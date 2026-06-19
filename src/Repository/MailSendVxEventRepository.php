<?php

namespace Velox\MailSendVx\Repository;

class MailSendVxEventRepository extends AbstractMailSendVxRepository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function add(
        string $eventName,
        array $payload = [],
        ?string $objectType = null,
        ?string $objectId = null,
        string $status = 'captured',
        ?int $idShop = null
    ): bool {
        $this->connection->insert($this->getTableName('mailsendvx_event'), [
            'id_shop' => (int) ($idShop ?: $this->getCurrentShopId()),
            'event_name' => $eventName,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'payload' => (string) json_encode($payload),
            'status' => $status,
            'date_add' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 20): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_event'))
            ->orderBy('date_add', 'DESC')
            ->setMaxResults(max(1, min(100, $limit)));

        return $queryBuilder->execute()->fetchAllAssociative();
    }
}
