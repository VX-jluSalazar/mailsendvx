<?php

namespace Velox\MailSendVx\Service\Flow;

use Configuration;
use Context;
use Throwable;
use Validate;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Repository\MailSendVxFlowRepository;
use Velox\MailSendVx\Repository\MailSendVxLogRepository;
use Velox\MailSendVx\Repository\MailSendVxQueueRepository;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;
use Velox\MailSendVx\Service\Mail\MailSendVxMailer;

class FlowWorkerService
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var MailSendVxQueueRepository
     */
    private $queueRepository;

    /**
     * @var MailSendVxFlowRepository
     */
    private $flowRepository;

    /**
     * @var MailSendVxTemplateRepository
     */
    private $templateRepository;

    /**
     * @var MailSendVxLogRepository
     */
    private $logRepository;

    /**
     * @var MailSendVxMailer
     */
    private $mailer;

    /**
     * @var FlowConditionEvaluator
     */
    private $conditionEvaluator;

    public function __construct(
        Context $context,
        MailSendVxQueueRepository $queueRepository,
        MailSendVxFlowRepository $flowRepository,
        MailSendVxTemplateRepository $templateRepository,
        MailSendVxLogRepository $logRepository,
        MailSendVxMailer $mailer,
        FlowConditionEvaluator $conditionEvaluator
    ) {
        $this->context = $context;
        $this->queueRepository = $queueRepository;
        $this->flowRepository = $flowRepository;
        $this->templateRepository = $templateRepository;
        $this->logRepository = $logRepository;
        $this->mailer = $mailer;
        $this->conditionEvaluator = $conditionEvaluator;
    }

    /**
     * @return array<string, mixed>
     */
    public function processDueJobs(int $limit = 50, int $lockTimeoutMinutes = 30): array
    {
        if (!(bool) Configuration::get(ModuleConstants::CONFIG_ENABLED)) {
            return [
                'enabled' => false,
                'processed' => 0,
                'message' => 'Module event capture is disabled.',
            ];
        }

        $limit = max(1, min(500, $limit));
        $releasedLocks = $this->queueRepository->releaseExpiredLocks($lockTimeoutMinutes);
        $jobs = $this->queueRepository->findDueJobs($limit);
        $summary = [
            'enabled' => true,
            'requested_limit' => $limit,
            'released_locks' => $releasedLocks,
            'found' => count($jobs),
            'locked' => 0,
            'processed' => 0,
            'sent' => 0,
            'retry_scheduled' => 0,
            'failed' => 0,
            'skipped' => 0,
            'cancelled' => 0,
            'contended' => 0,
        ];

        foreach ($jobs as $job) {
            $idQueue = (int) ($job['id_mailsendvx_queue'] ?? 0);
            if ($idQueue <= 0) {
                continue;
            }

            $lockToken = $this->generateLockToken();
            if (!$this->queueRepository->markAsProcessing($idQueue, $lockToken)) {
                ++$summary['contended'];
                continue;
            }

            ++$summary['locked'];
            ++$summary['processed'];

            $result = $this->processLockedJob($idQueue, $lockToken);
            if (isset($summary[$result])) {
                ++$summary[$result];
            }
        }

        return $summary;
    }

    private function processLockedJob(int $idQueue, string $lockToken): string
    {
        $job = $this->queueRepository->findById($idQueue);
        if (!$job || (string) ($job['lock_token'] ?? '') !== $lockToken || (string) ($job['status'] ?? '') !== 'processing') {
            return 'contended';
        }

        $payload = $this->decodePayload($job);
        if ($payload === null) {
            $message = 'Queued job payload is not valid JSON.';
            $this->logQueueOutcome($job, 'skipped', $message, []);
            $this->queueRepository->markAsSkipped($idQueue, $message, $lockToken);

            return 'skipped';
        }

        $recipient = (string) ($job['recipient'] ?? '');
        if ($recipient === '' || !Validate::isEmail($recipient)) {
            $message = 'Queued job has no valid recipient.';
            $this->logQueueOutcome($job, 'skipped', $message, $payload);
            $this->queueRepository->markAsSkipped($idQueue, $message, $lockToken);

            return 'skipped';
        }

        $revalidation = $this->revalidateJob($job, $payload);
        if ($revalidation['action'] === 'cancelled') {
            $message = $revalidation['message'];
            $this->logQueueOutcome($job, 'cancelled', $message, $payload);
            $this->queueRepository->markAsCancelled($idQueue, $message, $lockToken);

            return 'cancelled';
        }

        if ($revalidation['action'] === 'skipped') {
            $message = $revalidation['message'];
            $this->logQueueOutcome($job, 'skipped', $message, $payload);
            $this->queueRepository->markAsSkipped($idQueue, $message, $lockToken);

            return 'skipped';
        }

        $templateId = (int) ($job['id_template'] ?? 0);
        $template = $templateId > 0 ? $this->templateRepository->findById($templateId) : false;
        if (!$template) {
            $message = 'Queued job template no longer exists.';
            $this->logQueueOutcome($job, 'cancelled', $message, $payload);
            $this->queueRepository->markAsCancelled($idQueue, $message, $lockToken);

            return 'cancelled';
        }

        try {
            $sent = $this->mailer->sendTemplate(
                $template,
                $recipient,
                $this->resolveRecipientName($payload),
                $payload,
                $this->resolveLanguageId($payload),
                $this->resolveShopId($job, $payload),
                $idQueue
            );

            if ($sent) {
                $this->queueRepository->markAsSent($idQueue, $lockToken);

                return 'sent';
            }

            return $this->handleFailedAttempt($job, $payload, $lockToken, 'Mail provider returned false.');
        } catch (Throwable $exception) {
            return $this->handleFailedAttempt($job, $payload, $lockToken, $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     *
     * @return array{action: string, message: string}
     */
    private function revalidateJob(array $job, array $payload): array
    {
        $idFlow = (int) ($job['id_flow'] ?? 0);
        if ($idFlow <= 0) {
            return [
                'action' => 'send',
                'message' => '',
            ];
        }

        $flow = $this->flowRepository->findById($idFlow);
        if (!$flow || (int) ($flow['version'] ?? 0) !== (int) ($job['flow_version'] ?? 0)) {
            return [
                'action' => 'send',
                'message' => '',
            ];
        }

        $step = $this->findStep((array) ($flow['steps_json'] ?? []), (string) ($job['step_id'] ?? ''));
        if ($step === null) {
            return [
                'action' => 'skipped',
                'message' => 'Flow step no longer exists for the queued job version.',
            ];
        }

        if (empty($step['active'])) {
            return [
                'action' => 'cancelled',
                'message' => 'Queued job cancelled because the referenced step is inactive.',
            ];
        }

        $flowConditions = is_array($flow['conditions_json'] ?? null) ? $flow['conditions_json'] : [];
        if (!$this->conditionEvaluator->matches($flowConditions, $payload)) {
            return [
                'action' => 'skipped',
                'message' => 'Queued job skipped because flow conditions no longer match the stored payload.',
            ];
        }

        $stepConditions = is_array($step['conditions'] ?? null) ? $step['conditions'] : [];
        if (!$this->conditionEvaluator->matches($stepConditions, $payload)) {
            return [
                'action' => 'skipped',
                'message' => 'Queued job skipped because step conditions no longer match the stored payload.',
            ];
        }

        $cancelRules = is_array($step['cancel_rules'] ?? null) ? $step['cancel_rules'] : [];
        if (!empty($cancelRules) && $this->conditionEvaluator->matches($cancelRules, $payload)) {
            return [
                'action' => 'cancelled',
                'message' => 'Queued job cancelled because step cancel_rules matched the stored payload.',
            ];
        }

        return [
            'action' => 'send',
            'message' => '',
        ];
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     */
    private function handleFailedAttempt(array $job, array $payload, string $lockToken, string $message): string
    {
        $attempts = max(0, (int) ($job['attempts'] ?? 0)) + 1;
        $maxAttempts = max(1, (int) ($job['max_attempts'] ?? 3));
        $idQueue = (int) ($job['id_mailsendvx_queue'] ?? 0);
        $status = $attempts >= $maxAttempts ? 'failed' : 'retry_scheduled';
        $retryAt = $attempts < $maxAttempts
            ? date('Y-m-d H:i:s', strtotime(sprintf('+%d minutes', $this->getRetryDelayMinutes($attempts))))
            : null;

        $this->queueRepository->markAsFailed($idQueue, $attempts, $maxAttempts, $message, $retryAt, $lockToken);

        if ($status === 'retry_scheduled' && $retryAt !== null) {
            $message = sprintf('Attempt %d/%d failed. Retry scheduled at %s. %s', $attempts, $maxAttempts, $retryAt, $message);
        } else {
            $message = sprintf('Attempt %d/%d failed permanently. %s', $attempts, $maxAttempts, $message);
        }

        $this->logQueueOutcome($job, $status, $message, $payload);

        return $status;
    }

    /**
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>|null
     */
    private function decodePayload(array $job): ?array
    {
        $rawPayload = $job['payload_json'] ?? $job['payload'] ?? null;
        if (!is_string($rawPayload) || $rawPayload === '') {
            return [];
        }

        $decoded = json_decode($rawPayload, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     *
     * @return array<string, mixed>|null
     */
    private function findStep(array $steps, string $stepId): ?array
    {
        foreach ($steps as $step) {
            if (is_array($step) && (string) ($step['id'] ?? '') === $stepId) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveRecipientName(array $payload): ?string
    {
        $name = $payload['customer']['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     */
    private function resolveShopId(array $job, array $payload): int
    {
        $idShop = $payload['shop']['id'] ?? null;
        if (is_numeric($idShop) && (int) $idShop > 0) {
            return (int) $idShop;
        }

        if (!empty($job['id_shop'])) {
            return (int) $job['id_shop'];
        }

        return (int) $this->context->shop->id;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveLanguageId(array $payload): int
    {
        $idLang = $payload['shop']['id_lang'] ?? null;
        if (is_numeric($idLang) && (int) $idLang > 0) {
            return (int) $idLang;
        }

        return (int) $this->context->language->id;
    }

    private function getRetryDelayMinutes(int $attempts): int
    {
        return min(60, max(5, $attempts * 5));
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     */
    private function logQueueOutcome(array $job, string $status, string $message, array $payload): void
    {
        $this->logRepository->add(
            (string) ($job['event_name'] ?? ''),
            $status,
            (string) ($job['recipient'] ?? ''),
            !empty($job['id_template']) ? (int) $job['id_template'] : null,
            !empty($job['id_mailsendvx_queue']) ? (int) $job['id_mailsendvx_queue'] : null,
            $payload,
            $message,
            !empty($job['id_shop']) ? (int) $job['id_shop'] : null
        );
    }

    private function generateLockToken(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Throwable $exception) {
            return sha1(uniqid('mailsendvx_lock_', true));
        }
    }
}
