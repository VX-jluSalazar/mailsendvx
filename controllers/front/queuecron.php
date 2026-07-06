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

        if ($token === '' || !hash_equals($expectedToken, $token)) {
            http_response_code(403);
            $this->respondAndExit([
                'success' => false,
                'message' => 'Invalid cron token.',
            ]);
        }

        $limit = max(1, min(500, (int) Tools::getValue('limit', 50)));
        $service = $this->module->get('prestashop.module.mailsendvx.service.flow_worker');

        if (!$service instanceof FlowWorkerService) {
            http_response_code(500);
            $this->respondAndExit([
                'success' => false,
                'message' => 'Flow worker service is not available.',
            ]);
        }

        $this->respondAndExit([
            'success' => true,
            'result' => $service->processDueJobs($limit),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function respondAndExit(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->ajaxRender((string) json_encode($payload));
        exit;
    }
}
