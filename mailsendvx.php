<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Velox\MailSendVx\Install\ConfigurationInstaller;
use Velox\MailSendVx\Install\DatabaseInstaller;
use Velox\MailSendVx\Install\Installer;
use Velox\MailSendVx\Install\TabInstaller;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Service\InstantEmailHookService;
use Velox\MailSendVx\Service\OrderStateEventService;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class Mailsendvx extends Module
{
    public const EVENT_ORDER_STATUS_CHANGED = ModuleConstants::EVENT_ORDER_STATUS_CHANGED;
    public const EVENT_ORDER_STATUS_LEGACY = ModuleConstants::EVENT_ORDER_STATUS_LEGACY;
    public const EVENT_CUSTOMER_REGISTERED = ModuleConstants::EVENT_CUSTOMER_REGISTERED;
    public const EVENT_NEWSLETTER_REGISTERED = ModuleConstants::EVENT_NEWSLETTER_REGISTERED;
    public const CONFIG_ENABLED = ModuleConstants::CONFIG_ENABLED;
    public const CONFIG_PROVIDER = ModuleConstants::CONFIG_PROVIDER;
    public const CONFIG_DEBUG = ModuleConstants::CONFIG_DEBUG;
    public const CONFIG_CRON_TOKEN = ModuleConstants::CONFIG_CRON_TOKEN;

    private const SUBMIT_ACTION = 'submitMailsendvxConfig';
    private const TEMPLATE_SUBMIT_ACTION = 'submitMailsendvxTemplate';
    private const TEMPLATE_TEST_ACTION = 'submitMailsendvxTest';
    public const ADMIN_PARENT_TAB_CLASS = ModuleConstants::ADMIN_PARENT_TAB_CLASS;
    public const ADMIN_CONFIGURE_TAB_CLASS = ModuleConstants::ADMIN_CONFIGURE_TAB_CLASS;
    public const ADMIN_TEMPLATES_TAB_CLASS = ModuleConstants::ADMIN_TEMPLATES_TAB_CLASS;
    public const ADMIN_DASHBOARD_TAB_CLASS = ModuleConstants::ADMIN_DASHBOARD_TAB_CLASS;
    public const ADMIN_CONFIGURE_SECTION_CLASS = ModuleConstants::ADMIN_CONFIGURE_SECTION_CLASS;

    public function __construct()
    {
        $this->loadClasses();

        $this->name = 'mailsendvx';
        $this->tab = 'emailing';
        $this->version = '0.1.0';
        $this->author = 'Velox';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->trans('Mail Send VX', [], 'Modules.Mailsendvx.Admin');
        $this->description = $this->trans('Base module for transactional and automated email sending.', [], 'Modules.Mailsendvx.Admin');
        $this->confirmUninstall = $this->trans('Uninstalling will remove Mail Send VX tables and settings. Continue?', [], 'Modules.Mailsendvx.Admin');
    }

    private function loadClasses(): void
    {
        $basePath = __DIR__ . '/classes/';
        $files = [
            'Provider/MailSendVxMailProviderInterface.php',
            'Provider/MailSendVxPrestaShopMailProvider.php',
            'Repository/MailSendVxEventRepository.php',
            'Repository/MailSendVxLogRepository.php',
            'Repository/MailSendVxTemplateRepository.php',
            'Repository/MailSendVxQueueRepository.php',
            'Service/MailSendVxVariableRenderer.php',
            'Service/MailSendVxLogger.php',
            'Service/MailSendVxMailer.php',
        ];

        foreach ($files as $file) {
            $path = $basePath . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        return $this->getInstaller()->install($this);
    }

    public function uninstall(): bool
    {
        return $this->getInstaller()->uninstall()
            && parent::uninstall();
    }

    public function hookDisplayBackOfficeHeader(): void
    {
        $controller = Tools::getValue('controller');
        $allowedControllers = [
            self::ADMIN_CONFIGURE_TAB_CLASS,
            self::ADMIN_TEMPLATES_TAB_CLASS,
            self::ADMIN_DASHBOARD_TAB_CLASS,
        ];

        if (Tools::getValue('configure') !== $this->name && !in_array($controller, $allowedControllers, true)) {
            return;
        }

        $cssPath = __DIR__ . '/views/css/admin.css';
        if (file_exists($cssPath)) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css?v=' . (string) filemtime($cssPath));
        }
    }

    public function getContent(): string
    {
        $this->getInstaller()->ensureRuntimeSchema();
        $router = SymfonyContainer::getInstance()->get('router');
        Tools::redirectAdmin($router->generate('mailsendvx_configuration'));

        return '';
    }

    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $this->getInstantEmailHookService()->handleOrderStatusPostUpdate($params, $this);
    }

    public function hookActionCustomerAccountAdd(array $params): void
    {
        $this->getInstantEmailHookService()->handleCustomerAccountAdd($params, $this);
    }

    public function hookActionNewsletterRegistrationAfter(array $params): void
    {
        $this->getInstantEmailHookService()->handleNewsletterRegistrationAfter($params, $this);
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    private function getInstaller(): Installer
    {
        return new Installer(
            new ConfigurationInstaller(),
            new DatabaseInstaller(),
            new TabInstaller()
        );
    }

    private function getInstantEmailHookService(): InstantEmailHookService
    {
        $service = $this->get('prestashop.module.mailsendvx.service.instant_email_hook');
        if ($service instanceof InstantEmailHookService) {
            return $service;
        }

        return new InstantEmailHookService(
            $this->context,
            new OrderStateEventService($this->context)
        );
    }
}
