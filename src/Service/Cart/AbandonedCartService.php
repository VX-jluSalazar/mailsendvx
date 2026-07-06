<?php

namespace Velox\MailSendVx\Service\Cart;

use Configuration;
use Context;
use DateInterval;
use DateTimeImmutable;
use Order;
use PrestaShopLogger;
use Throwable;
use Validate;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Repository\MailSendVxAbandonedCartRepository;
use Velox\MailSendVx\Repository\MailSendVxEventRepository;
use Velox\MailSendVx\Repository\MailSendVxLogRepository;
use Velox\MailSendVx\Repository\MailSendVxQueueRepository;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;
use Velox\MailSendVx\Service\ContextBuilder\CartTemplateContextBuilder;
use Velox\MailSendVx\Service\Flow\FlowSchedulerService;
use Velox\MailSendVx\Service\Flow\FlowWorkerService;
use Velox\MailSendVx\Service\Mail\MailSendVxMailer;

class AbandonedCartService
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var CartTemplateContextBuilder
     */
    private $cartContextBuilder;

    /**
     * @var MailSendVxAbandonedCartRepository
     */
    private $repository;

    /**
     * @var MailSendVxEventRepository
     */
    private $eventRepository;

    /**
     * @var MailSendVxLogRepository
     */
    private $logRepository;

    /**
     * @var MailSendVxMailer
     */
    private $mailer;

    /**
     * @var MailSendVxQueueRepository
     */
    private $queueRepository;

    /**
     * @var MailSendVxTemplateRepository
     */
    private $templateRepository;

    /**
     * @var FlowSchedulerService
     */
    private $flowScheduler;

    /**
     * @var FlowWorkerService
     */
    private $flowWorker;

    public function __construct(
        Context $context,
        CartTemplateContextBuilder $cartContextBuilder,
        MailSendVxAbandonedCartRepository $repository,
        MailSendVxEventRepository $eventRepository,
        MailSendVxLogRepository $logRepository,
        MailSendVxMailer $mailer,
        MailSendVxQueueRepository $queueRepository,
        MailSendVxTemplateRepository $templateRepository,
        FlowSchedulerService $flowScheduler,
        FlowWorkerService $flowWorker
    ) {
        $this->context = $context;
        $this->cartContextBuilder = $cartContextBuilder;
        $this->repository = $repository;
        $this->eventRepository = $eventRepository;
        $this->logRepository = $logRepository;
        $this->mailer = $mailer;
        $this->queueRepository = $queueRepository;
        $this->templateRepository = $templateRepository;
        $this->flowScheduler = $flowScheduler;
        $this->flowWorker = $flowWorker;
    }

    /**
     * @return array<string, mixed>
     */
    public function processDueCarts(): array
    {
        if (!(bool) Configuration::get(ModuleConstants::CONFIG_ENABLED)) {
            return [
                'enabled' => false,
                'abandoned_cart_enabled' => false,
                'processed' => 0,
                'captured' => 0,
                'recovered' => 0,
                'skipped' => 0,
                'message' => 'Module event capture is disabled.',
            ];
        }

        if (!(bool) Configuration::get(ModuleConstants::CONFIG_ABANDONED_CART_ENABLED)) {
            return [
                'enabled' => true,
                'abandoned_cart_enabled' => false,
                'processed' => 0,
                'captured' => 0,
                'recovered' => 0,
                'skipped' => 0,
                'message' => 'Abandoned cart detection is disabled.',
            ];
        }

        $batchSize = max(1, (int) Configuration::get(ModuleConstants::CONFIG_ABANDONED_CART_CRON_BATCH_SIZE));
        $cutoff = $this->buildCutoffDate();
        $requireCustomer = (bool) Configuration::get(ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_CUSTOMER);
        $requireProducts = (bool) Configuration::get(ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_PRODUCTS);
        $recoveredCount = $this->syncRecoveredCarts($batchSize);
        $captured = 0;
        $skipped = 0;
        $processed = 0;

        foreach ($this->repository->findEligibleCarts($cutoff->format('Y-m-d H:i:s'), $batchSize, $requireCustomer, $requireProducts) as $candidate) {
            ++$processed;
            if (!$this->shouldCaptureCandidate($candidate)) {
                ++$skipped;
                continue;
            }

            if ($this->captureCandidate($candidate)) {
                ++$captured;
                continue;
            }

            ++$skipped;
        }

        return [
            'enabled' => true,
            'abandoned_cart_enabled' => true,
            'processed' => $processed,
            'captured' => $captured,
            'recovered' => $recoveredCount,
            'skipped' => $skipped,
            'cutoff_at' => $cutoff->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function markRecoveredFromOrderParams(array $params): void
    {
        $order = $params['order'] ?? null;
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            $order = isset($params['id_order']) ? new Order((int) $params['id_order']) : null;
        }

        if (!$order instanceof Order || !Validate::isLoadedObject($order) || !(int) $order->id_cart) {
            return;
        }

        $existing = $this->repository->findByCartId((int) $order->id_cart);
        if (!$existing) {
            return;
        }

        $this->repository->saveState([
            'id_cart' => (int) $order->id_cart,
            'id_customer' => (int) $order->id_customer,
            'email' => $existing['email'] ?? null,
            'id_shop' => (int) $order->id_shop,
            'id_lang' => (int) $order->id_lang,
            'status' => 'recovered',
            'cart_snapshot' => $this->decodeSnapshot($existing['cart_snapshot'] ?? null),
            'last_activity_at' => date('Y-m-d H:i:s'),
            'abandoned_at' => $existing['abandoned_at'] ?? null,
            'recovered_at' => date('Y-m-d H:i:s'),
            'last_event_hash' => $existing['last_event_hash'] ?? null,
        ]);

        $this->cancelPendingCartJobs(
            (int) $order->id_cart,
            (int) $order->id_shop,
            sprintf('Queued cart_abandoned jobs cancelled because cart %d was converted into order %d.', (int) $order->id_cart, (int) $order->id)
        );
    }

    private function buildCutoffDate(): DateTimeImmutable
    {
        $delayValue = max(0, (int) Configuration::get(ModuleConstants::CONFIG_ABANDONED_CART_DELAY_VALUE));
        $delayUnit = (string) (Configuration::get(ModuleConstants::CONFIG_ABANDONED_CART_DELAY_UNIT) ?: 'hour');
        $intervalSpec = 'PT0M';

        switch ($delayUnit) {
            case 'minute':
                $intervalSpec = sprintf('PT%dM', $delayValue);
                break;
            case 'day':
                $intervalSpec = sprintf('P%dD', $delayValue);
                break;
            case 'week':
                $intervalSpec = sprintf('P%dW', $delayValue);
                break;
            case 'hour':
            default:
                $intervalSpec = sprintf('PT%dH', $delayValue);
                break;
        }

        return (new DateTimeImmutable('now'))->sub(new DateInterval($intervalSpec));
    }

    private function syncRecoveredCarts(int $limit): int
    {
        $recovered = 0;
        foreach ($this->repository->findRecoverableAbandonedCarts($limit) as $state) {
            $this->repository->saveState([
                'id_cart' => (int) $state['id_cart'],
                'id_customer' => (int) ($state['id_customer'] ?? 0),
                'email' => $state['email'] ?? null,
                'id_shop' => (int) ($state['id_shop'] ?? $this->context->shop->id),
                'id_lang' => (int) ($state['id_lang'] ?? $this->context->language->id),
                'status' => 'recovered',
                'cart_snapshot' => $this->decodeSnapshot($state['cart_snapshot'] ?? null),
                'last_activity_at' => (string) ($state['cart_date_upd'] ?? $state['last_activity_at'] ?? date('Y-m-d H:i:s')),
                'abandoned_at' => $state['abandoned_at'] ?? null,
                'recovered_at' => date('Y-m-d H:i:s'),
                'last_event_hash' => $state['last_event_hash'] ?? null,
            ]);
            $this->cancelPendingCartJobs(
                (int) $state['id_cart'],
                (int) ($state['id_shop'] ?? $this->context->shop->id),
                sprintf('Queued cart_abandoned jobs cancelled because cart %d is already recovered.', (int) $state['id_cart'])
            );
            ++$recovered;
        }

        return $recovered;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function shouldCaptureCandidate(array $candidate): bool
    {
        $existing = $this->repository->findByCartId((int) $candidate['id_cart']);
        if (!$existing) {
            return true;
        }

        if (($existing['status'] ?? null) !== 'abandoned') {
            return true;
        }

        $currentActivity = (string) ($candidate['date_upd'] ?? '');
        $abandonedAt = (string) ($existing['abandoned_at'] ?? '');

        return $currentActivity !== '' && $abandonedAt !== '' && strtotime($currentActivity) > strtotime($abandonedAt);
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function captureCandidate(array $candidate): bool
    {
        try {
            $abandonedAt = date('Y-m-d H:i:s');
            $variables = $this->cartContextBuilder->buildCartAbandonedContext([
                'id_cart' => (int) $candidate['id_cart'],
                'abandoned_at' => $abandonedAt,
            ]);
            $eventHash = sha1(sprintf(
                '%d|%s|%s',
                (int) $candidate['id_cart'],
                (string) ($candidate['date_upd'] ?? ''),
                ModuleConstants::EVENT_CART_ABANDONED
            ));

            $existing = $this->repository->findByCartId((int) $candidate['id_cart']);
            if ($existing && ($existing['last_event_hash'] ?? null) === $eventHash && ($existing['status'] ?? null) === 'abandoned') {
                return false;
            }

            $this->repository->saveState([
                'id_cart' => (int) $candidate['id_cart'],
                'id_customer' => (int) ($candidate['id_customer'] ?? 0),
                'email' => $variables['customer']['email'] ?? ($candidate['email'] ?? null),
                'id_shop' => (int) ($candidate['id_shop'] ?? $this->context->shop->id),
                'id_lang' => (int) ($candidate['id_lang'] ?? ($variables['shop']['id_lang'] ?? $this->context->language->id)),
                'status' => 'abandoned',
                'cart_snapshot' => $variables,
                'last_activity_at' => (string) ($candidate['date_upd'] ?? date('Y-m-d H:i:s')),
                'abandoned_at' => $abandonedAt,
                'recovered_at' => null,
                'last_event_hash' => $eventHash,
            ]);

            $this->eventRepository->add(
                ModuleConstants::EVENT_CART_ABANDONED,
                $variables,
                'cart',
                (string) $candidate['id_cart'],
                'captured',
                (int) ($candidate['id_shop'] ?? $this->context->shop->id)
            );

            $scheduleResult = $this->flowScheduler->scheduleEvent(
                ModuleConstants::EVENT_CART_ABANDONED,
                $variables,
                (int) ($candidate['id_shop'] ?? $this->context->shop->id)
            );

            $recipient = isset($variables['customer']['email']) ? (string) $variables['customer']['email'] : null;
            if (!$recipient || !Validate::isEmail($recipient)) {
                $this->logRepository->add(
                    ModuleConstants::EVENT_CART_ABANDONED,
                    'skipped',
                    $recipient,
                    null,
                    null,
                    $variables,
                    'No valid recipient found for abandoned cart.',
                    (int) ($candidate['id_shop'] ?? $this->context->shop->id)
                );

                $this->triggerFlowWorkerIfNeeded($scheduleResult);

                return true;
            }

            $idLang = (int) ($variables['shop']['id_lang'] ?? $this->context->language->id);
            $idShop = (int) ($variables['shop']['id'] ?? $this->context->shop->id);
            if ($this->templateRepositoryHasActiveInstantTemplate($idLang, $idShop)) {
                $this->mailer->sendEvent(
                    ModuleConstants::EVENT_CART_ABANDONED,
                    $recipient,
                    !empty($variables['customer']['name']) ? (string) $variables['customer']['name'] : null,
                    $variables,
                    $idLang,
                    $idShop
                );
            } elseif (($scheduleResult['scheduled'] ?? 0) <= 0) {
                $this->logRepository->add(
                    ModuleConstants::EVENT_CART_ABANDONED,
                    'skipped',
                    $recipient,
                    null,
                    null,
                    $variables,
                    'No active instant template or flow matched this abandoned cart event.',
                    $idShop
                );
            }

            $this->triggerFlowWorkerIfNeeded($scheduleResult);

            return true;
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                sprintf('Mail Send VX abandoned cart capture failed: %s', $exception->getMessage()),
                3,
                null,
                'Module',
                0,
                true
            );

            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeSnapshot($snapshot): ?array
    {
        if (!is_string($snapshot) || $snapshot === '') {
            return null;
        }

        $decoded = json_decode($snapshot, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function cancelPendingCartJobs(int $idCart, int $idShop, string $message): int
    {
        $cancelled = 0;

        foreach ($this->queueRepository->findActiveJobsByEvent(ModuleConstants::EVENT_CART_ABANDONED, $idShop) as $job) {
            $payload = $this->decodeQueuePayload($job);
            if ($payload === null) {
                continue;
            }

            $jobCartId = $payload['cart']['id'] ?? null;
            if (!is_numeric($jobCartId) || (int) $jobCartId !== $idCart) {
                continue;
            }

            $idQueue = (int) ($job['id_mailsendvx_queue'] ?? 0);
            if ($idQueue <= 0 || !$this->queueRepository->cancelPendingJob($idQueue, $message)) {
                continue;
            }

            ++$cancelled;
            $this->logRepository->add(
                ModuleConstants::EVENT_CART_ABANDONED,
                'cancelled',
                isset($job['recipient']) ? (string) $job['recipient'] : null,
                !empty($job['id_template']) ? (int) $job['id_template'] : null,
                $idQueue,
                $payload,
                $message,
                $idShop
            );
        }

        return $cancelled;
    }

    /**
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>|null
     */
    private function decodeQueuePayload(array $job): ?array
    {
        $rawPayload = $job['payload_json'] ?? $job['payload'] ?? null;
        if (!is_string($rawPayload) || $rawPayload === '') {
            return [];
        }

        $decoded = json_decode($rawPayload, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, int> $scheduleResult
     */
    private function triggerFlowWorkerIfNeeded(array $scheduleResult): void
    {
        if ((int) ($scheduleResult['scheduled'] ?? 0) <= 0) {
            return;
        }

        try {
            $this->flowWorker->processDueJobs(20);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                sprintf('Mail Send VX abandoned cart flow worker trigger failed: %s', $exception->getMessage()),
                2,
                null,
                'Module',
                0,
                true
            );
        }
    }

    private function templateRepositoryHasActiveInstantTemplate(int $idLang, int $idShop): bool
    {
        return $this->templateRepository->hasActiveByEvent(ModuleConstants::EVENT_CART_ABANDONED, $idLang, $idShop);
    }
}
