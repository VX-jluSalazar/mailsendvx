<?php

use Velox\MailSendVx\ModuleConstants;

class MailsendvxAbandonedcartcronModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ajax = true;
    public $ssl = true;

    public function postProcess(): void
    {
        $token = (string) Tools::getValue('token');
        $expectedToken = (string) Configuration::get(ModuleConstants::CONFIG_CRON_TOKEN);

        if ($token === '' || !hash_equals($expectedToken, $token)) {
            http_response_code(403);
            $this->respondAndExit([
                'success' => false,
                'message' => 'Invalid cron token.',
            ]);
        }

        $result = $this->module->get('prestashop.module.mailsendvx.service.abandoned_cart')->processDueCarts();
        $this->respondAndExit([
            'success' => true,
            'result' => $result,
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
