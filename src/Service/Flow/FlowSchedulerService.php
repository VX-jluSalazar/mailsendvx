<?php

namespace Velox\MailSendVx\Service\Flow;

use DateInterval;
use DateTimeImmutable;
use Validate;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Repository\MailSendVxFlowRepository;
use Velox\MailSendVx\Repository\MailSendVxLogRepository;
use Velox\MailSendVx\Repository\MailSendVxQueueRepository;

class FlowSchedulerService
{
    /**
     * @var MailSendVxFlowRepository
     */
    private $flowRepository;

    /**
     * @var MailSendVxQueueRepository
     */
    private $queueRepository;

    /**
     * @var MailSendVxLogRepository
     */
    private $logRepository;

    /**
     * @var FlowConditionEvaluator
     */
    private $conditionEvaluator;

    public function __construct(
        MailSendVxFlowRepository $flowRepository,
        MailSendVxQueueRepository $queueRepository,
        MailSendVxLogRepository $logRepository,
        FlowConditionEvaluator $conditionEvaluator
    ) {
        $this->flowRepository = $flowRepository;
        $this->queueRepository = $queueRepository;
        $this->logRepository = $logRepository;
        $this->conditionEvaluator = $conditionEvaluator;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, int>
     */
    public function scheduleEvent(string $eventName, array $payload, ?int $idShop = null): array
    {
        $scheduled = 0;
        $skipped = 0;
        $contextType = ModuleConstants::getEventContextType($eventName);

        foreach ($this->flowRepository->findActiveByTriggerEvent($eventName, $idShop) as $flow) {
            if ($contextType !== null && (string) ($flow['context_type'] ?? '') !== $contextType) {
                ++$skipped;
                $this->logRepository->add($eventName, 'skipped', $this->extractRecipient($payload), null, null, $payload, 'Flow skipped because context_type does not match event payload.', $idShop);
                continue;
            }

            $flowConditions = is_array($flow['conditions_json'] ?? null) ? $flow['conditions_json'] : [];
            if (!$this->conditionEvaluator->matches($flowConditions, $payload)) {
                ++$skipped;
                continue;
            }

            $recipient = $this->extractRecipient($payload);
            if (!$recipient || !Validate::isEmail($recipient)) {
                ++$skipped;
                $this->logRepository->add($eventName, 'skipped', $recipient, null, null, $payload, 'Flow skipped because no valid recipient was resolved for the payload.', $idShop);
                continue;
            }

            $baseTime = new DateTimeImmutable('now');
            $previousSchedule = $baseTime;
            foreach ((array) ($flow['steps_json'] ?? []) as $step) {
                if (empty($step['active'])) {
                    continue;
                }

                $stepConditions = is_array($step['conditions'] ?? null) ? $step['conditions'] : [];
                if (!$this->conditionEvaluator->matches($stepConditions, $payload)) {
                    continue;
                }

                $scheduledAt = $this->calculateScheduledAt(is_array($step['delay'] ?? null) ? $step['delay'] : [], $baseTime, $previousSchedule);
                $previousSchedule = $scheduledAt;

                $this->queueRepository->scheduleJob(
                    $eventName,
                    $recipient,
                    $payload,
                    $scheduledAt->format('Y-m-d H:i:s'),
                    isset($step['template_id']) ? (int) $step['template_id'] : null,
                    (int) ($flow['id_mailsendvx_flow'] ?? 0),
                    (int) ($flow['version'] ?? 1),
                    isset($step['id']) ? (string) $step['id'] : null,
                    $idShop
                );
                ++$scheduled;
            }
        }

        return [
            'scheduled' => $scheduled,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param array<string, mixed> $delay
     */
    private function calculateScheduledAt(array $delay, DateTimeImmutable $triggerTime, DateTimeImmutable $previousStepTime): DateTimeImmutable
    {
        $value = max(0, (int) ($delay['value'] ?? 0));
        $unit = strtolower(trim((string) ($delay['unit'] ?? 'hour')));
        $mode = strtolower(trim((string) ($delay['mode'] ?? 'after_trigger')));
        $baseTime = $mode === 'after_previous_step' ? $previousStepTime : $triggerTime;

        if ($mode === 'immediate' || $value === 0) {
            return $baseTime;
        }

        switch ($unit) {
            case 'minute':
                $intervalSpec = sprintf('PT%dM', $value);
                break;
            case 'day':
                $intervalSpec = sprintf('P%dD', $value);
                break;
            case 'week':
                $intervalSpec = sprintf('P%dW', $value);
                break;
            case 'hour':
            default:
                $intervalSpec = sprintf('PT%dH', $value);
                break;
        }

        return $baseTime->add(new DateInterval($intervalSpec));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractRecipient(array $payload): ?string
    {
        $email = $payload['customer']['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
