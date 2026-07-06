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
        return [
            'event' => [
                'name' => $eventName,
            ],
            'shop' => [
                'id' => (int) $this->context->shop->id,
                'id_lang' => (int) $this->context->language->id,
                'name' => 'HEMP Ecuador',
                'url' => 'https://hemp.desarrollovelox.com',
                'unsubscribe_url' => 'https://hemp.desarrollovelox.com/module/mailsendvx/unsubscribe?email=jonathan%40velox.ec&token=sample-token&id_shop=1',
            ],
            'customer' => [
                'id' => 10,
                'name' => 'Jonathan Salazar',
                'firstname' => 'Jonathan',
                'lastname' => 'Salazar',
                'email' => 'jonathan@velox.ec',
            ],
            'order' => [
                'id' => 10,
                'reference' => 'RSTIQXSIH',
                'total' => 117.30,
                'date' => '2026-07-06 18:41:00',
                'formated_date' => '06 de Julio, 2026',
                'status' => 'Enviado',
                'old_status' => 'Pago aceptado',
                'payment_method' => 'Pagos por transferencia bancaria',
                'payment_method_code' => 'bankwire',
                'shipping_method' => 'Recoger en la tienda',
                'state' => [
                    'id' => 4,
                    'key' => 'shipped',
                    'name' => 'Enviado',
                ],
                'old_state' => [
                    'id' => 2,
                    'key' => 'payment_accepted',
                    'name' => 'Pago aceptado',
                ],
                'totals' => [
                    'paid' => 117.30,
                    'products' => 117.30,
                    'shipping' => 0.00,
                    'discounts' => 0.00,
                    'tax' => 15.30,
                ],
                'billing_address' => [
                    'firstname' => 'Jonathan',
                    'lastname' => 'Salazar',
                    'full_name' => 'Jonathan Salazar',
                    'company' => '',
                    'address1' => 'Quito',
                    'address2' => '',
                    'city' => 'Quito',
                    'postcode' => '170150',
                    'country' => 'Ecuador',
                    'state' => 'Pichincha',
                    'phone' => '0987654321',
                    'phone_mobile' => '0987654321',
                    'formatted' => "Jonathan Salazar\nQuito\nQuito 170150\nPichincha\nEcuador",
                ],
                'shipping_address' => [
                    'firstname' => 'Jonathan',
                    'lastname' => 'Salazar',
                    'full_name' => 'Jonathan Salazar',
                    'company' => '',
                    'address1' => 'Quito',
                    'address2' => '',
                    'city' => 'Quito',
                    'postcode' => '170150',
                    'country' => 'Ecuador',
                    'state' => 'Pichincha',
                    'phone' => '0987654321',
                    'phone_mobile' => '0987654321',
                    'formatted' => "Jonathan Salazar\nQuito\nQuito 170150\nPichincha\nEcuador",
                ],
                'shipping' => [
                    'carrier_name' => 'Recoger en la tienda',
                    'cost' => 0.00,
                    'tracking_url' => 'https://tracking.example.com/shipments/TRK-RSTIQXSIH',
                ],
                'products' => [
                    [
                        'id' => 3,
                        'attribute_id' => 0,
                        'name' => 'SUENOS',
                        'reference' => 'Dulces Sueñozzz 30ml',
                        'quantity' => 1,
                        'unit_price' => 58.65,
                        'total_price' => 58.65,
                        'unit_price_tax_excl' => 51,
                        'unit_price_tax_incl' => 58.65,
                        'total_price_tax_excl' => 51,
                        'total_price_tax_incl' => 58.65,
                        'url' => 'https://hemp.desarrollovelox.com/salud/3-suenos.html',
                        'image_url' => 'https://hemp.desarrollovelox.com/3-home_default/suenos.jpg',
                        'attributes' => [
                            [
                                'label' => 'Presentacion',
                                'value' => '30ml',
                            ],
                            [
                                'label' => 'Aroma',
                                'value' => 'Natural',
                            ],
                        ],
                    ],
                    [
                        'id' => 2,
                        'attribute_id' => 0,
                        'name' => 'FOCUS',
                        'reference' => 'Focus 30ml',
                        'quantity' => 1,
                        'unit_price' => 58.65,
                        'total_price' => 58.65,
                        'unit_price_tax_excl' => 51,
                        'unit_price_tax_incl' => 58.65,
                        'total_price_tax_excl' => 51,
                        'total_price_tax_incl' => 58.65,
                        'url' => 'https://hemp.desarrollovelox.com/salud/2-focus.html',
                        'image_url' => 'https://hemp.desarrollovelox.com/2-home_default/focus.jpg',
                        'attributes' => [
                            [
                                'label' => 'Presentacion',
                                'value' => '30ml',
                            ],
                            [
                                'label' => 'Aroma',
                                'value' => 'Natural',
                            ],
                        ],
                    ],
                ],
            ],
            'related_products' => [
                [
                    'id' => 16,
                    'name' => 'GOMCBD',
                    'price' => 24.44,
                    'url' => 'https://hemp.desarrollovelox.com/salud/16-gomcbd.html',
                    'image_url' => 'https://hemp.desarrollovelox.com/16-home_default/gomcbd.jpg',
                ],
                [
                    'id' => 24,
                    'name' => 'COLAGENO',
                    'price' => 43.99,
                    'url' => 'https://hemp.desarrollovelox.com/salud/24-colageno.html',
                    'image_url' => 'https://hemp.desarrollovelox.com/23-home_default/colageno.jpg',
                ],
            ],
            'reviews' => [
                [
                    'author' => 'Erick',
                    'firstname' => 'Erick',
                    'lastname' => '',
                    'rating' => 5,
                    'title' => 'Atención rápida y personalizada',
                    'content' => 'Me ayudaron a elegir lo que realmente necesitaba.',
                ],
                [
                    'author' => 'Carlos',
                    'firstname' => 'Carlos',
                    'lastname' => '',
                    'rating' => 5,
                    'title' => 'Entrega exprés sin fallos',
                    'content' => 'Todo llegó perfecto y rápido.',
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
            ->withShop($this->shopSegmentBuilder->build($idShop, $idLang, [
                'unsubscribe_email' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->email : '',
            ]))
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
