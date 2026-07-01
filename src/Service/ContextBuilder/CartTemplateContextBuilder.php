<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Cart;
use Context;
use Currency;
use Customer;
use DateTimeImmutable;
use Exception;
use Validate;
use Velox\MailSendVx\ModuleConstants;

class CartTemplateContextBuilder implements DomainTemplateContextBuilderInterface
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function supportsEvent(string $eventName): bool
    {
        return $eventName === ModuleConstants::EVENT_CART_ABANDONED;
    }

    public function buildHookContext(string $eventName, array $params): array
    {
        return $this->buildCartAbandonedContext($params);
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
                $decoded['shop']['url'] = (string) ($decoded['shop']['url'] ?? $this->context->link->getBaseLink((int) $this->context->shop->id, true));
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

        return [
            'related_products' => [],
            'reviews' => [],
            'event' => [
                'name' => $eventName,
            ],
            'shop' => [
                'id' => (int) $this->context->shop->id,
                'id_lang' => (int) $this->context->language->id,
                'name' => (string) $this->context->shop->name,
                'url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
                'contact_url' => '',
            ],
            'customer' => [
                'id' => 0,
                'name' => 'Cliente de prueba',
                'firstname' => 'Cliente',
                'lastname' => 'Prueba',
                'email' => 'cliente@example.com',
                'is_customer' => false,
            ],
            'cart' => [
                'id' => 1,
                'url' => $this->context->link->getPageLink('cart', true, null, 'action=show'),
                'recovery_url' => $this->context->link->getPageLink('cart', true, null, 'action=show'),
                'abandoned_at' => date(DATE_ATOM),
                'updated_at' => date(DATE_ATOM),
                'abandoned_minutes' => 60,
                'products_count' => 0,
                'total' => 0.0,
                'totals' => $this->getCartTotals(new Cart()),
                'items' => [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildCartAbandonedContext(array $params): array
    {
        $idCart = (int) ($params['id_cart'] ?? 0);
        $cart = $params['cart'] instanceof Cart ? $params['cart'] : new Cart($idCart);
        $customer = $params['customer'] instanceof Customer ? $params['customer'] : new Customer((int) $cart->id_customer);
        $idLang = (int) ($cart->id_lang ?: $this->context->language->id);
        $idShop = (int) ($cart->id_shop ?: $this->context->shop->id);
        $cartUrl = $this->context->link->getPageLink('cart', true, $idLang, 'action=show');
        $abandonedAt = (string) ($params['abandoned_at'] ?? $cart->date_upd);
        $abandonedMinutes = $this->calculateAbandonedMinutes($cart->date_upd, $abandonedAt);
        $products = $this->getCartProducts($cart);
        $totals = $this->getCartTotals($cart);

        return [
            'event' => [
                'name' => ModuleConstants::EVENT_CART_ABANDONED,
            ],
            'shop' => [
                'id' => $idShop,
                'id_lang' => $idLang,
                'name' => (string) $this->context->shop->name,
                'url' => $this->context->link->getBaseLink($idShop, true),
                'contact_url' => '',
            ],
            'customer' => [
                'id' => Validate::isLoadedObject($customer) ? (int) $customer->id : 0,
                'name' => Validate::isLoadedObject($customer) ? trim($customer->firstname . ' ' . $customer->lastname) : '',
                'firstname' => Validate::isLoadedObject($customer) ? (string) $customer->firstname : '',
                'lastname' => Validate::isLoadedObject($customer) ? (string) $customer->lastname : '',
                'email' => Validate::isLoadedObject($customer) ? (string) $customer->email : '',
                'is_customer' => Validate::isLoadedObject($customer) && (int) $customer->id > 0,
            ],
            'cart' => [
                'id' => (int) $cart->id,
                'url' => $cartUrl,
                'recovery_url' => $cartUrl,
                'abandoned_at' => $abandonedAt,
                'updated_at' => $cart->date_upd ? date(DATE_ATOM, strtotime((string) $cart->date_upd)) : null,
                'abandoned_minutes' => $abandonedMinutes,
                'products_count' => $this->countCartItems($products),
                'total' => $totals['total'] ?? 0,
                'totals' => $totals,
                'items' => $products,
            ],
            'related_products' => [],
            'reviews' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCartProducts(Cart $cart): array
    {
        if (!Validate::isLoadedObject($cart)) {
            return [];
        }

        $products = [];
        foreach ($cart->getProducts() as $product) {
            $products[] = [
                'category_id' => isset($product['id_category_default']) ? (int) $product['id_category_default'] : 0,
                'category_name' => isset($product['category']) ? (string) $product['category'] : '',
                'id_product' => isset($product['id_product']) ? (int) $product['id_product'] : 0,
                'id_product_attribute' => isset($product['id_product_attribute']) ? (int) $product['id_product_attribute'] : 0,
                'image_url' => isset($product['image']) ? (string) $product['image'] : '',
                'name' => isset($product['name']) ? (string) $product['name'] : '',
                'product_url' => isset($product['link']) ? (string) $product['link'] : '',
                'quantity' => isset($product['cart_quantity']) ? (int) $product['cart_quantity'] : (isset($product['quantity']) ? (int) $product['quantity'] : 0),
                'reference' => isset($product['reference']) ? (string) $product['reference'] : '',
                'tax_name' => '',
                'tax_rate' => isset($product['rate']) ? (float) $product['rate'] : 0.0,
                'total_price' => isset($product['total_wt']) ? (float) $product['total_wt'] : 0.0,
                'total_price_tax_amount' => 0.0,
                'total_price_tax_excl' => isset($product['total']) ? (float) $product['total'] : 0.0,
                'total_price_tax_incl' => isset($product['total_wt']) ? (float) $product['total_wt'] : 0.0,
                'unit_price' => isset($product['price_wt']) ? (float) $product['price_wt'] : 0.0,
                'unit_price_tax_amount' => 0.0,
                'unit_price_tax_excl' => isset($product['price']) ? (float) $product['price'] : 0.0,
                'unit_price_tax_incl' => isset($product['price_wt']) ? (float) $product['price_wt'] : 0.0,
            ];
        }

        return $products;
    }

    /**
     * @return array<string, float>
     */
    private function getCartTotals(Cart $cart): array
    {
        if (!Validate::isLoadedObject($cart)) {
            return [
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
            ];
        }

        $productsTaxIncl = (float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);
        $productsTaxExcl = (float) $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);
        $shippingTaxIncl = (float) $cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
        $shippingTaxExcl = (float) $cart->getOrderTotal(false, Cart::ONLY_SHIPPING);
        $discountsTaxIncl = (float) $cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
        $discountsTaxExcl = (float) $cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS);
        $totalTaxIncl = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $totalTaxExcl = (float) $cart->getOrderTotal(false, Cart::BOTH);

        return [
            'discounts' => $discountsTaxIncl,
            'discounts_tax_amount' => max(0.0, $discountsTaxIncl - $discountsTaxExcl),
            'discounts_tax_excl' => $discountsTaxExcl,
            'discounts_tax_incl' => $discountsTaxIncl,
            'products' => $productsTaxIncl,
            'products_tax_amount' => max(0.0, $productsTaxIncl - $productsTaxExcl),
            'products_tax_excl' => $productsTaxExcl,
            'products_tax_incl' => $productsTaxIncl,
            'shipping' => $shippingTaxIncl,
            'shipping_tax_amount' => max(0.0, $shippingTaxIncl - $shippingTaxExcl),
            'shipping_tax_excl' => $shippingTaxExcl,
            'shipping_tax_incl' => $shippingTaxIncl,
            'total' => $totalTaxIncl,
            'total_tax_amount' => max(0.0, $totalTaxIncl - $totalTaxExcl),
            'total_tax_excl' => $totalTaxExcl,
            'total_tax_incl' => $totalTaxIncl,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    private function countCartItems(array $products): int
    {
        $count = 0;
        foreach ($products as $product) {
            $count += (int) ($product['quantity'] ?? 0);
        }

        return $count;
    }

    private function calculateAbandonedMinutes(?string $cartUpdatedAt, string $abandonedAt): int
    {
        if (!$cartUpdatedAt) {
            return 0;
        }

        try {
            $updated = new DateTimeImmutable($cartUpdatedAt);
            $abandoned = new DateTimeImmutable($abandonedAt);
        } catch (Exception $exception) {
            return 0;
        }

        return max(0, (int) floor(($abandoned->getTimestamp() - $updated->getTimestamp()) / 60));
    }
}
