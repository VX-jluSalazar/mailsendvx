<?php

namespace Velox\MailSendVx\Service;

use Context;
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
     * @var MailSendVxLogRepository
     */
    private $logRepository;

    public function __construct(
        Context $context,
        MailSendVxTemplateRepository $templateRepository,
        MailSendVxQueueRepository $queueRepository,
        MailSendVxLogRepository $logRepository
    )
    {
        $this->context = $context;
        $this->templateRepository = $templateRepository;
        $this->queueRepository = $queueRepository;
        $this->logRepository = $logRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'templates_count' => $this->templateRepository->countAll(),
            'scheduled_count' => $this->queueRepository->countByStatus('scheduled'),
            'pending_count' => $this->queueRepository->countByStatus('pending'),
            'recent_logs' => $this->logRepository->getRecent(20),
        ];
    }
}
