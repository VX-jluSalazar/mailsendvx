<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Context;
use Currency;
use Customer;
use Order;
use OrderState;
use Validate;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Service\Event\OrderStateEventService;

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

    /**
     * @var EventContextSegmentBuilder
     */
    private $eventSegmentBuilder;

    /**
     * @var ShopContextSegmentBuilder
     */
    private $shopSegmentBuilder;

    /**
     * @var CustomerContextSegmentBuilder
     */
    private $customerSegmentBuilder;

    /**
     * @var OrderContextSegmentBuilder
     */
    private $orderSegmentBuilder;

    /**
     * @var ProductsContextBuilder
     */
    private $productsContextBuilder;

    /**
     * @var RelatedProductsContextProvider
     */
    private $relatedProductsContextProvider;

    /**
     * @var ReviewsContextProvider
     */
    private $reviewsContextProvider;

    public function __construct(
        Context $context,
        OrderStateEventService $orderStateEventService,
        EventContextSegmentBuilder $eventSegmentBuilder,
        ShopContextSegmentBuilder $shopSegmentBuilder,
        CustomerContextSegmentBuilder $customerSegmentBuilder,
        OrderContextSegmentBuilder $orderSegmentBuilder,
        ProductsContextBuilder $productsContextBuilder,
        RelatedProductsContextProvider $relatedProductsContextProvider,
        ReviewsContextProvider $reviewsContextProvider
    ) {
        $this->context = $context;
        $this->orderStateEventService = $orderStateEventService;
        $this->eventSegmentBuilder = $eventSegmentBuilder;
        $this->shopSegmentBuilder = $shopSegmentBuilder;
        $this->customerSegmentBuilder = $customerSegmentBuilder;
        $this->orderSegmentBuilder = $orderSegmentBuilder;
        $this->productsContextBuilder = $productsContextBuilder;
        $this->relatedProductsContextProvider = $relatedProductsContextProvider;
        $this->reviewsContextProvider = $reviewsContextProvider;
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

        return $this->buildOrderStatusContext($params, $eventName);
    }

    public function buildSampleContext(string $eventName): array
    {
        $fixturePath = dirname(__DIR__, 2) . '/.agents/fixtures/order.json';
        if (is_file($fixturePath)) {
            $decoded = json_decode((string) file_get_contents($fixturePath), true);
            if (is_array($decoded)) {
                $decoded['event']['name'] = $eventName;
                $decoded['shop']['id'] = (int) ($decoded['shop']['id'] ?? $this->context->shop->id);
                $decoded['shop']['id_lang'] = (int) ($decoded['shop']['id_lang'] ?? $this->context->language->id);
                $decoded['shop']['name'] = (string) ($decoded['shop']['name'] ?? $this->context->shop->name);
                $decoded['shop']['url'] = (string) ($decoded['shop']['url'] ?? $this->context->link->getBaseLink((int) $decoded['shop']['id'], true));

                return $decoded;
            }
        }

        return [
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
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function buildOrderStatusContext(array $params, string $eventName): array
    {
        $order = isset($params['id_order']) ? new Order((int) $params['id_order']) : null;
        $customer = $order instanceof Order && Validate::isLoadedObject($order) ? new Customer((int) $order->id_customer) : null;
        $newStatus = $params['newOrderStatus'] ?? null;
        $oldStatus = $params['oldOrderStatus'] ?? null;
        $idLang = $customer instanceof Customer && Validate::isLoadedObject($customer) && $customer->id_lang
            ? (int) $customer->id_lang
            : (int) $this->context->language->id;
        $idShop = $order instanceof Order && Validate::isLoadedObject($order) ? (int) $order->id_shop : (int) $this->context->shop->id;

        $state = $this->buildStateContext($newStatus, $idLang);
        $oldState = $this->buildStateContext($oldStatus, $idLang);
        $products = $this->productsContextBuilder->buildFromOrder($order, $idLang);

        return $this->composePayload(
            $eventName,
            $idShop,
            $idLang,
            $customer,
            $this->orderSegmentBuilder->build($order, $idLang, $products, $state, $oldState),
            $products
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
        $state = $this->buildStateContext($currentStatus, $idLang);
        $products = $this->productsContextBuilder->buildFromOrder($order, $idLang);

        return $this->composePayload(
            ModuleConstants::EVENT_ORDER_CREATED,
            $idShop,
            $idLang,
            $customer instanceof Customer ? $customer : null,
            $this->orderSegmentBuilder->build($order, $idLang, $products, $state),
            $products
        );
    }

    /**
     * @param array<int, array<string, mixed>> $products
     *
     * @return array<string, mixed>
     */
    private function composePayload(string $eventName, int $idShop, int $idLang, ?Customer $customer, array $order, array $products): array
    {
        return (new TemplateContextPayloadBuilder())
            ->withEvent($this->eventSegmentBuilder->build($eventName))
            ->withShop($this->shopSegmentBuilder->build($idShop, $idLang))
            ->withCustomer($this->customerSegmentBuilder->build($customer instanceof Customer && Validate::isLoadedObject($customer) ? $customer : null))
            ->withOrder($order)
            ->withRelatedProducts($this->relatedProductsContextProvider->getRelatedProducts(
                $this->productsContextBuilder->extractProductIds($products),
                $idLang,
                $idShop
            ))
            ->withReviews($this->reviewsContextProvider->getLatestApprovedReviews($idShop))
            ->build();
    }

    /**
     * @param mixed $status
     *
     * @return array<string, mixed>
     */
    private function buildStateContext($status, int $idLang): array
    {
        $stateId = 0;
        $stateName = '';

        if ($status instanceof OrderState && Validate::isLoadedObject($status)) {
            $stateId = (int) $status->id;
            if (is_array($status->name) && isset($status->name[$idLang])) {
                $stateName = (string) $status->name[$idLang];
            } elseif (is_string($status->name)) {
                $stateName = $status->name;
            }
        }

        return [
            'id' => $stateId,
            'key' => $this->orderStateEventService->resolveOrderStateKey($status, $stateName, $stateId),
            'name' => $stateName,
        ];
    }
}
