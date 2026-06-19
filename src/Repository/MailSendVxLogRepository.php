<?php

namespace Velox\MailSendVx\Repository;

class MailSendVxLogRepository extends AbstractMailSendVxRepository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function add(
        string $eventName,
        string $status,
        ?string $recipient = null,
        ?int $idTemplate = null,
        ?int $idQueue = null,
        array $payload = [],
        ?string $message = null,
        ?int $idShop = null
    ): bool {
        $this->connection->insert($this->getTableName('mailsendvx_log'), [
            'id_shop' => (int) ($idShop ?: $this->getCurrentShopId()),
            'id_template' => $idTemplate ? (int) $idTemplate : null,
            'id_queue' => $idQueue ? (int) $idQueue : null,
            'event_name' => $eventName,
            'recipient' => $recipient,
            'status' => $status,
            'payload' => (string) json_encode($payload),
            'message' => $message,
            'date_add' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 10): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_log'))
            ->orderBy('date_add', 'DESC')
            ->setMaxResults(max(1, min(100, $limit)));

        return $queryBuilder->execute()->fetchAllAssociative();
    }
}
