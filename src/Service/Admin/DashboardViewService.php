<?php

namespace Velox\MailSendVx\Service\Admin;

use Context;
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

    public function __construct(
        Context $context,
        MailSendVxTemplateRepository $templateRepository,
        MailSendVxQueueRepository $queueRepository
    )
    {
        $this->context = $context;
        $this->templateRepository = $templateRepository;
        $this->queueRepository = $queueRepository;
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
        ];
    }
}
