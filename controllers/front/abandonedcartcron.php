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

        header('Content-Type: application/json');

        if ($token === '' || !hash_equals($expectedToken, $token)) {
            http_response_code(403);
            $this->ajaxRender(json_encode([
                'success' => false,
                'message' => 'Invalid cron token.',
            ]));

            return;
        }

        $result = $this->module->get('prestashop.module.mailsendvx.service.abandoned_cart')->processDueCarts();
        $this->ajaxRender(json_encode([
            'success' => true,
            'result' => $result,
        ]));
    }
}
