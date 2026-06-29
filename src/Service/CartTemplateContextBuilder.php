<?php

namespace Velox\MailSendVx\Service;

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
                $decoded['event_name'] = $eventName;
                $decoded['id_lang'] = (int) $this->context->language->id;
                $decoded['id_shop'] = (int) $this->context->shop->id;
                $decoded['customer_id'] = (int) ($decoded['customer']['id'] ?? 0);
                $decoded['customer_name'] = trim((string) (($decoded['customer']['firstname'] ?? '') . ' ' . ($decoded['customer']['lastname'] ?? '')));
                $decoded['customer_firstname'] = (string) ($decoded['customer']['firstname'] ?? '');
                $decoded['customer_lastname'] = (string) ($decoded['customer']['lastname'] ?? '');
                $decoded['customer_email'] = (string) ($decoded['customer']['email'] ?? '');
                $decoded['cart_id'] = (int) ($decoded['cart']['id'] ?? 0);
                $decoded['cart_url'] = (string) ($decoded['misc']['cart_url'] ?? ($decoded['cart']['url'] ?? ''));
                $decoded['recovery_url'] = $decoded['cart_url'];
                $decoded['abandoned_at'] = (string) ($decoded['cart']['updated_at'] ?? '');
                $decoded['abandoned_minutes'] = (int) ($decoded['cart']['abandoned_minutes'] ?? 0);
                $decoded['currency'] = (string) ($decoded['currency'] ?? '');
                $decoded['cart_total'] = $decoded['cart']['totals']['total'] ?? 0;
                $decoded['cart_products_count'] = is_array($decoded['cart']['items'] ?? null) ? count($decoded['cart']['items']) : 0;
                $decoded['products'] = $decoded['cart']['items'] ?? [];
                $decoded['shop_name'] = (string) $this->context->shop->name;
                $decoded['shop_url'] = (string) ($decoded['shop_url'] ?? $this->context->link->getBaseLink((int) $this->context->shop->id, true));

                return $decoded;
            }
        }

        return [
            'event_name' => $eventName,
            'id_lang' => (int) $this->context->language->id,
            'id_shop' => (int) $this->context->shop->id,
            'customer_id' => 0,
            'customer_name' => 'Cliente de prueba',
            'customer_firstname' => 'Cliente',
            'customer_lastname' => 'Prueba',
            'customer_email' => 'cliente@example.com',
            'cart_id' => 1,
            'cart_url' => $this->context->link->getPageLink('cart', true, null, 'action=show'),
            'recovery_url' => $this->context->link->getPageLink('cart', true, null, 'action=show'),
            'abandoned_at' => date(DATE_ATOM),
            'abandoned_minutes' => 60,
            'currency' => 'USD',
            'cart_total' => 0,
            'cart_products_count' => 0,
            'products' => [],
            'related_products' => [],
            'shop_name' => (string) $this->context->shop->name,
            'shop_url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
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
        $currency = new Currency((int) $cart->id_currency);
        $idLang = (int) ($cart->id_lang ?: $this->context->language->id);
        $idShop = (int) ($cart->id_shop ?: $this->context->shop->id);
        $cartUrl = $this->context->link->getPageLink('cart', true, $idLang, 'action=show');
        $abandonedAt = (string) ($params['abandoned_at'] ?? $cart->date_upd);
        $abandonedMinutes = $this->calculateAbandonedMinutes($cart->date_upd, $abandonedAt);
        $products = $this->getCartProducts($cart, $currency);
        $totals = $this->getCartTotals($cart);

        $context = [
            'event_name' => ModuleConstants::EVENT_CART_ABANDONED,
            'id_lang' => $idLang,
            'id_shop' => $idShop,
            'customer_id' => Validate::isLoadedObject($customer) ? (int) $customer->id : 0,
            'customer_name' => Validate::isLoadedObject($customer) ? trim($customer->firstname . ' ' . $customer->lastname) : '',
            'customer_firstname' => Validate::isLoadedObject($customer) ? (string) $customer->firstname : '',
            'customer_lastname' => Validate::isLoadedObject($customer) ? (string) $customer->lastname : '',
            'customer_email' => Validate::isLoadedObject($customer) ? (string) $customer->email : '',
            'cart_id' => (int) $cart->id,
            'cart_url' => $cartUrl,
            'recovery_url' => $cartUrl,
            'abandoned_at' => $abandonedAt,
            'abandoned_minutes' => $abandonedMinutes,
            'currency' => Validate::isLoadedObject($currency) ? (string) $currency->iso_code : '',
            'cart_total' => $totals['total'] ?? 0,
            'cart_products_count' => $this->countCartItems($products),
            'products' => $products,
            'related_products' => [],
            'shop_name' => (string) $this->context->shop->name,
            'shop_url' => $this->context->link->getBaseLink($idShop, true),
        ];

        $context['cart'] = [
            'abandoned_minutes' => $abandonedMinutes,
            'id' => (int) $cart->id,
            'items' => $products,
            'totals' => $totals,
            'updated_at' => $cart->date_upd ? date(DATE_ATOM, strtotime((string) $cart->date_upd)) : null,
            'url' => $cartUrl,
        ];
        $context['customer'] = [
            'email' => $context['customer_email'],
            'firstname' => $context['customer_firstname'],
            'id' => $context['customer_id'],
            'is_customer' => $context['customer_id'] > 0,
            'lastname' => $context['customer_lastname'],
        ];
        $context['misc'] = [
            'cart_url' => $cartUrl,
            'contact_url' => '',
            'shop_url' => $context['shop_url'],
        ];

        return $context;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCartProducts(Cart $cart, ?Currency $currency): array
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
                'currency' => Validate::isLoadedObject($currency) ? (string) $currency->iso_code : '',
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
