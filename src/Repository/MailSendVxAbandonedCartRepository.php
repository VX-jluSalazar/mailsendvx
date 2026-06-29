<?php

namespace Velox\MailSendVx\Repository;

use Doctrine\DBAL\ParameterType;

class MailSendVxAbandonedCartRepository extends AbstractMailSendVxRepository
{
    /**
     * @return array<string, mixed>|false
     */
    public function findByCartId(int $idCart)
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_abandoned_cart'))
            ->where('id_cart = :idCart')
            ->setParameter('idCart', $idCart, ParameterType::INTEGER);

        $result = $queryBuilder->execute()->fetchAssociative();

        return $result ?: false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRecoverableAbandonedCarts(int $limit = 100): array
    {
        $sql = sprintf(
            'SELECT state.*, cart.date_upd AS cart_date_upd
             FROM `%s` state
             INNER JOIN `%s` cart ON cart.id_cart = state.id_cart
             LEFT JOIN `%s` orders ON orders.id_cart = cart.id_cart
             WHERE state.status = :status
             AND (
               orders.id_order IS NOT NULL
               OR cart.date_upd > state.abandoned_at
             )
             ORDER BY state.id_mailsendvx_abandoned_cart ASC
             LIMIT %d',
            $this->getTableName('mailsendvx_abandoned_cart'),
            $this->databasePrefix . 'cart',
            $this->databasePrefix . 'orders',
            max(1, $limit)
        );

        return $this->connection->executeQuery($sql, [
            'status' => 'abandoned',
        ], [
            'status' => ParameterType::STRING,
        ])->fetchAllAssociative();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findEligibleCarts(
        string $cutoffDate,
        int $limit = 100,
        bool $requireCustomer = true,
        bool $requireProducts = true
    ): array {
        $conditions = [
            'orders.id_order IS NULL',
            'cart.date_upd <= :cutoffDate',
        ];

        if ($requireCustomer) {
            $conditions[] = 'customer.id_customer IS NOT NULL';
            $conditions[] = 'customer.email IS NOT NULL';
            $conditions[] = 'customer.email != ""';
        }

        $having = $requireProducts ? 'HAVING COALESCE(SUM(cart_product.quantity), 0) > 0' : '';

        $sql = sprintf(
            'SELECT
                cart.id_cart,
                cart.id_shop,
                cart.id_lang,
                cart.id_currency,
                cart.id_customer,
                cart.date_upd,
                customer.firstname,
                customer.lastname,
                customer.email,
                COALESCE(SUM(cart_product.quantity), 0) AS items_count
             FROM `%s` cart
             LEFT JOIN `%s` customer ON customer.id_customer = cart.id_customer
             LEFT JOIN `%s` cart_product ON cart_product.id_cart = cart.id_cart
             LEFT JOIN `%s` orders ON orders.id_cart = cart.id_cart
             WHERE %s
             GROUP BY cart.id_cart, cart.id_shop, cart.id_lang, cart.id_currency, cart.id_customer, cart.date_upd, customer.firstname, customer.lastname, customer.email
             %s
             ORDER BY cart.date_upd ASC
             LIMIT %d',
            $this->databasePrefix . 'cart',
            $this->databasePrefix . 'customer',
            $this->databasePrefix . 'cart_product',
            $this->databasePrefix . 'orders',
            implode(' AND ', $conditions),
            $having,
            max(1, $limit)
        );

        return $this->connection->executeQuery($sql, [
            'cutoffDate' => $cutoffDate,
        ], [
            'cutoffDate' => ParameterType::STRING,
        ])->fetchAllAssociative();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveState(array $data): bool
    {
        $existing = $this->findByCartId((int) $data['id_cart']);
        $now = date('Y-m-d H:i:s');
        $row = [
            'id_cart' => (int) $data['id_cart'],
            'id_customer' => (int) ($data['id_customer'] ?? 0),
            'email' => $data['email'] ?? null,
            'id_shop' => (int) ($data['id_shop'] ?? $this->getCurrentShopId()),
            'id_lang' => (int) ($data['id_lang'] ?? 0),
            'status' => (string) ($data['status'] ?? 'active'),
            'cart_snapshot' => isset($data['cart_snapshot']) ? (string) json_encode($data['cart_snapshot']) : null,
            'last_activity_at' => $data['last_activity_at'] ?? null,
            'abandoned_at' => $data['abandoned_at'] ?? null,
            'recovered_at' => $data['recovered_at'] ?? null,
            'last_event_hash' => $data['last_event_hash'] ?? null,
            'date_upd' => $now,
        ];

        if ($existing) {
            $this->connection->update(
                $this->getTableName('mailsendvx_abandoned_cart'),
                $row,
                ['id_cart' => (int) $data['id_cart']]
            );

            return true;
        }

        $row['date_add'] = $now;
        $this->connection->insert($this->getTableName('mailsendvx_abandoned_cart'), $row);

        return true;
    }
}
