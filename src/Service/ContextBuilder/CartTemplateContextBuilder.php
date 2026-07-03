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
        $fixturePath = dirname(__DIR__, 2) . '/.agents/fixtures/cart.json';
        if (is_file($fixturePath)) {
            $decoded = json_decode((string) file_get_contents($fixturePath), true);
            if (is_array($decoded)) {
                $decoded['event']['name'] = $eventName;
                $decoded['shop']['id'] = (int) ($decoded['shop']['id'] ?? $this->context->shop->id);
                $decoded['shop']['id_lang'] = (int) ($decoded['shop']['id_lang'] ?? $this->context->language->id);
                $decoded['shop']['name'] = (string) ($decoded['shop']['name'] ?? $this->context->shop->name);
                $decoded['shop']['url'] = (string) ($decoded['shop']['url'] ?? $this->context->link->getBaseLink((int) $decoded['shop']['id'], true));
                $decoded['shop']['contact_url'] = (string) ($decoded['shop']['contact_url'] ?? '');
                $decoded['customer']['id'] = (int) ($decoded['customer']['id'] ?? 0);
                $decoded['customer']['name'] = (string) ($decoded['customer']['name'] ?? trim((string) (($decoded['customer']['firstname'] ?? '') . ' ' . ($decoded['customer']['lastname'] ?? ''))));
                $decoded['customer']['firstname'] = (string) ($decoded['customer']['firstname'] ?? '');
                $decoded['customer']['lastname'] = (string) ($decoded['customer']['lastname'] ?? '');
                $decoded['customer']['email'] = (string) ($decoded['customer']['email'] ?? '');
                $decoded['customer']['is_customer'] = (bool) ($decoded['customer']['is_customer'] ?? ((int) ($decoded['customer']['id'] ?? 0) > 0));
                $decoded['cart']['id'] = (int) ($decoded['cart']['id'] ?? 0);
                $decoded['cart']['url'] = (string) ($decoded['cart']['url'] ?? '');
                $decoded['cart']['recovery_url'] = (string) ($decoded['cart']['recovery_url'] ?? $decoded['cart']['url']);
                $decoded['cart']['abandoned_at'] = (string) ($decoded['cart']['abandoned_at'] ?? '');
                $decoded['cart']['abandoned_minutes'] = (int) ($decoded['cart']['abandoned_minutes'] ?? 0);
                $decoded['cart']['products_count'] = (int) ($decoded['cart']['products_count'] ?? (is_array($decoded['cart']['items'] ?? null) ? count($decoded['cart']['items']) : 0));
                $decoded['cart']['total'] = $decoded['cart']['total'] ?? ($decoded['cart']['totals']['total'] ?? 0);
                $decoded['cart']['items'] = $decoded['cart']['items'] ?? [];

                return $decoded;
            }
        }

        return (new TemplateContextPayloadBuilder())
            ->withEvent($this->eventSegmentBuilder->build($eventName))
            ->withShop($this->shopSegmentBuilder->build((int) $this->context->shop->id, (int) $this->context->language->id, [
                'contact_url' => '',
            ]))
            ->withCustomer($this->customerSegmentBuilder->build(null, [
                'id' => 0,
                'name' => 'Cliente de prueba',
                'firstname' => 'Cliente',
                'lastname' => 'Prueba',
                'email' => 'cliente@example.com',
                'is_customer' => false,
            ]))
            ->withCart([
                'id' => 1,
                'url' => $this->context->link->getPageLink('cart', true, null, 'action=show'),
                'recovery_url' => $this->context->link->getPageLink('cart', true, null, 'action=show'),
                'abandoned_at' => date(DATE_ATOM),
                'updated_at' => date(DATE_ATOM),
                'abandoned_minutes' => 60,
                'products_count' => 0,
                'total' => 0.0,
                'totals' => [
                    'discounts' => 0.0,
                    'discounts_tax_amount' => 0.0,
                    'discounts_tax_excl' => 0.0,
                    'discounts_tax_incl' => 0.0,
                    'products' => 0.0,
                    'products_tax_amount' => 0.0,
                    'products_tax_excl' => 0.0,
                    'products_tax_incl' => 0.0,
                    'shipping' => 0.0,
                    'shipping_tax_amount' => 0.0,
                    'shipping_tax_excl' => 0.0,
                    'shipping_tax_incl' => 0.0,
                    'total' => 0.0,
                    'total_tax_amount' => 0.0,
                    'total_tax_excl' => 0.0,
                    'total_tax_incl' => 0.0,
                ],
                'items' => [],
            ])
            ->withRelatedProducts([])
            ->withReviews([])
            ->build();
    }
}
