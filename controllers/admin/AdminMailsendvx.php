<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminMailsendvxController extends ModuleAdminController
{
    public function init(): void
    {
        parent::init();

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMailsendvxDashboard'));
    }
}
