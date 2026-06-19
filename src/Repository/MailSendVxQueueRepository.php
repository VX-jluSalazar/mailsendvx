<?php

namespace Velox\MailSendVx\Repository;

class MailSendVxQueueRepository extends AbstractMailSendVxRepository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function schedule(
        string $eventName,
        string $recipient,
        array $payload,
        string $scheduledAt,
        ?int $idTemplate = null,
        ?int $idFlow = null,
        ?int $idShop = null
    ): bool {
        $this->connection->insert($this->getTableName('mailsendvx_queue'), [
            'id_shop' => (int) ($idShop ?: $this->getCurrentShopId()),
            'id_template' => $idTemplate ? (int) $idTemplate : null,
            'id_flow' => $idFlow ? (int) $idFlow : null,
            'event_name' => $eventName,
            'recipient' => $recipient,
            'payload' => (string) json_encode($payload),
            'status' => 'scheduled',
            'attempts' => 0,
            'scheduled_at' => $scheduledAt,
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function countByStatus(string $status): int
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('COUNT(*)')
            ->from($this->getTableName('mailsendvx_queue'))
            ->where('status = :status')
            ->setParameter('status', $status);

        return (int) $queryBuilder->execute()->fetchOne();
    }
}
