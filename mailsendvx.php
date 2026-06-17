<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Velox\MailSendVx\Install\ConfigurationInstaller;
use Velox\MailSendVx\Install\DatabaseInstaller;
use Velox\MailSendVx\Install\Installer;
use Velox\MailSendVx\Install\TabInstaller;
use Velox\MailSendVx\Service\OrderStateEventService;
use Velox\MailSendVx\Service\TemplateContentService;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class Mailsendvx extends Module
{
    public const EVENT_ORDER_STATUS_CHANGED = 'order_status_changed';
    public const EVENT_ORDER_STATUS_LEGACY = 'order_status_updated';
    public const EVENT_CUSTOMER_REGISTERED = 'customer_registered';
    public const EVENT_NEWSLETTER_REGISTERED = 'newsletter_registered';
    public const CONFIG_ENABLED = 'MAILSENDVX_ENABLED';
    public const CONFIG_PROVIDER = 'MAILSENDVX_PROVIDER';
    public const CONFIG_DEBUG = 'MAILSENDVX_DEBUG';
    public const CONFIG_CRON_TOKEN = 'MAILSENDVX_CRON_TOKEN';

    private const SUBMIT_ACTION = 'submitMailsendvxConfig';
    private const TEMPLATE_SUBMIT_ACTION = 'submitMailsendvxTemplate';
    private const TEMPLATE_TEST_ACTION = 'submitMailsendvxTest';
    public const ADMIN_PARENT_TAB_CLASS = 'AdminMailsendvx';
    public const ADMIN_CONFIGURE_TAB_CLASS = 'AdminMailsendvxConfigure';
    public const ADMIN_TEMPLATES_TAB_CLASS = 'AdminMailsendvxTemplates';
    public const ADMIN_DASHBOARD_TAB_CLASS = 'AdminMailsendvxDashboard';
    public const ADMIN_CONFIGURE_SECTION_CLASS = 'CONFIGURE';

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

    public function getTemplatesContent(): string
    {
        $this->getInstaller()->ensureRuntimeSchema();

        return $this->handleTemplateActions() . $this->renderTemplatesPanel();
    }

    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $variables = $this->buildOrderStatusVariables($params);
        $this->dispatchOrderStatusEmails($variables);
    }

    public function hookActionCustomerAccountAdd(array $params): void
    {
        $variables = $this->buildCustomerVariables($params);
        $this->sendInstantEmail(
            self::EVENT_CUSTOMER_REGISTERED,
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
            self::EVENT_NEWSLETTER_REGISTERED,
            $variables,
            $variables['customer_email'] ?? null,
            $variables['customer_name'] ?? null,
            $variables['id_lang'] ?? null,
            $variables['id_shop'] ?? null,
            'newsletter',
            $variables['customer_email'] ?? null
        );
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function dispatchOrderStatusEmails(array $variables): void
    {
        $templateRepository = new MailSendVxTemplateRepository();
        $eventNames = $this->getOrderStateEventService()->buildDispatchEventNames(
            $variables,
            $templateRepository,
            self::EVENT_ORDER_STATUS_CHANGED,
            self::EVENT_ORDER_STATUS_LEGACY
        );

        foreach (array_unique($eventNames) as $eventName) {
            $eventVariables = $variables;
            $eventVariables['event_name'] = $eventName;
            $this->sendInstantEmail(
                $eventName,
                $eventVariables,
                $eventVariables['customer_email'] ?? null,
                $eventVariables['customer_name'] ?? null,
                $eventVariables['id_lang'] ?? null,
                $eventVariables['id_shop'] ?? null,
                'order',
                $eventVariables['order_id'] ?? null
            );
        }
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
        $newStateId = $this->getOrderStatusId($newStatus);
        $oldStateId = $this->getOrderStatusId($oldStatus);
        $newStateName = $this->getOrderStatusName($newStatus, $idLang);
        $oldStateName = $this->getOrderStatusName($oldStatus, $idLang);

        return array_merge($this->getCommonVariables($idShop), [
            'event_name' => self::EVENT_ORDER_STATUS_CHANGED,
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
            'order_status' => $newStateName,
            'old_order_status' => $oldStateName,
            'order_state_id' => $newStateId,
            'order_state_key' => $this->getOrderStateEventService()->resolveOrderStateKey($newStatus, $newStateName, $newStateId),
            'order_state_name' => $newStateName,
            'old_order_state_id' => $oldStateId,
            'old_order_state_key' => $this->getOrderStateEventService()->resolveOrderStateKey($oldStatus, $oldStateName, $oldStateId),
            'old_order_state_name' => $oldStateName,
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
            'event_name' => self::EVENT_CUSTOMER_REGISTERED,
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
            'event_name' => self::EVENT_NEWSLETTER_REGISTERED,
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

    /**
     * @param mixed $status
     */
    private function getOrderStatusId($status): int
    {
        if ($status instanceof OrderState && Validate::isLoadedObject($status)) {
            return (int) $status->id;
        }

        return 0;
    }

    private function handleTemplateActions(): string
    {
        $output = '';
        $repository = new MailSendVxTemplateRepository();

        $flashMessage = $this->getTemplateFlashMessage();
        if ($flashMessage !== '') {
            $output .= $flashMessage;
        }

        if (Tools::isSubmit(self::TEMPLATE_SUBMIT_ACTION)) {
            $idTemplate = (int) Tools::getValue('id_mailsendvx_template');
            $eventName = (string) Tools::getValue('event_name');
            $name = trim((string) Tools::getValue('template_name'));
            $subject = trim((string) Tools::getValue('subject'));
            $htmlContent = (string) Tools::getValue('html_content');
            $textContent = trim((string) Tools::getValue('text_content'));

            if ($textContent === '') {
                $textContent = $this->generateTextContentFromHtml($htmlContent);
            }

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
                    'html_content' => $htmlContent,
                    'text_content' => $textContent,
                    'json_design' => null,
                    'provider' => 'prestashop_mail',
                    'active' => (bool) Tools::getValue('active'),
                ], $idTemplate ?: null);

                if ($saved) {
                    $this->redirectTemplatesPage(['mailsendvx_notice' => 'saved']);
                }

                $output .= $this->displayError($this->trans('Template could not be saved.', [], 'Modules.Mailsendvx.Admin'));
            }
        }

        if (Tools::getValue('mailsendvx_delete_template')) {
            $deleted = $repository->delete((int) Tools::getValue('mailsendvx_delete_template'));
            if ($deleted) {
                $this->redirectTemplatesPage(['mailsendvx_notice' => 'deleted']);
            }

            $output .= $this->displayError($this->trans('Template could not be deleted.', [], 'Modules.Mailsendvx.Admin'));
        }

        if (Tools::isSubmit(self::TEMPLATE_TEST_ACTION)) {
            $testResult = $this->sendTemplateTest($repository);
            if ($testResult === true) {
                $this->redirectTemplatesPage(['mailsendvx_notice' => 'test_sent']);
            }

            $output .= is_string($testResult)
                ? $testResult
                : $this->displayError($this->trans('Test email was not sent. Check logs for details.', [], 'Modules.Mailsendvx.Admin'));
        }

        return $output;
    }

    /**
     * @return bool|string
     */
    private function sendTemplateTest(MailSendVxTemplateRepository $repository)
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
            ? true
            : $this->displayError($this->trans('Test email was not sent. Check logs for details.', [], 'Modules.Mailsendvx.Admin'));
    }

    /**
     * @return array<string, string>
     */
    private function getSupportedEvents(): array
    {
        return array_merge(
            $this->getBaseSupportedEvents(),
            $this->getOrderStateEventService()->getSupportedEvents([
                'generic' => self::EVENT_ORDER_STATUS_CHANGED,
            ])
        );
    }

    /**
     * @return array<string, string>
     */
    private function getBaseSupportedEvents(): array
    {
        return [
            self::EVENT_ORDER_STATUS_CHANGED => 'Cambio de estado de pedido',
            self::EVENT_ORDER_STATUS_LEGACY => 'Cambio de estado de pedido (legado)',
            self::EVENT_CUSTOMER_REGISTERED => 'Registro de cliente',
            self::EVENT_NEWSLETTER_REGISTERED => 'Suscripcion newsletter',
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

        $eventName = $template ? (string) $template['event_name'] : self::EVENT_ORDER_STATUS_CHANGED;

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
            'order_state_id' => 2,
            'order_state_key' => 'payment',
            'order_state_name' => 'Pago aceptado',
            'old_order_state_id' => 1,
            'old_order_state_key' => 'awaiting_bank_wire_payment',
            'old_order_state_name' => 'Pendiente',
            'newsletter_action' => 'subscribe',
        ]);

        return $variables;
    }

    private function getDefaultHtmlContent(string $eventName): string
    {
        return $this->getTemplateContentService()->getDefaultHtmlContent(
            $eventName,
            self::EVENT_CUSTOMER_REGISTERED,
            self::EVENT_NEWSLETTER_REGISTERED
        );
    }

    private function getDefaultTextContent(string $eventName): string
    {
        return $this->getTemplateContentService()->getDefaultTextContent(
            $eventName,
            self::EVENT_CUSTOMER_REGISTERED,
            self::EVENT_NEWSLETTER_REGISTERED
        );
    }

    private function generateTextContentFromHtml(string $htmlContent): string
    {
        return $this->getTemplateContentService()->generateTextContentFromHtml($htmlContent);
    }

    private function getTemplateFlashMessage(): string
    {
        $notice = (string) Tools::getValue('mailsendvx_notice');
        if ($notice === 'saved') {
            return $this->displayConfirmation($this->trans('Template saved.', [], 'Admin.Notifications.Success'));
        }

        if ($notice === 'deleted') {
            return $this->displayConfirmation($this->trans('Template deleted.', [], 'Admin.Notifications.Success'));
        }

        if ($notice === 'test_sent') {
            return $this->displayConfirmation($this->trans('Test email sent.', [], 'Admin.Notifications.Success'));
        }

        return '';
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

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    private function redirectTemplatesPage(array $params = []): void
    {
        $url = $this->context->link->getAdminLink(self::ADMIN_TEMPLATES_TAB_CLASS);
        foreach ($params as $key => $value) {
            $url .= '&' . urlencode($key) . '=' . urlencode($value);
        }

        Tools::redirectAdmin($url);
    }

    private function getInstaller(): Installer
    {
        return new Installer(
            new ConfigurationInstaller(),
            new DatabaseInstaller(),
            new TabInstaller()
        );
    }

    private function getOrderStateEventService(): OrderStateEventService
    {
        return new OrderStateEventService($this->context);
    }

    private function getTemplateContentService(): TemplateContentService
    {
        return new TemplateContentService();
    }
}
