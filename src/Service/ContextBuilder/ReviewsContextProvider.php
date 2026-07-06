<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Db;

class ReviewsContextProvider
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLatestApprovedReviews(int $idShop, int $limit = 5): array
    {
        if ($limit < 1 || !$this->reviewsTableExists()) {
            return [];
        }

        $sql = 'SELECT sr.id_review, sr.id_order, sr.id_customer, sr.id_lang, sr.title_review, sr.text_review,
                sr.rating_value, sr.review_status, sr.id_shop, sr.date_add, sr.date_upd,
                c.firstname AS customer_firstname, c.lastname AS customer_lastname
            FROM `' . _DB_PREFIX_ . 'bt_spr_shop_reviews` sr
            LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.id_customer = sr.id_customer)
            WHERE sr.review_status = 1
                AND sr.id_shop = ' . (int) $idShop . '
            ORDER BY sr.date_add DESC
            LIMIT ' . (int) $limit;

        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $reviews = [];
        foreach ($rows as $row) {
            $firstname = trim((string) ($row['customer_firstname'] ?? ''));
            $lastname = trim((string) ($row['customer_lastname'] ?? ''));

            $reviews[] = [
                'author' => trim($firstname . ' ' . $lastname),
                'firstname' => $firstname,
                'lastname' => $lastname,
                'rating' => (int) ($row['rating_value'] ?? 0),
                'title' => (string) ($row['title_review'] ?? ''),
                'content' => (string) ($row['text_review'] ?? ''),
            ];
        }

        return $reviews;
    }

    private function reviewsTableExists(): bool
    {
        $sql = 'SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
                AND table_name = \'' . pSQL(_DB_PREFIX_ . 'bt_spr_shop_reviews') . '\'';

        return (int) Db::getInstance()->getValue($sql) > 0;
    }
}
