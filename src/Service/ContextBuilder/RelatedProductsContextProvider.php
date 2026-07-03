<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Context;
use Db;
use Image;
use ImageType;
use Product;
use Shop;
use Validate;

class RelatedProductsContextProvider
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
     * @param array<int, int> $sourceProductIds
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRelatedProducts(array $sourceProductIds, int $idLang, int $idShop, int $limit = 4): array
    {
        $sourceProductIds = $this->normalizeIds($sourceProductIds);
        if (empty($sourceProductIds) || $limit < 1) {
            return [];
        }

        $relatedProducts = [];
        $excludedProductIds = array_fill_keys($sourceProductIds, true);
        $seedProducts = $this->getSeedProducts($sourceProductIds, $idShop);

        foreach ($seedProducts as $seedProduct) {
            if (count($relatedProducts) >= $limit) {
                break;
            }

            if (!empty($seedProduct['id_manufacturer'])) {
                $this->appendProducts(
                    $relatedProducts,
                    $excludedProductIds,
                    $this->findProductsByManufacturer(
                        (int) $seedProduct['id_manufacturer'],
                        $idLang,
                        $idShop,
                        $limit - count($relatedProducts),
                        array_keys($excludedProductIds)
                    ),
                    $idLang,
                    $idShop,
                    $limit
                );
            }

            if (count($relatedProducts) >= $limit) {
                break;
            }

            $this->appendProducts(
                $relatedProducts,
                $excludedProductIds,
                $this->findAccessoryProducts(
                    (int) $seedProduct['id_product'],
                    $idLang,
                    $idShop,
                    $limit - count($relatedProducts),
                    array_keys($excludedProductIds)
                ),
                $idLang,
                $idShop,
                $limit
            );

            if (count($relatedProducts) >= $limit) {
                break;
            }

            $categoryIds = isset($seedProduct['category_ids']) && is_array($seedProduct['category_ids'])
                ? $seedProduct['category_ids']
                : [];

            if (!empty($categoryIds)) {
                $this->appendProducts(
                    $relatedProducts,
                    $excludedProductIds,
                    $this->findProductsByCategories(
                        $categoryIds,
                        $idLang,
                        $idShop,
                        $limit - count($relatedProducts),
                        array_keys($excludedProductIds)
                    ),
                    $idLang,
                    $idShop,
                    $limit
                );
            }
        }

        return array_values($relatedProducts);
    }

    /**
     * @param array<int, int> $productIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSeedProducts(array $productIds, int $idShop): array
    {
        $seedProducts = [];

        foreach ($productIds as $idProduct) {
            $row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
                'SELECT p.id_product, p.id_manufacturer, product_shop.id_category_default
                FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` product_shop
                    ON (product_shop.id_product = p.id_product AND product_shop.id_shop = ' . (int) $idShop . ')
                WHERE p.id_product = ' . (int) $idProduct
            );

            if (!is_array($row)) {
                continue;
            }

            $categoryIds = Product::getProductCategories((int) $row['id_product']);
            if (!is_array($categoryIds)) {
                $categoryIds = [];
            }

            if (!empty($row['id_category_default'])) {
                array_unshift($categoryIds, (int) $row['id_category_default']);
            }

            $seedProducts[] = [
                'id_product' => (int) $row['id_product'],
                'id_manufacturer' => (int) ($row['id_manufacturer'] ?? 0),
                'category_ids' => $this->normalizeIds($categoryIds),
            ];
        }

        return $seedProducts;
    }

    /**
     * @param array<int, int> $excludedProductIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function findProductsByManufacturer(int $idManufacturer, int $idLang, int $idShop, int $limit, array $excludedProductIds): array
    {
        if ($idManufacturer < 1 || $limit < 1) {
            return [];
        }

        return $this->findProducts(
            'p.id_manufacturer = ' . (int) $idManufacturer,
            $idLang,
            $idShop,
            $limit,
            $excludedProductIds
        );
    }

    /**
     * @param array<int, int> $excludedProductIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function findAccessoryProducts(int $idProduct, int $idLang, int $idShop, int $limit, array $excludedProductIds): array
    {
        if ($idProduct < 1 || $limit < 1) {
            return [];
        }

        return $this->findProducts(
            'p.id_product IN (
                SELECT a.id_product_2
                FROM `' . _DB_PREFIX_ . 'accessory` a
                WHERE a.id_product_1 = ' . (int) $idProduct . '
            )',
            $idLang,
            $idShop,
            $limit,
            $excludedProductIds
        );
    }

    /**
     * @param array<int, int> $categoryIds
     * @param array<int, int> $excludedProductIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function findProductsByCategories(array $categoryIds, int $idLang, int $idShop, int $limit, array $excludedProductIds): array
    {
        $categoryIds = $this->normalizeIds($categoryIds);
        if (empty($categoryIds) || $limit < 1) {
            return [];
        }

        return $this->findProducts(
            'p.id_product IN (
                SELECT cp.id_product
                FROM `' . _DB_PREFIX_ . 'category_product` cp
                WHERE cp.id_category IN (' . implode(',', $categoryIds) . ')
            )',
            $idLang,
            $idShop,
            $limit,
            $excludedProductIds
        );
    }

    /**
     * @param array<int, int> $excludedProductIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function findProducts(string $conditionSql, int $idLang, int $idShop, int $limit, array $excludedProductIds): array
    {
        if ($limit < 1) {
            return [];
        }

        $where = [
            $conditionSql,
            'product_shop.id_shop = ' . (int) $idShop,
            'product_shop.active = 1',
            'product_shop.visibility IN ("both", "catalog")',
        ];

        $excludedProductIds = $this->normalizeIds($excludedProductIds);
        if (!empty($excludedProductIds)) {
            $where[] = 'p.id_product NOT IN (' . implode(',', $excludedProductIds) . ')';
        }

        $sql = 'SELECT DISTINCT p.id_product, pl.name, pl.link_rewrite, cl.link_rewrite AS category_link_rewrite
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` product_shop
                ON (product_shop.id_product = p.id_product)
            INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON (
                    pl.id_product = p.id_product
                    AND pl.id_lang = ' . (int) $idLang . Shop::addSqlRestrictionOnLang('pl') . '
                )
            LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                ON (
                    cl.id_category = product_shop.id_category_default
                    AND cl.id_lang = ' . (int) $idLang . Shop::addSqlRestrictionOnLang('cl') . '
                )
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY p.date_upd DESC, p.id_product DESC
            LIMIT ' . (int) $limit;

        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int, array<string, mixed>> $relatedProducts
     * @param array<int, bool> $excludedProductIds
     * @param array<int, array<string, mixed>> $rows
     */
    private function appendProducts(array &$relatedProducts, array &$excludedProductIds, array $rows, int $idLang, int $idShop, int $limit): void
    {
        foreach ($rows as $row) {
            $idProduct = (int) ($row['id_product'] ?? 0);
            if ($idProduct < 1 || isset($excludedProductIds[$idProduct])) {
                continue;
            }

            $formatted = $this->formatRelatedProduct($row, $idLang, $idShop);
            if ($formatted === null) {
                continue;
            }

            $relatedProducts[] = $formatted;
            $excludedProductIds[$idProduct] = true;

            if (count($relatedProducts) >= $limit) {
                break;
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>|null
     */
    private function formatRelatedProduct(array $row, int $idLang, int $idShop): ?array
    {
        $idProduct = (int) ($row['id_product'] ?? 0);
        if ($idProduct < 1) {
            return null;
        }

        $product = new Product($idProduct, false, $idLang, $idShop);
        if (!Validate::isLoadedObject($product) || !Product::checkAccessStatic($idProduct, false)) {
            return null;
        }

        $linkRewrite = $this->resolveLinkRewrite($idProduct, $idLang, (string) ($row['link_rewrite'] ?? ''));
        $imageUrl = '';
        $cover = Image::getCover($idProduct);
        if (is_array($cover) && !empty($cover['id_image'])) {
            $imageUrl = $this->context->link->getImageLink(
                $linkRewrite,
                $idProduct . '-' . (int) $cover['id_image'],
                ImageType::getFormattedName('home')
            );
        }

        $specificPrice = null;

        return [
            'id' => $idProduct,
            'name' => (string) ($row['name'] ?? $product->name),
            'price' => (float) Product::getPriceStatic(
                $idProduct,
                true,
                null,
                6,
                null,
                false,
                true,
                1,
                false,
                null,
                null,
                null,
                $specificPrice,
                true,
                true,
                $this->context
            ),
            'url' => $this->context->link->getProductLink(
                $idProduct,
                $linkRewrite,
                isset($row['category_link_rewrite']) ? (string) $row['category_link_rewrite'] : null,
                null,
                $idLang,
                $idShop
            ),
            'image_url' => $imageUrl,
        ];
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

    /**
     * @param array<int, mixed> $ids
     *
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            $normalized[] = (int) $id;
        }

        return array_values(array_unique(array_filter($normalized)));
    }
}
