<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Cart;
use DateTimeImmutable;
use Exception;
use Validate;

class CartContextSegmentBuilder
{
    /**
     * @param array<int, array<string, mixed>> $products
     *
     * @return array<string, mixed>
     */
    public function build(Cart $cart, string $cartUrl, string $abandonedAt, array $products): array
    {
        $totals = $this->getCartTotals($cart);

        return [
            'id' => (int) $cart->id,
            'url' => $cartUrl,
            'recovery_url' => $cartUrl,
            'abandoned_at' => $abandonedAt,
            'updated_at' => $cart->date_upd ? date(DATE_ATOM, strtotime((string) $cart->date_upd)) : null,
            'abandoned_minutes' => $this->calculateAbandonedMinutes($cart->date_upd, $abandonedAt),
            'products_count' => $this->countProducts($products),
            'total' => $totals['total'] ?? 0.0,
            'totals' => $totals,
            'items' => $products,
        ];
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
    private function countProducts(array $products): int
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
