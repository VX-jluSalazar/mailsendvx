<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Cart;
use Context;
use Image;
use ImageType;
use Order;
use Product;
use Validate;

class ProductsContextBuilder
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildFromOrder(?Order $order, int $idLang): array
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return [];
        }

        $products = [];
        foreach ($order->getProducts() as $row) {
            $idProduct = (int) ($row['product_id'] ?? $row['id_product'] ?? 0);
            $attributeId = (int) ($row['product_attribute_id'] ?? $row['id_product_attribute'] ?? 0);
            $rewrite = $this->resolveLinkRewrite($idProduct, $idLang, isset($row['link_rewrite']) && is_string($row['link_rewrite']) ? $row['link_rewrite'] : '');

            $products[] = [
                'id' => $idProduct,
                'attribute_id' => $attributeId,
                'name' => (string) ($row['product_name'] ?? ''),
                'reference' => (string) ($row['product_reference'] ?? ''),
                'quantity' => (int) ($row['product_quantity'] ?? 0),
                'unit_price' => (float) ($row['unit_price_tax_incl'] ?? 0),
                'total_price' => (float) ($row['total_price_tax_incl'] ?? 0),
                'unit_price_tax_excl' => (float) ($row['unit_price_tax_excl'] ?? 0),
                'unit_price_tax_incl' => (float) ($row['unit_price_tax_incl'] ?? 0),
                'total_price_tax_excl' => (float) ($row['total_price_tax_excl'] ?? 0),
                'total_price_tax_incl' => (float) ($row['total_price_tax_incl'] ?? 0),
                'url' => $idProduct > 0 ? $this->context->link->getProductLink($idProduct, $rewrite, null, null, $idLang, (int) $order->id_shop, $attributeId > 0 ? $attributeId : 0) : '',
                'image_url' => $this->getProductImageUrl($idProduct, $rewrite),
                'attributes' => $this->extractAttributes($row),
            ];
        }

        return $products;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildFromCart(Cart $cart, int $idLang): array
    {
        if (!Validate::isLoadedObject($cart)) {
            return [];
        }

        $products = [];
        foreach ($cart->getProducts() as $row) {
            $idProduct = (int) ($row['id_product'] ?? 0);
            $attributeId = (int) ($row['id_product_attribute'] ?? 0);
            $rewrite = $this->resolveLinkRewrite($idProduct, $idLang, isset($row['link_rewrite']) && is_string($row['link_rewrite']) ? $row['link_rewrite'] : '');
            $url = isset($row['link']) ? (string) $row['link'] : '';

            if ($url === '' && $idProduct > 0) {
                $url = $this->context->link->getProductLink($idProduct, $rewrite, null, null, $idLang, (int) $cart->id_shop, $attributeId > 0 ? $attributeId : 0);
            }

            $products[] = [
                'id' => $idProduct,
                'attribute_id' => $attributeId,
                'name' => (string) ($row['name'] ?? ''),
                'reference' => (string) ($row['reference'] ?? ''),
                'quantity' => isset($row['cart_quantity']) ? (int) $row['cart_quantity'] : (int) ($row['quantity'] ?? 0),
                'unit_price' => (float) ($row['price_wt'] ?? 0),
                'total_price' => (float) ($row['total_wt'] ?? 0),
                'unit_price_tax_excl' => (float) ($row['price'] ?? 0),
                'unit_price_tax_incl' => (float) ($row['price_wt'] ?? 0),
                'total_price_tax_excl' => (float) ($row['total'] ?? 0),
                'total_price_tax_incl' => (float) ($row['total_wt'] ?? 0),
                'url' => $url,
                'image_url' => isset($row['image']) ? (string) $row['image'] : $this->getProductImageUrl($idProduct, $rewrite),
                'attributes' => $this->extractAttributes($row),
            ];
        }

        return $products;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     *
     * @return array<int, int>
     */
    public function extractProductIds(array $products): array
    {
        $productIds = [];

        foreach ($products as $product) {
            $idProduct = (int) ($product['id'] ?? 0);
            if ($idProduct > 0) {
                $productIds[] = $idProduct;
            }
        }

        return array_values(array_unique($productIds));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<int, array<string, string>>
     */
    private function extractAttributes(array $row): array
    {
        $attributesDescription = '';
        if (isset($row['product_attributes']) && is_string($row['product_attributes'])) {
            $attributesDescription = $row['product_attributes'];
        } elseif (isset($row['attributes']) && is_string($row['attributes'])) {
            $attributesDescription = $row['attributes'];
        } elseif (isset($row['attributes_small']) && is_string($row['attributes_small'])) {
            $attributesDescription = $row['attributes_small'];
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

    private function getProductImageUrl(int $idProduct, string $linkRewrite): string
    {
        if ($idProduct < 1) {
            return '';
        }

        $cover = Image::getCover($idProduct);
        if (!is_array($cover) || empty($cover['id_image'])) {
            return '';
        }

        return $this->context->link->getImageLink(
            $linkRewrite,
            $idProduct . '-' . (int) $cover['id_image'],
            ImageType::getFormattedName('home')
        );
    }

    private function resolveLinkRewrite(int $idProduct, int $idLang, string $linkRewrite): string
    {
        $linkRewrite = trim($linkRewrite);
        if ($linkRewrite !== '' || $idProduct < 1) {
            return $linkRewrite;
        }

        $product = new Product($idProduct, false, $idLang, (int) $this->context->shop->id);
        if (!Validate::isLoadedObject($product)) {
            return '';
        }

        if (is_array($product->link_rewrite)) {
            return isset($product->link_rewrite[$idLang]) ? (string) $product->link_rewrite[$idLang] : '';
        }

        return is_string($product->link_rewrite) ? $product->link_rewrite : '';
    }
}
