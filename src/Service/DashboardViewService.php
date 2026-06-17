<?php

namespace Velox\MailSendVx\Service;

use Context;

class DashboardViewService
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        LegacyClassLoader::load();
        $this->context = $context;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'templates_count' => (new \MailSendVxTemplateRepository())->countAll(),
            'scheduled_count' => (new \MailSendVxQueueRepository())->countByStatus('scheduled'),
            'pending_count' => (new \MailSendVxQueueRepository())->countByStatus('pending'),
            'recent_logs' => (new \MailSendVxLogRepository())->getRecent(20),
        ];
    }
}
