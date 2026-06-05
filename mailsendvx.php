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
    private const TEMPLATE_SUBMIT_ACTION = 'submitMailsendvxTemplate';
    private const TEMPLATE_TEST_ACTION = 'submitMailsendvxTest';
    private const ADMIN_PARENT_TAB_CLASS = 'AdminMailsendvx';
    private const ADMIN_CONFIGURE_TAB_CLASS = 'AdminMailsendvxConfigure';
    private const ADMIN_TEMPLATES_TAB_CLASS = 'AdminMailsendvxTemplates';
    private const ADMIN_DASHBOARD_TAB_CLASS = 'AdminMailsendvxDashboard';
    private const ADMIN_CONFIGURE_SECTION_CLASS = 'CONFIGURE';

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

    public function getTemplatesContent(): string
    {
        $this->installDatabase();
        $this->installDefaultConfiguration(false);
        $this->installAdminTabs();

        return $this->handleTemplateActions() . $this->renderTemplatesPanel();
    }

    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $variables = $this->buildOrderStatusVariables($params);
        $this->sendInstantEmail(
            'order_status_updated',
            $variables,
            $variables['customer_email'] ?? null,
            $variables['customer_name'] ?? null,
            $variables['id_lang'] ?? null,
            $variables['id_shop'] ?? null,
            'order',
            $variables['order_id'] ?? null
        );
    }

    public function hookActionCustomerAccountAdd(array $params): void
    {
        $variables = $this->buildCustomerVariables($params);
        $this->sendInstantEmail(
            'customer_registered',
            $variables,
            $variables['customer_email'] ?? null,
            $variables['customer_name'] ?? null,
            $variables['id_lang'] ?? null,
            $variables['id_shop'] ?? null,
            'customer',
            $variables['customer_id'] ?? null
        );
    }

    public function hookActionNewsletterRegistrationAfter(array $params): void
    {
        $variables = $this->buildNewsletterVariables($params);
        $this->sendInstantEmail(
            'newsletter_registered',
            $variables,
            $variables['customer_email'] ?? null,
            $variables['customer_name'] ?? null,
            $variables['id_lang'] ?? null,
            $variables['id_shop'] ?? null,
            'newsletter',
            $variables['customer_email'] ?? null
        );
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

    /**
     * @param array<string, mixed> $variables
     * @param int|string|null $objectId
     */
    private function sendInstantEmail(
        string $eventName,
        array $variables,
        ?string $recipient,
        ?string $recipientName,
        $idLang = null,
        $idShop = null,
        ?string $objectType = null,
        $objectId = null
    ): void {
        if (!(bool) Configuration::get(self::CONFIG_ENABLED)) {
            return;
        }

        $idLang = (int) ($idLang ?: $this->context->language->id);
        $idShop = (int) ($idShop ?: $this->context->shop->id);

        try {
            (new MailSendVxEventRepository())->add(
                $eventName,
                $variables,
                $objectType,
                $objectId !== null ? (string) $objectId : null,
                'captured',
                $idShop
            );

            if (!$recipient || !Validate::isEmail($recipient)) {
                (new MailSendVxLogRepository())->add(
                    $eventName,
                    'skipped',
                    $recipient,
                    null,
                    null,
                    $variables,
                    'No valid recipient found.',
                    $idShop
                );

                return;
            }

            (new MailSendVxMailer())->sendEvent(
                $eventName,
                $recipient,
                $recipientName,
                $variables,
                $idLang,
                $idShop
            );
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                sprintf('Mail Send VX instant email failed: %s', $exception->getMessage()),
                3,
                null,
                'Module',
                (int) $this->id,
                true
            );
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function buildOrderStatusVariables(array $params): array
    {
        $order = isset($params['id_order']) ? new Order((int) $params['id_order']) : null;
        $customer = $order && Validate::isLoadedObject($order) ? new Customer((int) $order->id_customer) : null;
        $currency = $order && Validate::isLoadedObject($order) ? new Currency((int) $order->id_currency) : null;
        $newStatus = $params['newOrderStatus'] ?? null;
        $oldStatus = $params['oldOrderStatus'] ?? null;
        $idLang = $customer && Validate::isLoadedObject($customer) ? (int) $customer->id_lang : (int) $this->context->language->id;
        $idShop = $order && Validate::isLoadedObject($order) ? (int) $order->id_shop : (int) $this->context->shop->id;

        return array_merge($this->getCommonVariables($idShop), [
            'event_name' => 'order_status_updated',
            'id_lang' => $idLang,
            'id_shop' => $idShop,
            'customer_id' => $customer && Validate::isLoadedObject($customer) ? (int) $customer->id : '',
            'customer_name' => $customer && Validate::isLoadedObject($customer) ? trim($customer->firstname . ' ' . $customer->lastname) : '',
            'customer_firstname' => $customer && Validate::isLoadedObject($customer) ? (string) $customer->firstname : '',
            'customer_lastname' => $customer && Validate::isLoadedObject($customer) ? (string) $customer->lastname : '',
            'customer_email' => $customer && Validate::isLoadedObject($customer) ? (string) $customer->email : '',
            'order_id' => $order && Validate::isLoadedObject($order) ? (int) $order->id : '',
            'order_reference' => $order && Validate::isLoadedObject($order) ? (string) $order->reference : '',
            'order_total' => $order && Validate::isLoadedObject($order) ? Tools::displayPrice((float) $order->total_paid, $currency) : '',
            'order_status' => $this->getOrderStatusName($newStatus, $idLang),
            'old_order_status' => $this->getOrderStatusName($oldStatus, $idLang),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function buildCustomerVariables(array $params): array
    {
        $customer = $params['newCustomer'] ?? null;
        $idLang = $customer instanceof Customer && Validate::isLoadedObject($customer) && $customer->id_lang
            ? (int) $customer->id_lang
            : (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        return array_merge($this->getCommonVariables($idShop), [
            'event_name' => 'customer_registered',
            'id_lang' => $idLang,
            'id_shop' => $idShop,
            'customer_id' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (int) $customer->id : '',
            'customer_name' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? trim($customer->firstname . ' ' . $customer->lastname) : '',
            'customer_firstname' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->firstname : '',
            'customer_lastname' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->lastname : '',
            'customer_email' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->email : '',
        ]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function buildNewsletterVariables(array $params): array
    {
        $email = isset($params['email']) ? (string) $params['email'] : '';
        $idShop = (int) $this->context->shop->id;

        return array_merge($this->getCommonVariables($idShop), [
            'event_name' => 'newsletter_registered',
            'id_lang' => (int) $this->context->language->id,
            'id_shop' => $idShop,
            'customer_name' => '',
            'customer_email' => $email,
            'newsletter_action' => isset($params['action']) ? (string) $params['action'] : '',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getCommonVariables(int $idShop): array
    {
        $shop = new Shop($idShop);

        return [
            'shop_name' => Validate::isLoadedObject($shop) ? (string) $shop->name : (string) Configuration::get('PS_SHOP_NAME'),
            'shop_url' => $this->context->link->getBaseLink($idShop, true),
        ];
    }

    private function getOrderStatusName($status, int $idLang): string
    {
        if ($status instanceof OrderState && Validate::isLoadedObject($status)) {
            if (is_array($status->name) && isset($status->name[$idLang])) {
                return (string) $status->name[$idLang];
            }

            return is_string($status->name) ? $status->name : '';
        }

        return '';
    }

    private function handleTemplateActions(): string
    {
        $output = '';
        $repository = new MailSendVxTemplateRepository();

        if (Tools::isSubmit(self::TEMPLATE_SUBMIT_ACTION)) {
            $idTemplate = (int) Tools::getValue('id_mailsendvx_template');
            $eventName = (string) Tools::getValue('event_name');
            $name = trim((string) Tools::getValue('template_name'));
            $subject = trim((string) Tools::getValue('subject'));

            if (!array_key_exists($eventName, $this->getSupportedEvents())) {
                $output .= $this->displayError($this->trans('Invalid event.', [], 'Modules.Mailsendvx.Admin'));
            } elseif ($name === '' || $subject === '') {
                $output .= $this->displayError($this->trans('Template name and subject are required.', [], 'Modules.Mailsendvx.Admin'));
            } else {
                $saved = $repository->save([
                    'id_shop' => (int) Tools::getValue('id_shop', (int) $this->context->shop->id),
                    'id_lang' => (int) Tools::getValue('id_lang', (int) $this->context->language->id),
                    'event_name' => $eventName,
                    'name' => $name,
                    'subject' => $subject,
                    'mail_template' => trim((string) Tools::getValue('mail_template', 'mailsendvx_default')) ?: 'mailsendvx_default',
                    'html_content' => (string) Tools::getValue('html_content'),
                    'text_content' => (string) Tools::getValue('text_content'),
                    'json_design' => null,
                    'provider' => 'prestashop_mail',
                    'active' => (bool) Tools::getValue('active'),
                ], $idTemplate ?: null);

                $output .= $saved
                    ? $this->displayConfirmation($this->trans('Template saved.', [], 'Admin.Notifications.Success'))
                    : $this->displayError($this->trans('Template could not be saved.', [], 'Modules.Mailsendvx.Admin'));
            }
        }

        if (Tools::getValue('mailsendvx_delete_template')) {
            $deleted = $repository->delete((int) Tools::getValue('mailsendvx_delete_template'));
            $output .= $deleted
                ? $this->displayConfirmation($this->trans('Template deleted.', [], 'Admin.Notifications.Success'))
                : $this->displayError($this->trans('Template could not be deleted.', [], 'Modules.Mailsendvx.Admin'));
        }

        if (Tools::isSubmit(self::TEMPLATE_TEST_ACTION)) {
            $output .= $this->sendTemplateTest($repository);
        }

        return $output;
    }

    private function sendTemplateTest(MailSendVxTemplateRepository $repository): string
    {
        $idTemplate = (int) Tools::getValue('test_id_mailsendvx_template');
        $recipient = trim((string) Tools::getValue('test_email'));
        $template = $repository->findById($idTemplate);

        if (!$template) {
            return $this->displayError($this->trans('Template not found.', [], 'Modules.Mailsendvx.Admin'));
        }

        if (!Validate::isEmail($recipient)) {
            return $this->displayError($this->trans('Invalid test email.', [], 'Modules.Mailsendvx.Admin'));
        }

        $sent = (new MailSendVxMailer())->sendTemplate(
            $template,
            $recipient,
            null,
            $this->getSampleVariables((string) $template['event_name']),
            (int) ($template['id_lang'] ?: $this->context->language->id),
            (int) ($template['id_shop'] ?: $this->context->shop->id)
        );

        return $sent
            ? $this->displayConfirmation($this->trans('Test email sent.', [], 'Admin.Notifications.Success'))
            : $this->displayError($this->trans('Test email was not sent. Check logs for details.', [], 'Modules.Mailsendvx.Admin'));
    }

    /**
     * @return array<string, string>
     */
    private function getSupportedEvents(): array
    {
        return [
            'order_status_updated' => 'Cambio de estado de pedido',
            'customer_registered' => 'Registro de cliente',
            'newsletter_registered' => 'Suscripcion newsletter',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getTemplateFormValues(): array
    {
        $template = null;
        if ((int) Tools::getValue('mailsendvx_edit_template')) {
            $template = (new MailSendVxTemplateRepository())->findById((int) Tools::getValue('mailsendvx_edit_template'));
        }

        $eventName = $template ? (string) $template['event_name'] : 'order_status_updated';

        return [
            'id_mailsendvx_template' => $template ? (int) $template['id_mailsendvx_template'] : 0,
            'id_shop' => $template ? (int) $template['id_shop'] : (int) $this->context->shop->id,
            'id_lang' => $template ? (int) $template['id_lang'] : (int) $this->context->language->id,
            'event_name' => $eventName,
            'name' => $template ? (string) $template['name'] : '',
            'subject' => $template ? (string) $template['subject'] : '',
            'mail_template' => $template ? (string) $template['mail_template'] : 'mailsendvx_default',
            'html_content' => $template ? (string) $template['html_content'] : $this->getDefaultHtmlContent($eventName),
            'text_content' => $template ? (string) $template['text_content'] : $this->getDefaultTextContent($eventName),
            'active' => $template ? (int) $template['active'] : 1,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPreviewData(): ?array
    {
        $idTemplate = (int) Tools::getValue('mailsendvx_preview_template');
        if (!$idTemplate) {
            return null;
        }

        $template = (new MailSendVxTemplateRepository())->findById($idTemplate);
        if (!$template) {
            return null;
        }

        $variables = $this->getSampleVariables((string) $template['event_name']);
        $renderer = new MailSendVxVariableRenderer();

        return [
            'name' => (string) $template['name'],
            'subject' => $renderer->render((string) $template['subject'], $variables),
            'html' => $renderer->render((string) $template['html_content'], $variables),
            'text' => $renderer->render((string) $template['text_content'], $variables),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSampleVariables(string $eventName): array
    {
        $variables = array_merge($this->getCommonVariables((int) $this->context->shop->id), [
            'event_name' => $eventName,
            'id_lang' => (int) $this->context->language->id,
            'id_shop' => (int) $this->context->shop->id,
            'customer_id' => 123,
            'customer_name' => 'Cliente de prueba',
            'customer_firstname' => 'Cliente',
            'customer_lastname' => 'Prueba',
            'customer_email' => 'cliente@example.com',
            'order_id' => 456,
            'order_reference' => 'VX123456',
            'order_total' => '$89.50',
            'order_status' => 'Pago aceptado',
            'old_order_status' => 'Pendiente',
            'newsletter_action' => 'subscribe',
        ]);

        return $variables;
    }

    private function getDefaultHtmlContent(string $eventName): string
    {
        if ($eventName === 'customer_registered') {
            return '<p>Hola {customer_name},</p><p>Bienvenido a {shop_name}. Gracias por crear tu cuenta.</p><p><a href="{shop_url}">Visitar la tienda</a></p>';
        }

        if ($eventName === 'newsletter_registered') {
            return '<p>Hola,</p><p>Gracias por suscribirte al newsletter de {shop_name}.</p><p><a href="{shop_url}">Visitar la tienda</a></p>';
        }

        return '<p>Hola {customer_name},</p><p>Tu pedido {order_reference} cambio al estado: <strong>{order_status}</strong>.</p><p>Total: {order_total}</p><p><a href="{shop_url}">Visitar la tienda</a></p>';
    }

    private function getDefaultTextContent(string $eventName): string
    {
        if ($eventName === 'customer_registered') {
            return "Hola {customer_name},\n\nBienvenido a {shop_name}. Gracias por crear tu cuenta.\n\n{shop_url}";
        }

        if ($eventName === 'newsletter_registered') {
            return "Hola,\n\nGracias por suscribirte al newsletter de {shop_name}.\n\n{shop_url}";
        }

        return "Hola {customer_name},\n\nTu pedido {order_reference} cambio al estado: {order_status}.\nTotal: {order_total}\n\n{shop_url}";
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
            $this->getConfigureSectionTabId(),
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
            self::ADMIN_TEMPLATES_TAB_CLASS,
            'Templates',
            $idParent,
            'mail'
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

    private function getConfigureSectionTabId(): int
    {
        return (int) Tab::getIdFromClassName(self::ADMIN_CONFIGURE_SECTION_CLASS);
    }

    private function uninstallAdminTabs(): bool
    {
        $classes = [
            self::ADMIN_CONFIGURE_TAB_CLASS,
            self::ADMIN_TEMPLATES_TAB_CLASS,
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
            'mailsendvx_templates_url' => $this->context->link->getAdminLink(self::ADMIN_TEMPLATES_TAB_CLASS),
            'mailsendvx_cron_token' => (string) Configuration::get(self::CONFIG_CRON_TOKEN),
            'mailsendvx_mail_diagnostics' => $this->getMailDiagnostics(),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    private function renderTemplatesPanel(): string
    {
        $templateRepository = new MailSendVxTemplateRepository();
        $templates = $templateRepository->getAll();
        $templatesUrl = $this->context->link->getAdminLink(self::ADMIN_TEMPLATES_TAB_CLASS);

        $this->context->smarty->assign([
            'mailsendvx_templates' => $templates,
            'mailsendvx_events' => $this->getSupportedEvents(),
            'mailsendvx_template_form' => $this->getTemplateFormValues(),
            'mailsendvx_preview' => $this->getPreviewData(),
            'mailsendvx_configure_url' => $templatesUrl,
            'mailsendvx_languages' => Language::getLanguages(false),
            'mailsendvx_current_shop_id' => (int) $this->context->shop->id,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/templates.tpl');
    }

    /**
     * @return array<string, string>
     */
    private function getMailDiagnostics(): array
    {
        $method = (int) Configuration::get('PS_MAIL_METHOD');
        $labels = [
            1 => 'PHP sendmail',
            2 => 'SMTP',
            3 => 'Disabled',
        ];
        $warning = '';
        if ($method === Mail::METHOD_DISABLE) {
            $warning = 'Emails are disabled in PrestaShop. Mail::Send() can return true without sending.';
        } elseif ($method === Mail::METHOD_SMTP && (!Configuration::get('PS_MAIL_SERVER') || !Configuration::get('PS_MAIL_SMTP_PORT'))) {
            $warning = 'SMTP is selected but server or port is empty.';
        } elseif ($method !== Mail::METHOD_SMTP) {
            $warning = 'Using local sendmail. In local/dev environments it may accept emails without delivering them externally.';
        }

        return [
            'method' => $labels[$method] ?? ('Unknown (' . $method . ')'),
            'server' => (string) Configuration::get('PS_MAIL_SERVER'),
            'port' => (string) Configuration::get('PS_MAIL_SMTP_PORT'),
            'encryption' => (string) Configuration::get('PS_MAIL_SMTP_ENCRYPTION'),
            'user' => (string) Configuration::get('PS_MAIL_USER'),
            'shop_email' => (string) Configuration::get('PS_SHOP_EMAIL'),
            'log_emails' => Configuration::get('PS_LOG_EMAILS') ? 'Yes' : 'No',
            'warning' => $warning,
        ];
    }
}
