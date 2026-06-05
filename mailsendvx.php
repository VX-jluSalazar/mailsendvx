<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Mailsendvx extends Module
{
    public const CONFIG_ENABLED = 'MAILSENDVX_ENABLED';
    public const CONFIG_PROVIDER = 'MAILSENDVX_PROVIDER';
    public const CONFIG_DEBUG = 'MAILSENDVX_DEBUG';
    public const CONFIG_CRON_TOKEN = 'MAILSENDVX_CRON_TOKEN';

    private const SUBMIT_ACTION = 'submitMailsendvxConfig';
    private const ADMIN_PARENT_TAB_CLASS = 'AdminMailsendvx';
    private const ADMIN_CONFIGURE_TAB_CLASS = 'AdminMailsendvxConfigure';
    private const ADMIN_DASHBOARD_TAB_CLASS = 'AdminMailsendvxDashboard';

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
        return parent::install()
            && $this->installDatabase()
            && $this->installDefaultConfiguration()
            && $this->installAdminTabs()
            && $this->registerHook([
                'displayBackOfficeHeader',
                'actionOrderStatusPostUpdate',
                'actionCustomerAccountAdd',
                'actionNewsletterRegistrationAfter',
            ]);
    }

    public function uninstall(): bool
    {
        return $this->uninstallAdminTabs()
            && $this->uninstallDatabase()
            && $this->deleteConfiguration()
            && parent::uninstall();
    }

    public function hookDisplayBackOfficeHeader(): void
    {
        $controller = Tools::getValue('controller');
        $allowedControllers = [
            self::ADMIN_CONFIGURE_TAB_CLASS,
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
        $output = '';
        $this->installDatabase();
        $this->installDefaultConfiguration(false);
        $this->installAdminTabs();

        if (Tools::isSubmit(self::SUBMIT_ACTION)) {
            Configuration::updateValue(self::CONFIG_ENABLED, (bool) Tools::getValue(self::CONFIG_ENABLED));
            Configuration::updateValue(self::CONFIG_DEBUG, (bool) Tools::getValue(self::CONFIG_DEBUG));
            Configuration::updateValue(self::CONFIG_PROVIDER, 'prestashop_mail');
            $output .= $this->displayConfirmation($this->trans('Settings updated.', [], 'Admin.Notifications.Success'));
        }

        return $output . $this->renderConfigurationForm() . $this->renderStatusPanel();
    }

    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $this->logBaseEvent('order_status_updated', $params);
    }

    public function hookActionCustomerAccountAdd(array $params): void
    {
        $this->logBaseEvent('customer_registered', $params);
    }

    public function hookActionNewsletterRegistrationAfter(array $params): void
    {
        $this->logBaseEvent('newsletter_registered', $params);
    }

    private function logBaseEvent(string $eventName, array $params): void
    {
        if (!(bool) Configuration::get(self::CONFIG_ENABLED)) {
            return;
        }

        try {
            $payload = [
                'source' => 'prestashop_hook',
                'keys' => array_keys($params),
            ];
            (new MailSendVxEventRepository())->add($eventName, $payload);
            (new MailSendVxLogger())->info($eventName, 'Base event captured.', $payload);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                sprintf('Mail Send VX event log failed: %s', $exception->getMessage()),
                3,
                null,
                'Module',
                (int) $this->id,
                true
            );
        }
    }

    private function installDefaultConfiguration(bool $force = true): bool
    {
        $values = [
            self::CONFIG_ENABLED => '0',
            self::CONFIG_DEBUG => '0',
            self::CONFIG_PROVIDER => 'prestashop_mail',
            self::CONFIG_CRON_TOKEN => Tools::passwdGen(32),
        ];

        foreach ($values as $key => $value) {
            if ($force || Configuration::get($key) === false) {
                if (!Configuration::updateValue($key, $value)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function deleteConfiguration(): bool
    {
        return Configuration::deleteByName(self::CONFIG_ENABLED)
            && Configuration::deleteByName(self::CONFIG_DEBUG)
            && Configuration::deleteByName(self::CONFIG_PROVIDER)
            && Configuration::deleteByName(self::CONFIG_CRON_TOKEN);
    }

    private function installDatabase(): bool
    {
        $engine = _MYSQL_ENGINE_;
        $charset = 'DEFAULT CHARSET=utf8mb4';

        $queries = [
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_template` (
                `id_mailsendvx_template` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_lang` INT UNSIGNED NOT NULL DEFAULT 0,
                `event_name` VARCHAR(128) NOT NULL,
                `name` VARCHAR(191) NOT NULL,
                `subject` VARCHAR(191) NOT NULL,
                `mail_template` VARCHAR(128) NOT NULL DEFAULT "mailsendvx_default",
                `html_content` MEDIUMTEXT NULL,
                `text_content` MEDIUMTEXT NULL,
                `json_design` MEDIUMTEXT NULL,
                `provider` VARCHAR(64) NOT NULL DEFAULT "prestashop_mail",
                `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_template`),
                KEY `event_lookup` (`event_name`, `id_shop`, `id_lang`, `active`)
            ) ENGINE=' . $engine . ' ' . $charset,
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_event` (
                `id_mailsendvx_event` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `event_name` VARCHAR(128) NOT NULL,
                `object_type` VARCHAR(64) NULL,
                `object_id` VARCHAR(128) NULL,
                `payload` MEDIUMTEXT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT "captured",
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_event`),
                KEY `event_name` (`event_name`),
                KEY `object_lookup` (`object_type`, `object_id`)
            ) ENGINE=' . $engine . ' ' . $charset,
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_flow` (
                `id_mailsendvx_flow` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `name` VARCHAR(191) NOT NULL,
                `trigger_event` VARCHAR(128) NOT NULL,
                `conditions_json` MEDIUMTEXT NULL,
                `steps_json` MEDIUMTEXT NULL,
                `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_flow`),
                KEY `trigger_event` (`trigger_event`, `active`)
            ) ENGINE=' . $engine . ' ' . $charset,
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_queue` (
                `id_mailsendvx_queue` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_template` INT UNSIGNED NULL,
                `id_flow` INT UNSIGNED NULL,
                `event_name` VARCHAR(128) NOT NULL,
                `recipient` VARCHAR(191) NOT NULL,
                `payload` MEDIUMTEXT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT "pending",
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `scheduled_at` DATETIME NOT NULL,
                `processed_at` DATETIME NULL,
                `last_error` TEXT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_queue`),
                KEY `next_jobs` (`status`, `scheduled_at`),
                KEY `recipient` (`recipient`)
            ) ENGINE=' . $engine . ' ' . $charset,
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_log` (
                `id_mailsendvx_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_template` INT UNSIGNED NULL,
                `id_queue` INT UNSIGNED NULL,
                `event_name` VARCHAR(128) NOT NULL,
                `recipient` VARCHAR(191) NULL,
                `status` VARCHAR(32) NOT NULL,
                `payload` MEDIUMTEXT NULL,
                `message` TEXT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_log`),
                KEY `event_status` (`event_name`, `status`),
                KEY `date_add` (`date_add`)
            ) ENGINE=' . $engine . ' ' . $charset,
        ];

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallDatabase(): bool
    {
        $tables = [
            'mailsendvx_log',
            'mailsendvx_queue',
            'mailsendvx_flow',
            'mailsendvx_event',
            'mailsendvx_template',
        ];

        foreach ($tables as $table) {
            if (!Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . bqSQL($table) . '`')) {
                return false;
            }
        }

        return true;
    }

    private function installAdminTabs(): bool
    {
        $idParent = $this->createOrUpdateAdminTab(
            self::ADMIN_PARENT_TAB_CLASS,
            'Mail Send VELOX',
            0,
            'markunread_mailbox'
        );

        if (!$idParent) {
            return false;
        }

        return $this->createOrUpdateAdminTab(
            self::ADMIN_CONFIGURE_TAB_CLASS,
            'Configuracion',
            $idParent,
            'settings'
        ) && $this->createOrUpdateAdminTab(
            self::ADMIN_DASHBOARD_TAB_CLASS,
            'Dashboard',
            $idParent,
            'dashboard'
        );
    }

    private function createOrUpdateAdminTab(string $className, string $name, int $idParent, string $icon): int
    {
        $idTab = (int) Tab::getIdFromClassName($className);
        $tab = $idTab ? new Tab($idTab) : new Tab();
        $tab->active = 1;
        $tab->enabled = 1;
        $tab->class_name = $className;
        $tab->module = $this->name;
        $tab->id_parent = $idParent;
        $tab->icon = $icon;

        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = $name;
        }

        $saved = $idTab ? $tab->update() : $tab->add();

        return $saved ? (int) $tab->id : 0;
    }

    private function uninstallAdminTabs(): bool
    {
        $classes = [
            self::ADMIN_CONFIGURE_TAB_CLASS,
            self::ADMIN_DASHBOARD_TAB_CLASS,
            self::ADMIN_PARENT_TAB_CLASS,
        ];

        foreach ($classes as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);
            if (!$idTab) {
                continue;
            }

            $tab = new Tab($idTab);
            if (Validate::isLoadedObject($tab) && !$tab->delete()) {
                return false;
            }
        }

        return true;
    }

    private function renderConfigurationForm(): string
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = self::SUBMIT_ACTION;
        if (Tools::getValue('controller') === self::ADMIN_CONFIGURE_TAB_CLASS) {
            $helper->currentIndex = AdminController::$currentIndex;
            $helper->token = Tools::getAdminTokenLite(self::ADMIN_CONFIGURE_TAB_CLASS);
        } else {
            $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
            $helper->token = Tools::getAdminTokenLite('AdminModules');
        }
        $helper->fields_value = [
            self::CONFIG_ENABLED => (bool) Configuration::get(self::CONFIG_ENABLED),
            self::CONFIG_DEBUG => (bool) Configuration::get(self::CONFIG_DEBUG),
            self::CONFIG_PROVIDER => (string) Configuration::get(self::CONFIG_PROVIDER),
        ];

        return $helper->generateForm([[
            'form' => [
                'legend' => [
                    'title' => $this->trans('General settings', [], 'Modules.Mailsendvx.Admin'),
                    'icon' => 'icon-envelope',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Enable event capture', [], 'Modules.Mailsendvx.Admin'),
                        'name' => self::CONFIG_ENABLED,
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Provider', [], 'Modules.Mailsendvx.Admin'),
                        'name' => self::CONFIG_PROVIDER,
                        'readonly' => true,
                        'desc' => $this->trans('Phase 0 uses PrestaShop Mail::Send(). The provider layer is ready for SMTP, Brevo or API adapters.', [], 'Modules.Mailsendvx.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Debug mode', [], 'Modules.Mailsendvx.Admin'),
                        'name' => self::CONFIG_DEBUG,
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'debug_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                            ['id' => 'debug_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ]]);
    }

    private function renderStatusPanel(): string
    {
        $this->context->smarty->assign([
            'mailsendvx_templates_count' => (new MailSendVxTemplateRepository())->countAll(),
            'mailsendvx_scheduled_count' => (new MailSendVxQueueRepository())->countByStatus('scheduled'),
            'mailsendvx_recent_logs' => (new MailSendVxLogRepository())->getRecent(8),
            'mailsendvx_dashboard_url' => $this->context->link->getAdminLink(self::ADMIN_DASHBOARD_TAB_CLASS),
            'mailsendvx_cron_token' => (string) Configuration::get(self::CONFIG_CRON_TOKEN),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }
}
