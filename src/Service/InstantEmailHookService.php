<?php

namespace Velox\MailSendVx\Service;

use Configuration;
use Context;
use Currency;
use Customer;
use MailSendVxEventRepository;
use MailSendVxLogRepository;
use MailSendVxLogger;
use MailSendVxMailer;
use MailSendVxTemplateRepository;
use Module;
use Order;
use OrderState;
use PrestaShopLogger;
use Shop;
use Tools;
use Validate;
use Velox\MailSendVx\ModuleConstants;

class InstantEmailHookService
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var OrderStateEventService
     */
    private $orderStateEventService;

    public function __construct(Context $context, OrderStateEventService $orderStateEventService)
    {
        LegacyClassLoader::load();
        $this->context = $context;
        $this->orderStateEventService = $orderStateEventService;
    }

    public function handleOrderStatusPostUpdate(array $params, Module $module): void
    {
        $variables = $this->buildOrderStatusVariables($params);
        $this->dispatchOrderStatusEmails($variables, $module);
    }

    public function handleCustomerAccountAdd(array $params, Module $module): void
    {
        $variables = $this->buildCustomerVariables($params);
        $this->sendInstantEmail(
            ModuleConstants::EVENT_CUSTOMER_REGISTERED,
            $variables,
            $variables['customer_email'] ?? null,
            $variables['customer_name'] ?? null,
            $variables['id_lang'] ?? null,
            $variables['id_shop'] ?? null,
            'customer',
            $variables['customer_id'] ?? null,
            $module
        );
    }

    public function handleNewsletterRegistrationAfter(array $params, Module $module): void
    {
        $variables = $this->buildNewsletterVariables($params);
        $this->sendInstantEmail(
            ModuleConstants::EVENT_NEWSLETTER_REGISTERED,
            $variables,
            $variables['customer_email'] ?? null,
            $variables['customer_name'] ?? null,
            $variables['id_lang'] ?? null,
            $variables['id_shop'] ?? null,
            'newsletter',
            $variables['customer_email'] ?? null,
            $module
        );
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function dispatchOrderStatusEmails(array $variables, Module $module): void
    {
        $templateRepository = new MailSendVxTemplateRepository();
        $eventNames = $this->orderStateEventService->buildDispatchEventNames(
            $variables,
            $templateRepository,
            ModuleConstants::EVENT_ORDER_STATUS_CHANGED,
            ModuleConstants::EVENT_ORDER_STATUS_LEGACY
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
                $eventVariables['order_id'] ?? null,
                $module
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
        $idLang,
        $idShop,
        ?string $objectType,
        $objectId,
        Module $module
    ): void {
        if (!(bool) Configuration::get(ModuleConstants::CONFIG_ENABLED)) {
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
        } catch (\Throwable $exception) {
            PrestaShopLogger::addLog(
                sprintf('Mail Send VX instant email failed: %s', $exception->getMessage()),
                3,
                null,
                'Module',
                (int) $module->id,
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
            'event_name' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED,
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
            'order_state_key' => $this->orderStateEventService->resolveOrderStateKey($newStatus, $newStateName, $newStateId),
            'order_state_name' => $newStateName,
            'old_order_state_id' => $oldStateId,
            'old_order_state_key' => $this->orderStateEventService->resolveOrderStateKey($oldStatus, $oldStateName, $oldStateId),
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
            'event_name' => ModuleConstants::EVENT_CUSTOMER_REGISTERED,
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
            'event_name' => ModuleConstants::EVENT_NEWSLETTER_REGISTERED,
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

    /**
     * @param mixed $status
     */
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
}
