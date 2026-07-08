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

        try {
            $service = $this->module->get('prestashop.module.mailsendvx.service.abandoned_cart');
            $result = $service->processDueCarts();
            $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [];
            $success = empty($errors);

            if (!$success) {
                http_response_code(500);
            }

            $payload = [
                'success' => $success,
                'result' => $result,
            ];

            if (!$success) {
                $payload['message'] = 'Abandoned cart cron finished with errors.';
            }

            $this->respondAndExit($payload);
        } catch (Throwable $exception) {
            http_response_code(500);
            $this->respondAndExit([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }
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
