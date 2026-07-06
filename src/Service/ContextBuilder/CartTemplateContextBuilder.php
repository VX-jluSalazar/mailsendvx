<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Cart;
use Context;
use Customer;
use Validate;
use Velox\MailSendVx\ModuleConstants;

class CartTemplateContextBuilder implements DomainTemplateContextBuilderInterface
{
    /**
     * @var Context
     */
    private $context;

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
     * @var CartContextSegmentBuilder
     */
    private $cartSegmentBuilder;

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
        EventContextSegmentBuilder $eventSegmentBuilder,
        ShopContextSegmentBuilder $shopSegmentBuilder,
        CustomerContextSegmentBuilder $customerSegmentBuilder,
        CartContextSegmentBuilder $cartSegmentBuilder,
        ProductsContextBuilder $productsContextBuilder,
        RelatedProductsContextProvider $relatedProductsContextProvider,
        ReviewsContextProvider $reviewsContextProvider
    ) {
        $this->context = $context;
        $this->eventSegmentBuilder = $eventSegmentBuilder;
        $this->shopSegmentBuilder = $shopSegmentBuilder;
        $this->customerSegmentBuilder = $customerSegmentBuilder;
        $this->cartSegmentBuilder = $cartSegmentBuilder;
        $this->productsContextBuilder = $productsContextBuilder;
        $this->relatedProductsContextProvider = $relatedProductsContextProvider;
        $this->reviewsContextProvider = $reviewsContextProvider;
    }

    public function supportsEvent(string $eventName): bool
    {
        return $eventName === ModuleConstants::EVENT_CART_ABANDONED;
    }

    public function buildHookContext(string $eventName, array $params): array
    {
        $idCart = (int) ($params['id_cart'] ?? 0);
        $cart = $params['cart'] instanceof Cart ? $params['cart'] : new Cart($idCart);
        $customer = $params['customer'] instanceof Customer ? $params['customer'] : new Customer((int) $cart->id_customer);
        $idLang = (int) ($cart->id_lang ?: $this->context->language->id);
        $idShop = (int) ($cart->id_shop ?: $this->context->shop->id);
        $cartUrl = $this->context->link->getPageLink('cart', true, $idLang, 'action=show');
        $abandonedAt = (string) ($params['abandoned_at'] ?? $cart->date_upd);
        $products = $this->productsContextBuilder->buildFromCart($cart, $idLang);

        return (new TemplateContextPayloadBuilder())
            ->withEvent($this->eventSegmentBuilder->build(ModuleConstants::EVENT_CART_ABANDONED))
            ->withShop($this->shopSegmentBuilder->build($idShop, $idLang, [
                'contact_url' => '',
                'unsubscribe_email' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->email : '',
            ]))
            ->withCustomer($this->customerSegmentBuilder->build(
                $customer instanceof Customer && Validate::isLoadedObject($customer) ? $customer : null,
                [
                    'is_customer' => $customer instanceof Customer && Validate::isLoadedObject($customer) && (int) $customer->id > 0,
                ]
            ))
            ->withCart($this->cartSegmentBuilder->build($cart, $cartUrl, $abandonedAt, $products))
            ->withRelatedProducts($this->relatedProductsContextProvider->getRelatedProducts(
                $this->productsContextBuilder->extractProductIds($products),
                $idLang,
                $idShop
            ))
            ->withReviews($this->reviewsContextProvider->getLatestApprovedReviews($idShop))
            ->build();
    }

    public function buildSampleContext(string $eventName): array
    {
        return (new TemplateContextPayloadBuilder())
            ->withEvent($this->eventSegmentBuilder->build($eventName))
            ->withShop($this->shopSegmentBuilder->build((int) $this->context->shop->id, (int) $this->context->language->id, [
                'contact_url' => 'https://api.whatsapp.com/send/?phone=593123456789',
                'unsubscribe_email' => 'jonathan@velox.ec',
            ]))
            ->withCustomer($this->customerSegmentBuilder->build(null, [
                'id' => 10,
                'name' => 'Jonathan Salazar',
                'firstname' => 'Jonathan',
                'lastname' => 'Salazar',
                'email' => 'jonathan@velox.ec',
                'is_customer' => true,
            ]))
            ->withCart([
                'id' => 15,
                'url' => $this->context->link->getPageLink('cart', true, null, 'action=show'),
                'recovery_url' => $this->context->link->getPageLink('cart', true, null, 'action=show'),
                'abandoned_at' => '2026-07-06T18:39:30-05:00',
                'updated_at' => '2026-07-06T17:39:30-05:00',
                'abandoned_minutes' => 60,
                'products_count' => 2,
                'total' => 117.3,
                'totals' => [
                    'discounts' => 0.0,
                    'discounts_tax_amount' => 0.0,
                    'discounts_tax_excl' => 0.0,
                    'discounts_tax_incl' => 0.0,
                    'products' => 117.3,
                    'products_tax_amount' => 15.3,
                    'products_tax_excl' => 102.0,
                    'products_tax_incl' => 117.3,
                    'shipping' => 0.0,
                    'shipping_tax_amount' => 0.0,
                    'shipping_tax_excl' => 0.0,
                    'shipping_tax_incl' => 0.0,
                    'total' => 117.3,
                    'total_tax_amount' => 15.3,
                    'total_tax_excl' => 102.0,
                    'total_tax_incl' => 117.3,
                ],
                'items' => [
                    [
                        'id' => 2,
                        'attribute_id' => 0,
                        'name' => 'FOCUS',
                        'reference' => 'Focus 30ml',
                        'quantity' => 1,
                        'unit_price' => 58.65,
                        'unit_price_tax_excl' => 51,
                        'unit_price_tax_incl' => 58.65,
                        'total_price' => 58.65,
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
                    [
                        'id' => 3,
                        'attribute_id' => 0,
                        'name' => 'SUENOS',
                        'reference' => 'Dulces Sueñozzz 30ml',
                        'quantity' => 1,
                        'unit_price' => 58.65,
                        'unit_price_tax_excl' => 51,
                        'unit_price_tax_incl' => 58.65,
                        'total_price' => 58.65,
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
                ],
            ])
            ->withRelatedProducts([
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
            ])
            ->withReviews([
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
            ])
            ->build();
    }
}
