<?php

use Velox\MailSendVx\Service\Flow\FlowWorkerService;

class MailsendvxQueuecronModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ajax = true;
    public $ssl = true;

    public function postProcess(): void
    {
        $token = (string) Tools::getValue('token');
        $expectedToken = (string) Configuration::get(\Velox\MailSendVx\ModuleConstants::CONFIG_CRON_TOKEN);

        header('Content-Type: application/json');

        if ($token === '' || !hash_equals($expectedToken, $token)) {
            http_response_code(403);
            $this->ajaxRender(json_encode([
                'success' => false,
                'message' => 'Invalid cron token.',
            ]));

            return;
        }

        $limit = max(1, min(500, (int) Tools::getValue('limit', 50)));
        $service = $this->module->get('prestashop.module.mailsendvx.service.flow_worker');

        if (!$service instanceof FlowWorkerService) {
            http_response_code(500);
            $this->ajaxRender(json_encode([
                'success' => false,
                'message' => 'Flow worker service is not available.',
            ]));

            return;
        }

        $this->ajaxRender(json_encode([
            'success' => true,
            'result' => $service->processDueJobs($limit),
        ]));
    }
}
