<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class AdminMailsendvxTemplatesController extends ModuleAdminController
{
    public function initContent(): void
    {
        $router = SymfonyContainer::getInstance()->get('router');
        Tools::redirectAdmin($router->generate('mailsendvx_templates'));
    }
}
