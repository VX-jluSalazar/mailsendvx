<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__DIR__, 2) . '/classes/Repository/MailSendVxLogRepository.php';
require_once dirname(__DIR__, 2) . '/classes/Repository/MailSendVxQueueRepository.php';
require_once dirname(__DIR__, 2) . '/classes/Repository/MailSendVxTemplateRepository.php';

class AdminMailsendvxDashboardController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent(): void
    {
        parent::initContent();

        $this->context->smarty->assign([
            'templates_count' => (new MailSendVxTemplateRepository())->countAll(),
            'scheduled_count' => (new MailSendVxQueueRepository())->countByStatus('scheduled'),
            'pending_count' => (new MailSendVxQueueRepository())->countByStatus('pending'),
            'recent_logs' => (new MailSendVxLogRepository())->getRecent(20),
            'configure_url' => $this->context->link->getAdminLink('AdminModules')
                . '&configure=' . $this->module->name,
        ]);

        $this->setTemplate('dashboard.tpl');
    }
}

