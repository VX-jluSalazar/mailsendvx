<?php

namespace Velox\MailSendVx\Repository;

use Doctrine\DBAL\ParameterType;

class MailSendVxQueueRepository extends AbstractMailSendVxRepository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function scheduleJob(
        string $eventName,
        string $recipient,
        array $payload,
        string $scheduledAt,
        ?int $idTemplate = null,
        ?int $idFlow = null,
        ?int $flowVersion = null,
        ?string $stepId = null,
        ?int $idShop = null,
        int $maxAttempts = 3
    ): bool {
        $now = date('Y-m-d H:i:s');
        $initialStatus = strtotime($scheduledAt) <= strtotime($now) ? 'pending' : 'scheduled';
        $encodedPayload = (string) json_encode($payload);

        $this->connection->insert($this->getTableName('mailsendvx_queue'), [
            'id_shop' => (int) ($idShop ?: $this->getCurrentShopId()),
            'id_template' => $idTemplate ? (int) $idTemplate : null,
            'id_flow' => $idFlow ? (int) $idFlow : null,
            'flow_version' => max(1, (int) ($flowVersion ?: 1)),
            'step_id' => $stepId ?: null,
            'event_name' => $eventName,
            'recipient' => $recipient,
            'payload' => $encodedPayload,
            'payload_json' => $encodedPayload,
            'status' => $initialStatus,
            'attempts' => 0,
            'max_attempts' => max(1, $maxAttempts),
            'scheduled_at' => $scheduledAt,
            'processed_at' => null,
            'locked_at' => null,
            'lock_token' => null,
            'last_error' => null,
            'cancel_reason' => null,
            'date_add' => $now,
            'date_upd' => $now,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 20): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_queue'))
            ->orderBy('date_add', 'DESC')
            ->setMaxResults(max(1, min(100, $limit)));

        return $queryBuilder->execute()->fetchAllAssociative();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findDueJobs(int $limit = 50): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_queue'))
            ->where('(status = :scheduledStatus OR status = :pendingStatus)')
            ->andWhere('scheduled_at <= :scheduledAt')
            ->orderBy('scheduled_at', 'ASC')
            ->addOrderBy('id_mailsendvx_queue', 'ASC')
            ->setMaxResults(max(1, min(500, $limit)))
            ->setParameter('scheduledStatus', 'scheduled')
            ->setParameter('pendingStatus', 'pending')
            ->setParameter('scheduledAt', date('Y-m-d H:i:s'));

        return $queryBuilder->execute()->fetchAllAssociative();
    }

    public function markAsProcessing(int $idQueue, string $lockToken): bool
    {
        $updatedRows = $this->connection->update(
            $this->getTableName('mailsendvx_queue'),
            [
                'status' => 'processing',
                'locked_at' => date('Y-m-d H:i:s'),
                'lock_token' => $lockToken,
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            [
                'id_mailsendvx_queue' => $idQueue,
            ]
        );

        return $updatedRows > 0;
    }

    public function markAsSent(int $idQueue): bool
    {
        return $this->updateQueueState($idQueue, [
            'status' => 'sent',
            'processed_at' => date('Y-m-d H:i:s'),
            'locked_at' => null,
            'lock_token' => null,
            'last_error' => null,
            'date_upd' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markAsSkipped(int $idQueue, string $message): bool
    {
        return $this->updateQueueState($idQueue, [
            'status' => 'skipped',
            'processed_at' => date('Y-m-d H:i:s'),
            'locked_at' => null,
            'lock_token' => null,
            'last_error' => $message,
            'date_upd' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markAsFailed(int $idQueue, int $attempts, int $maxAttempts, string $message): bool
    {
        $isTerminal = $attempts >= $maxAttempts;

        return $this->updateQueueState($idQueue, [
            'status' => $isTerminal ? 'failed' : 'pending',
            'attempts' => $attempts,
            'processed_at' => $isTerminal ? date('Y-m-d H:i:s') : null,
            'locked_at' => null,
            'lock_token' => null,
            'last_error' => $message,
            'date_upd' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findById(int $idQueue)
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_queue'))
            ->where('id_mailsendvx_queue = :idQueue')
            ->setParameter('idQueue', $idQueue, ParameterType::INTEGER);

        $result = $queryBuilder->execute()->fetchAssociative();

        return $result ?: false;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateQueueState(int $idQueue, array $data): bool
    {
        $updatedRows = $this->connection->update(
            $this->getTableName('mailsendvx_queue'),
            $data,
            ['id_mailsendvx_queue' => $idQueue]
        );

        return $updatedRows > 0;
    }
}
