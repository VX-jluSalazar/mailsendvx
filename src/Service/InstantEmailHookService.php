<?php

namespace Velox\MailSendVx\Service;

use Address;
use Carrier;
use Configuration;
use Context;
use Country;
use Currency;
use Customer;
use Module;
use Order;
use OrderState;
use PrestaShopLogger;
use Shop;
use State;
use Tools;
use Validate;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Repository\MailSendVxEventRepository;
use Velox\MailSendVx\Repository\MailSendVxLogRepository;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;

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

    /**
     * @var MailSendVxTemplateRepository
     */
    private $templateRepository;

    /**
     * @var MailSendVxEventRepository
     */
    private $eventRepository;

    /**
     * @var MailSendVxLogRepository
     */
    private $logRepository;

    /**
     * @var MailSendVxMailer
     */
    private $mailer;

    public function __construct(
        Context $context,
        OrderStateEventService $orderStateEventService,
        MailSendVxTemplateRepository $templateRepository,
        MailSendVxEventRepository $eventRepository,
        MailSendVxLogRepository $logRepository,
        MailSendVxMailer $mailer
    )
    {
        $this->context = $context;
        $this->orderStateEventService = $orderStateEventService;
        $this->templateRepository = $templateRepository;
        $this->eventRepository = $eventRepository;
        $this->logRepository = $logRepository;
        $this->mailer = $mailer;
    }

    public function handleOrderStatusPostUpdate(array $params, Module $module): void
    {
        $variables = $this->buildOrderStatusVariables($params);
        $this->dispatchOrderStatusEmails($variables, $module);
    }

    public function handleValidateOrder(array $params, Module $module): void
    {
        $variables = $this->buildOrderCreatedVariables($params);
        $this->sendInstantEmail(
            ModuleConstants::EVENT_ORDER_CREATED,
            $variables,
            $variables['customer_email'] ?? null,
            $variables['customer_name'] ?? null,
            $variables['id_lang'] ?? null,
            $variables['id_shop'] ?? null,
            'order',
            $variables['order_id'] ?? null,
            $module
        );
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
        $eventNames = $this->orderStateEventService->buildDispatchEventNames(
            $variables,
            $this->templateRepository,
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
            $this->eventRepository->add(
                $eventName,
                $variables,
                $objectType,
                $objectId !== null ? (string) $objectId : null,
                'captured',
                $idShop
            );

            if (!$recipient || !Validate::isEmail($recipient)) {
                $this->logRepository->add(
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

            $this->mailer->sendEvent(
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

        return array_merge($this->buildOrderCommonVariables($order, $customer, $currency, $idLang, $idShop), [
            'event_name' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED,
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
    private function buildOrderCreatedVariables(array $params): array
    {
        $order = $params['order'] ?? null;
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            $order = isset($params['id_order']) ? new Order((int) $params['id_order']) : null;
        }

        $customer = $params['customer'] ?? null;
        if (!$customer instanceof Customer || !Validate::isLoadedObject($customer)) {
            $customer = $order instanceof Order && Validate::isLoadedObject($order)
                ? new Customer((int) $order->id_customer)
                : null;
        }

        $currency = $params['currency'] ?? null;
        if (!$currency instanceof Currency || !Validate::isLoadedObject($currency)) {
            $currency = $order instanceof Order && Validate::isLoadedObject($order)
                ? new Currency((int) $order->id_currency)
                : null;
        }

        $idLang = $customer instanceof Customer && Validate::isLoadedObject($customer) && $customer->id_lang
            ? (int) $customer->id_lang
            : (int) $this->context->language->id;
        $idShop = $order instanceof Order && Validate::isLoadedObject($order)
            ? (int) $order->id_shop
            : (int) $this->context->shop->id;
        $currentStatus = $params['orderStatus'] ?? null;
        $currentStateId = $this->getOrderStatusId($currentStatus);
        $currentStateName = $this->getOrderStatusName($currentStatus, $idLang);

        return array_merge($this->buildOrderCommonVariables($order, $customer, $currency, $idLang, $idShop), [
            'event_name' => ModuleConstants::EVENT_ORDER_CREATED,
            'order_status' => $currentStateName,
            'order_state_id' => $currentStateId,
            'order_state_key' => $this->orderStateEventService->resolveOrderStateKey($currentStatus, $currentStateName, $currentStateId),
            'order_state_name' => $currentStateName,
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

    private function buildOrderCommonVariables(?Order $order, ?Customer $customer, ?Currency $currency, int $idLang, int $idShop): array
    {
        $billingAddress = $this->getOrderAddress($order, 'id_address_invoice');
        $shippingAddress = $this->getOrderAddress($order, 'id_address_delivery');
        $products = $this->getOrderProducts($order, $currency, $idLang);
        $shipping = $this->getShippingContext($order, $currency);

        return array_merge($this->getCommonVariables($idShop), [
            'id_lang' => $idLang,
            'id_shop' => $idShop,
            'customer_id' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (int) $customer->id : '',
            'customer_name' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? trim($customer->firstname . ' ' . $customer->lastname) : '',
            'customer_firstname' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->firstname : '',
            'customer_lastname' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->lastname : '',
            'customer_email' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->email : '',
            'order_id' => $order instanceof Order && Validate::isLoadedObject($order) ? (int) $order->id : '',
            'order_reference' => $order instanceof Order && Validate::isLoadedObject($order) ? (string) $order->reference : '',
            'order_total' => $order instanceof Order && Validate::isLoadedObject($order) ? Tools::displayPrice((float) $order->total_paid, $currency instanceof Currency ? $currency : null) : '',
            'order_totals' => $this->getOrderTotalsContext($order, $currency),
            'billing_address' => $billingAddress,
            'shipping_address' => $shippingAddress,
            'shipping' => $shipping,
            'products' => $products,
            'related_products' => [],
            'reviews' => [],
            'order' => [
                'id' => $order instanceof Order && Validate::isLoadedObject($order) ? (int) $order->id : 0,
                'reference' => $order instanceof Order && Validate::isLoadedObject($order) ? (string) $order->reference : '',
                'totals' => $this->getOrderTotalsContext($order, $currency),
                'billing_address' => $billingAddress,
                'shipping_address' => $shippingAddress,
                'shipping' => $shipping,
                'products' => $products,
            ],
        ]);
    }

    private function getOrderTotalsContext(?Order $order, ?Currency $currency): array
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return [
                'paid' => '',
                'products' => '',
                'shipping' => '',
                'discounts' => '',
                'tax' => '',
            ];
        }

        return [
            'paid' => Tools::displayPrice((float) $order->total_paid, $currency),
            'products' => Tools::displayPrice((float) $order->total_products_wt, $currency),
            'shipping' => Tools::displayPrice((float) $order->total_shipping_tax_incl, $currency),
            'discounts' => Tools::displayPrice((float) $order->total_discounts_tax_incl, $currency),
            'tax' => Tools::displayPrice((float) $order->total_paid_tax_incl - (float) $order->total_paid_tax_excl, $currency),
        ];
    }

    private function getOrderAddress(?Order $order, string $property): array
    {
        if (
            !$order instanceof Order
            || !Validate::isLoadedObject($order)
            || empty($order->{$property})
        ) {
            return $this->getEmptyAddressContext();
        }

        $address = new Address((int) $order->{$property});
        if (!Validate::isLoadedObject($address)) {
            return $this->getEmptyAddressContext();
        }

        $countryName = '';
        if (!empty($address->id_country)) {
            $country = new Country((int) $address->id_country, (int) $this->context->language->id);
            if (Validate::isLoadedObject($country)) {
                $countryName = (string) $country->name;
            }
        }

        $stateName = '';
        if (!empty($address->id_state)) {
            $state = new State((int) $address->id_state);
            if (Validate::isLoadedObject($state)) {
                $stateName = (string) $state->name;
            }
        }

        $fullName = trim((string) $address->firstname . ' ' . (string) $address->lastname);
        $lines = array_filter([
            $fullName,
            (string) $address->company,
            trim((string) $address->address1),
            trim((string) $address->address2),
            trim((string) $address->city . ' ' . (string) $address->postcode),
            $stateName,
            $countryName,
        ]);

        return [
            'firstname' => (string) $address->firstname,
            'lastname' => (string) $address->lastname,
            'full_name' => $fullName,
            'company' => (string) $address->company,
            'address1' => (string) $address->address1,
            'address2' => (string) $address->address2,
            'city' => (string) $address->city,
            'postcode' => (string) $address->postcode,
            'country' => $countryName,
            'state' => $stateName,
            'phone' => (string) $address->phone,
            'phone_mobile' => (string) $address->phone_mobile,
            'formatted' => implode("\n", $lines),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getEmptyAddressContext(): array
    {
        return [
            'firstname' => '',
            'lastname' => '',
            'full_name' => '',
            'company' => '',
            'address1' => '',
            'address2' => '',
            'city' => '',
            'postcode' => '',
            'country' => '',
            'state' => '',
            'phone' => '',
            'phone_mobile' => '',
            'formatted' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getShippingContext(?Order $order, ?Currency $currency): array
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return [
                'carrier_name' => '',
                'cost' => '',
                'tracking_url' => '',
            ];
        }

        $carrierName = '';
        if (!empty($order->id_carrier)) {
            $carrier = new Carrier((int) $order->id_carrier);
            if (Validate::isLoadedObject($carrier)) {
                $carrierName = (string) $carrier->name;
            }
        }

        return [
            'carrier_name' => $carrierName,
            'cost' => Tools::displayPrice((float) $order->total_shipping_tax_incl, $currency),
            'tracking_url' => '',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getOrderProducts(?Order $order, ?Currency $currency, int $idLang): array
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return [];
        }

        $products = [];
        foreach ($order->getProducts() as $row) {
            $idProduct = (int) ($row['product_id'] ?? $row['id_product'] ?? 0);
            $idProductAttribute = (int) ($row['product_attribute_id'] ?? $row['id_product_attribute'] ?? 0);
            $rewrite = isset($row['link_rewrite']) && is_string($row['link_rewrite']) ? $row['link_rewrite'] : null;

            $products[] = [
                'id' => $idProduct,
                'attribute_id' => $idProductAttribute,
                'name' => (string) ($row['product_name'] ?? ''),
                'reference' => (string) ($row['product_reference'] ?? ''),
                'quantity' => (int) ($row['product_quantity'] ?? 0),
                'unit_price' => Tools::displayPrice((float) ($row['unit_price_tax_incl'] ?? 0), $currency),
                'total_price' => Tools::displayPrice((float) ($row['total_price_tax_incl'] ?? 0), $currency),
                'unit_price_tax_excl' => (float) ($row['unit_price_tax_excl'] ?? 0),
                'unit_price_tax_incl' => (float) ($row['unit_price_tax_incl'] ?? 0),
                'total_price_tax_excl' => (float) ($row['total_price_tax_excl'] ?? 0),
                'total_price_tax_incl' => (float) ($row['total_price_tax_incl'] ?? 0),
                'url' => $idProduct > 0 ? $this->context->link->getProductLink($idProduct, $rewrite, null, null, $idLang, (int) $order->id_shop, $idProductAttribute > 0 ? $idProductAttribute : 0) : '',
                'image_url' => '',
            ];
        }

        return $products;
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
