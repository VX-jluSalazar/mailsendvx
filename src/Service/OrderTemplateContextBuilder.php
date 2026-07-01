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
            'event' => [
                'name' => $eventName,
            ],
            'shop' => [
                'id' => (int) $this->context->shop->id,
                'id_lang' => (int) $this->context->language->id,
                'name' => (string) $this->context->shop->name,
                'url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
            ],
            'customer' => [
                'id' => 123,
                'name' => 'Cliente de prueba',
                'firstname' => 'Cliente',
                'lastname' => 'Prueba',
                'email' => 'cliente@example.com',
            ],
            'order' => [
                'id' => 456,
                'reference' => 'VX123456',
                'total' => 89.50,
                'date' => '2026-07-01 10:30:00',
                'formated_date' => '01 de Julio, 2026',
                'status' => 'Pago aceptado',
                'old_status' => 'Pendiente',
                'payment_method' => 'Transferencia bancaria',
                'shipping_method' => 'Envio express',
                'state' => [
                    'id' => 2,
                    'key' => 'payment_accepted',
                    'name' => 'Pago aceptado',
                ],
                'old_state' => [
                    'id' => 1,
                    'key' => 'awaiting_bank_wire_payment',
                    'name' => 'Pendiente',
                ],
                'totals' => [
                    'paid' => 89.50,
                    'products' => 75.00,
                    'shipping' => 9.50,
                    'discounts' => 5.00,
                    'tax' => 10.00,
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
                    'cost' => 9.50,
                    'tracking_url' => 'https://example.com/tracking/VX123456',
                ],
                'products' => [
                    [
                        'id' => 10,
                        'attribute_id' => 0,
                        'name' => 'Camisa Azul',
                        'reference' => 'CA-001',
                        'quantity' => 2,
                        'unit_price' => 25.00,
                        'total_price' => 50.00,
                        'unit_price_tax_excl' => 21.74,
                        'unit_price_tax_incl' => 25.00,
                        'total_price_tax_excl' => 43.48,
                        'total_price_tax_incl' => 50.00,
                        'url' => 'https://example.com/camisa-azul',
                        'image_url' => 'https://via.placeholder.com/120x120.png?text=Camisa+Azul',
                        'attributes' => [
                            [
                                'label' => 'Color',
                                'value' => 'Azul',
                            ],
                        ],
                    ],
                ],
            ],
            'related_products' => [
                [
                    'id' => 21,
                    'name' => 'Zapatos Urbanos',
                    'price' => 59.00,
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

        return $this->enrichOrderStateVariables(
            $this->buildOrderCommonVariables($order, $customer, $currency, $idLang, $idShop),
            ModuleConstants::EVENT_ORDER_STATUS_CHANGED,
            [
                'id' => $newStateId,
                'key' => $this->orderStateEventService->resolveOrderStateKey($newStatus, $newStateName, $newStateId),
                'name' => $newStateName,
            ],
            [
                'id' => $oldStateId,
                'key' => $this->orderStateEventService->resolveOrderStateKey($oldStatus, $oldStateName, $oldStateId),
                'name' => $oldStateName,
            ]
        );
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

        return $this->enrichOrderStateVariables(
            $this->buildOrderCommonVariables($order, $customer, $currency, $idLang, $idShop),
            ModuleConstants::EVENT_ORDER_CREATED,
            [
                'id' => $currentStateId,
                'key' => $this->orderStateEventService->resolveOrderStateKey($currentStatus, $currentStateName, $currentStateId),
                'name' => $currentStateName,
            ]
        );
    }

    private function buildOrderCommonVariables(?Order $order, ?Customer $customer, ?Currency $currency, int $idLang, int $idShop): array
    {
        $billingAddress = $this->getOrderAddress($order, 'id_address_invoice');
        $shippingAddress = $this->getOrderAddress($order, 'id_address_delivery');
        $products = $this->getOrderProducts($order, $currency, $idLang);
        $shipping = $this->getShippingContext($order);
        $numericTotals = $this->getOrderTotalsNumericContext($order);
        $paymentMethod = $this->getPaymentMethod($order);
        $shippingMethod = (string) ($shipping['carrier_name'] ?? '');
        $orderDate = $this->getOrderDate($order);
        $formattedOrderDate = $this->getFormattedOrderDate($order, $idLang);
        $numericOrderTotal = $order instanceof Order && Validate::isLoadedObject($order)
            ? (float) $order->total_paid
            : 0.0;
        $event = [
            'name' => '',
        ];
        $shop = [
            'id' => $idShop,
            'id_lang' => $idLang,
            'name' => $this->getShopName($idShop),
            'url' => $this->context->link->getBaseLink($idShop, true),
        ];
        $customerData = [
            'id' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (int) $customer->id : 0,
            'name' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? trim($customer->firstname . ' ' . $customer->lastname) : '',
            'firstname' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->firstname : '',
            'lastname' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->lastname : '',
            'email' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->email : '',
        ];

        return [
            'related_products' => [],
            'reviews' => [],
            'event' => $event,
            'shop' => $shop,
            'customer' => $customerData,
            'order' => [
                'id' => $order instanceof Order && Validate::isLoadedObject($order) ? (int) $order->id : 0,
                'reference' => $order instanceof Order && Validate::isLoadedObject($order) ? (string) $order->reference : '',
                'total' => $numericOrderTotal,
                'date' => $orderDate,
                'formated_date' => $formattedOrderDate,
                'status' => '',
                'old_status' => '',
                'payment_method' => $paymentMethod,
                'shipping_method' => $shippingMethod,
                'state' => [
                    'id' => 0,
                    'key' => '',
                    'name' => '',
                ],
                'old_state' => [
                    'id' => 0,
                    'key' => '',
                    'name' => '',
                ],
                'totals' => $numericTotals,
                'billing_address' => $billingAddress,
                'shipping_address' => $shippingAddress,
                'shipping' => $shipping,
                'products' => $products,
            ],
        ];
    }

    private function getOrderTotalsNumericContext(?Order $order): array
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return [
                'paid' => 0.0,
                'products' => 0.0,
                'shipping' => 0.0,
                'discounts' => 0.0,
                'tax' => 0.0,
            ];
        }

        return [
            'paid' => (float) $order->total_paid,
            'products' => (float) $order->total_products_wt,
            'shipping' => (float) $order->total_shipping_tax_incl,
            'discounts' => (float) $order->total_discounts_tax_incl,
            'tax' => (float) $order->total_paid_tax_incl - (float) $order->total_paid_tax_excl,
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
    private function getShippingContext(?Order $order): array
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return [
                'carrier_name' => '',
                'cost' => 0.0,
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
            'cost' => (float) $order->total_shipping_tax_incl,
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
                'unit_price' => (float) ($row['unit_price_tax_incl'] ?? 0),
                'total_price' => (float) ($row['total_price_tax_incl'] ?? 0),
                'unit_price_tax_excl' => (float) ($row['unit_price_tax_excl'] ?? 0),
                'unit_price_tax_incl' => (float) ($row['unit_price_tax_incl'] ?? 0),
                'total_price_tax_excl' => (float) ($row['total_price_tax_excl'] ?? 0),
                'total_price_tax_incl' => (float) ($row['total_price_tax_incl'] ?? 0),
                'url' => $idProduct > 0 ? $this->context->link->getProductLink($idProduct, $rewrite, null, null, $idLang, (int) $order->id_shop, $idProductAttribute > 0 ? $idProductAttribute : 0) : '',
                'image_url' => '',
                'attributes' => $this->getProductAttributesContext($row),
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

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function enrichOrderStateVariables(array $context, string $eventName, array $state, array $oldState = []): array
    {
        if (!isset($context['order']) || !is_array($context['order'])) {
            $context['order'] = [];
        }

        if (!isset($context['event']) || !is_array($context['event'])) {
            $context['event'] = [];
        }

        $context['event']['name'] = $eventName;
        $context['order']['status'] = (string) ($state['name'] ?? '');
        $context['order']['old_status'] = (string) ($oldState['name'] ?? '');
        $context['order']['state'] = [
            'id' => (int) ($state['id'] ?? 0),
            'key' => (string) ($state['key'] ?? ''),
            'name' => (string) ($state['name'] ?? ''),
        ];
        $context['order']['old_state'] = [
            'id' => (int) ($oldState['id'] ?? 0),
            'key' => (string) ($oldState['key'] ?? ''),
            'name' => (string) ($oldState['name'] ?? ''),
        ];

        return $context;
    }

    private function getPaymentMethod(?Order $order): string
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return '';
        }

        return (string) ($order->payment ?? '');
    }

    private function getOrderDate(?Order $order): string
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order) || empty($order->date_add)) {
            return '';
        }

        return (string) $order->date_add;
    }

    private function getFormattedOrderDate(?Order $order, int $idLang): string
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order) || empty($order->date_add)) {
            return '';
        }

        return (string) Tools::displayDate((string) $order->date_add, $idLang, true);
    }

    private function getShopName(int $idShop): string
    {
        $shop = new Shop($idShop);

        return Validate::isLoadedObject($shop) ? (string) $shop->name : (string) Configuration::get('PS_SHOP_NAME');
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<int, array<string, string>>
     */
    private function getProductAttributesContext(array $row): array
    {
        $attributesDescription = '';
        if (isset($row['product_attributes']) && is_string($row['product_attributes'])) {
            $attributesDescription = $row['product_attributes'];
        } elseif (isset($row['attributes']) && is_string($row['attributes'])) {
            $attributesDescription = $row['attributes'];
        }

        if ($attributesDescription === '') {
            return [];
        }

        $attributes = [];
        $parts = array_filter(array_map('trim', explode(',', $attributesDescription)));
        foreach ($parts as $part) {
            $attribute = explode(':', $part, 2);
            if (count($attribute) === 2) {
                $attributes[] = [
                    'label' => trim((string) $attribute[0]),
                    'value' => trim((string) $attribute[1]),
                ];
                continue;
            }

            $attributes[] = [
                'label' => 'Valor',
                'value' => trim((string) $part),
            ];
        }

        return $attributes;
    }
}
