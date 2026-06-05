<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminMailsendvxTemplatesController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent(): void
    {
        $this->content .= $this->module->getTemplatesContent();

        parent::initContent();
    }
}
