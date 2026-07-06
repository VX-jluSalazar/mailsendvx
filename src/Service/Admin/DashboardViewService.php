<?php

namespace Velox\MailSendVx\Service\Admin;

use Context;
use Velox\MailSendVx\Repository\MailSendVxEventRepository;
use Velox\MailSendVx\Repository\MailSendVxLogRepository;
use Velox\MailSendVx\Repository\MailSendVxQueueRepository;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;

class DashboardViewService
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var MailSendVxTemplateRepository
     */
    private $templateRepository;

    /**
     * @var MailSendVxQueueRepository
     */
    private $queueRepository;

    /**
     * @var MailSendVxEventRepository
     */
    private $eventRepository;

    /**
     * @var MailSendVxLogRepository
     */
    private $logRepository;

    public function __construct(
        Context $context,
        MailSendVxTemplateRepository $templateRepository,
        MailSendVxQueueRepository $queueRepository,
        MailSendVxEventRepository $eventRepository,
        MailSendVxLogRepository $logRepository
    )
    {
        $this->context = $context;
        $this->templateRepository = $templateRepository;
        $this->queueRepository = $queueRepository;
        $this->eventRepository = $eventRepository;
        $this->logRepository = $logRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $recentLogs = $this->logRepository->getRecent(20);

        return [
            'templates_count' => $this->templateRepository->countAll(),
            'scheduled_count' => $this->queueRepository->countByStatus('scheduled'),
            'pending_count' => $this->queueRepository->countByStatus('pending'),
            'recent_events' => $this->eventRepository->getRecent(20),
            'recent_logs' => $this->formatLogsForView($recentLogs),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatLogsForView(array $logs): array
    {
        foreach ($logs as &$log) {
            $payload = [];
            if (!empty($log['payload']) && is_string($log['payload'])) {
                $decodedPayload = json_decode($log['payload'], true);
                if (is_array($decodedPayload)) {
                    $payload = $decodedPayload;
                }
            }

            $log['payload_pretty'] = !empty($payload)
                ? (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : '{}';
        }

        unset($log);

        return $logs;
    }
}
