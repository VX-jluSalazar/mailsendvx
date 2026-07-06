<?php

use Velox\MailSendVx\Service\Marketing\UnsubscribeUrlService;

class MailsendvxUnsubscribeModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ssl = true;

    public function initContent(): void
    {
        parent::initContent();

        $email = (string) Tools::getValue('email');
        $token = (string) Tools::getValue('token');
        $idShop = max(1, (int) Tools::getValue('id_shop', (int) $this->context->shop->id));
        $service = $this->module->get('prestashop.module.mailsendvx.service.unsubscribe_url');

        $result = [
            'success' => false,
            'message' => 'No fue posible procesar la desuscripcion en este momento.',
        ];

        if ($service instanceof UnsubscribeUrlService) {
            $result = $service->unsubscribeByToken($email, $token, $idShop);
        }

        $this->context->smarty->assign([
            'mailsendvx_unsubscribe_success' => !empty($result['success']),
            'mailsendvx_unsubscribe_message' => (string) ($result['message'] ?? ''),
            'mailsendvx_shop_url' => $this->context->link->getBaseLink($idShop, true),
            'mailsendvx_shop_name' => (string) $this->context->shop->name,
        ]);

        $this->setTemplate('module:mailsendvx/views/templates/front/unsubscribe_result.tpl');
    }
}
