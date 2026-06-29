<?php

namespace Velox\MailSendVx\Service;

use Address;
use Carrier;
use Configuration;
use Context;
use Country;
use Currency;
use Customer;
use Order;
use OrderState;
use Shop;
use State;
use Tools;
use Validate;
use Velox\MailSendVx\ModuleConstants;

class OrderTemplateContextBuilder implements DomainTemplateContextBuilderInterface
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
        $this->context = $context;
        $this->orderStateEventService = $orderStateEventService;
    }

    public function supportsEvent(string $eventName): bool
    {
        return $eventName === ModuleConstants::EVENT_ORDER_CREATED
            || $eventName === ModuleConstants::EVENT_ORDER_STATUS_CHANGED
            || strpos($eventName, ModuleConstants::EVENT_ORDER_STATUS_CHANGED . '_') === 0
            || $eventName === ModuleConstants::EVENT_ORDER_STATUS_LEGACY;
    }

    public function buildHookContext(string $eventName, array $params): array
    {
        if ($eventName === ModuleConstants::EVENT_ORDER_CREATED) {
            return $this->buildOrderCreatedContext($params);
        }

        return $this->buildOrderStatusContext($params);
    }

    public function buildSampleContext(string $eventName): array
    {
        $context = [
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
            'order_state_key' => 'payment_accepted',
            'order_state_name' => 'Pago aceptado',
            'old_order_state_id' => 1,
            'old_order_state_key' => 'awaiting_bank_wire_payment',
            'old_order_state_name' => 'Pendiente',
            'order_totals' => [
                'paid' => '$89.50',
                'products' => '$75.00',
                'shipping' => '$9.50',
                'discounts' => '$5.00',
                'tax' => '$10.00',
            ],
            'billing_address' => [
                'firstname' => 'Cliente',
                'lastname' => 'Prueba',
                'full_name' => 'Cliente Prueba',
                'company' => 'Velox Labs',
                'address1' => 'Av. Siempre Viva 123',
                'address2' => 'Depto 4B',
                'city' => 'Guayaquil',
                'postcode' => '090101',
                'country' => 'Ecuador',
                'state' => 'Guayas',
                'phone' => '+593999999999',
                'phone_mobile' => '+593988888888',
                'formatted' => "Cliente Prueba\nAv. Siempre Viva 123\nDepto 4B\nGuayaquil 090101\nEcuador",
            ],
            'shipping_address' => [
                'firstname' => 'Cliente',
                'lastname' => 'Prueba',
                'full_name' => 'Cliente Prueba',
                'company' => '',
                'address1' => 'Calle Comercio 456',
                'address2' => '',
                'city' => 'Samborondon',
                'postcode' => '092301',
                'country' => 'Ecuador',
                'state' => 'Guayas',
                'phone' => '+593977777777',
                'phone_mobile' => '',
                'formatted' => "Cliente Prueba\nCalle Comercio 456\nSamborondon 092301\nEcuador",
            ],
            'shipping' => [
                'carrier_name' => 'Envio express',
                'cost' => '$9.50',
                'tracking_url' => 'https://example.com/tracking/VX123456',
            ],
            'products' => [
                [
                    'id' => 10,
                    'attribute_id' => 0,
                    'name' => 'Camisa Azul',
                    'reference' => 'CA-001',
                    'quantity' => 2,
                    'unit_price' => '$25.00',
                    'total_price' => '$50.00',
                    'url' => 'https://example.com/camisa-azul',
                    'image_url' => 'https://via.placeholder.com/120x120.png?text=Camisa+Azul',
                ],
                [
                    'id' => 11,
                    'attribute_id' => 0,
                    'name' => 'Pantalon Negro',
                    'reference' => 'PN-010',
                    'quantity' => 1,
                    'unit_price' => '$25.00',
                    'total_price' => '$25.00',
                    'url' => 'https://example.com/pantalon-negro',
                    'image_url' => 'https://via.placeholder.com/120x120.png?text=Pantalon+Negro',
                ],
            ],
            'related_products' => [
                [
                    'id' => 21,
                    'name' => 'Zapatos Urbanos',
                    'price' => '$59.00',
                    'url' => 'https://example.com/zapatos-urbanos',
                    'image_url' => 'https://via.placeholder.com/120x120.png?text=Zapatos',
                ],
            ],
            'reviews' => [
                [
                    'author' => 'Maria',
                    'rating' => 5,
                    'title' => 'Excelente compra',
                    'content' => 'Entrega rapida y producto en perfecto estado.',
                ],
            ],
            'shop_name' => (string) $this->context->shop->name,
            'shop_url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
        ];

        $context['order'] = [
            'id' => $context['order_id'],
            'reference' => $context['order_reference'],
            'totals' => $context['order_totals'],
            'billing_address' => $context['billing_address'],
            'shipping_address' => $context['shipping_address'],
            'shipping' => $context['shipping'],
            'products' => $context['products'],
        ];

        return $context;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function buildOrderStatusContext(array $params): array
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
    private function buildOrderCreatedContext(array $params): array
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

    private function buildOrderCommonVariables(?Order $order, ?Customer $customer, ?Currency $currency, int $idLang, int $idShop): array
    {
        $billingAddress = $this->getOrderAddress($order, 'id_address_invoice');
        $shippingAddress = $this->getOrderAddress($order, 'id_address_delivery');
        $products = $this->getOrderProducts($order, $currency, $idLang);
        $shipping = $this->getShippingContext($order, $currency);
        $totals = $this->getOrderTotalsContext($order, $currency);

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
            'order_totals' => $totals,
            'billing_address' => $billingAddress,
            'shipping_address' => $shippingAddress,
            'shipping' => $shipping,
            'products' => $products,
            'related_products' => [],
            'reviews' => [],
            'order' => [
                'id' => $order instanceof Order && Validate::isLoadedObject($order) ? (int) $order->id : 0,
                'reference' => $order instanceof Order && Validate::isLoadedObject($order) ? (string) $order->reference : '',
                'totals' => $totals,
                'billing_address' => $billingAddress,
                'shipping_address' => $shippingAddress,
                'shipping' => $shipping,
                'products' => $products,
            ],
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
