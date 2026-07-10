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

        if ($this->hasEquivalentJob(
            $eventName,
            $recipient,
            $encodedPayload,
            $scheduledAt,
            $idTemplate,
            $idFlow,
            $flowVersion,
            $stepId,
            $idShop
        )) {
            return false;
        }

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveJobsByEvent(string $eventName, ?int $idShop = null, int $limit = 500): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_queue'))
            ->where('event_name = :eventName')
            ->andWhere('status IN (:scheduledStatus, :pendingStatus)')
            ->orderBy('scheduled_at', 'ASC')
            ->addOrderBy('id_mailsendvx_queue', 'ASC')
            ->setMaxResults(max(1, min(2000, $limit)))
            ->setParameter('eventName', $eventName)
            ->setParameter('scheduledStatus', 'scheduled')
            ->setParameter('pendingStatus', 'pending');

        if ($idShop !== null) {
            $queryBuilder
                ->andWhere('id_shop IN (:allShops, :idShop)')
                ->setParameter('allShops', 0, ParameterType::INTEGER)
                ->setParameter('idShop', $idShop, ParameterType::INTEGER);
        }

        return $queryBuilder->execute()->fetchAllAssociative();
    }

    public function markAsProcessing(int $idQueue, string $lockToken): bool
    {
        $updatedRows = $this->connection->executeStatement(
            'UPDATE `' . $this->getTableName('mailsendvx_queue') . '`
            SET `status` = :processingStatus, `locked_at` = :lockedAt, `lock_token` = :lockToken, `date_upd` = :dateUpd
            WHERE `id_mailsendvx_queue` = :idQueue
              AND `status` IN (:scheduledStatus, :pendingStatus)
              AND (`lock_token` IS NULL OR `lock_token` = "")',
            [
                'processingStatus' => 'processing',
                'lockedAt' => date('Y-m-d H:i:s'),
                'lockToken' => $lockToken,
                'dateUpd' => date('Y-m-d H:i:s'),
                'idQueue' => $idQueue,
                'scheduledStatus' => 'scheduled',
                'pendingStatus' => 'pending',
            ]
        );

        return $updatedRows > 0;
    }

    public function markAsSent(int $idQueue, ?string $lockToken = null): bool
    {
        return $this->updateQueueState($idQueue, [
            'status' => 'sent',
            'processed_at' => date('Y-m-d H:i:s'),
            'locked_at' => null,
            'lock_token' => null,
            'last_error' => null,
            'date_upd' => date('Y-m-d H:i:s'),
        ], $lockToken);
    }

    public function markAsSkipped(int $idQueue, string $message, ?string $lockToken = null): bool
    {
        return $this->updateQueueState($idQueue, [
            'status' => 'skipped',
            'processed_at' => date('Y-m-d H:i:s'),
            'locked_at' => null,
            'lock_token' => null,
            'last_error' => $message,
            'date_upd' => date('Y-m-d H:i:s'),
        ], $lockToken);
    }

    public function markAsCancelled(int $idQueue, string $message, ?string $lockToken = null): bool
    {
        return $this->updateQueueState($idQueue, [
            'status' => 'cancelled',
            'processed_at' => date('Y-m-d H:i:s'),
            'locked_at' => null,
            'lock_token' => null,
            'last_error' => $message,
            'cancel_reason' => $message,
            'date_upd' => date('Y-m-d H:i:s'),
        ], $lockToken);
    }

    public function cancelPendingJob(int $idQueue, string $message): bool
    {
        return $this->updateQueueState($idQueue, [
            'status' => 'cancelled',
            'processed_at' => date('Y-m-d H:i:s'),
            'locked_at' => null,
            'lock_token' => null,
            'last_error' => $message,
            'cancel_reason' => $message,
            'date_upd' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markAsFailed(
        int $idQueue,
        int $attempts,
        int $maxAttempts,
        string $message,
        ?string $retryAt = null,
        ?string $lockToken = null
    ): bool
    {
        $isTerminal = $attempts >= $maxAttempts;
        $data = [
            'status' => $isTerminal ? 'failed' : 'scheduled',
            'attempts' => $attempts,
            'processed_at' => $isTerminal ? date('Y-m-d H:i:s') : null,
            'locked_at' => null,
            'lock_token' => null,
            'last_error' => $message,
            'date_upd' => date('Y-m-d H:i:s'),
        ];

        if (!$isTerminal && $retryAt) {
            $data['scheduled_at'] = $retryAt;
        }

        return $this->updateQueueState($idQueue, $data, $lockToken);
    }

    public function releaseExpiredLocks(int $timeoutMinutes = 30): int
    {
        $timeoutMinutes = max(1, $timeoutMinutes);
        $cutoff = date('Y-m-d H:i:s', strtotime(sprintf('-%d minutes', $timeoutMinutes)));

        return $this->connection->executeStatement(
            'UPDATE `' . $this->getTableName('mailsendvx_queue') . '`
            SET `status` = :pendingStatus, `locked_at` = NULL, `lock_token` = NULL, `date_upd` = :dateUpd
            WHERE `status` = :processingStatus
              AND `locked_at` IS NOT NULL
              AND `locked_at` <= :cutoff',
            [
                'pendingStatus' => 'pending',
                'dateUpd' => date('Y-m-d H:i:s'),
                'processingStatus' => 'processing',
                'cutoff' => $cutoff,
            ]
        );
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

    public function clearTerminalJobs(): int
    {
        return $this->connection->executeStatement(
            'DELETE FROM `' . $this->getTableName('mailsendvx_queue') . '`
            WHERE `status` IN (:sentStatus, :failedStatus, :cancelledStatus, :skippedStatus)',
            [
                'sentStatus' => 'sent',
                'failedStatus' => 'failed',
                'cancelledStatus' => 'cancelled',
                'skippedStatus' => 'skipped',
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateQueueState(int $idQueue, array $data, ?string $lockToken = null): bool
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->update($this->getTableName('mailsendvx_queue'));

        foreach ($data as $column => $value) {
            $parameterName = str_replace('.', '_', $column);
            $queryBuilder->set('`' . $column . '`', ':' . $parameterName);
            $queryBuilder->setParameter($parameterName, $value);
        }

        $queryBuilder
            ->where('id_mailsendvx_queue = :idQueue')
            ->setParameter('idQueue', $idQueue, ParameterType::INTEGER);

        if ($lockToken !== null) {
            $queryBuilder
                ->andWhere('status = :processingStatus')
                ->andWhere('lock_token = :lockToken')
                ->setParameter('processingStatus', 'processing')
                ->setParameter('lockToken', $lockToken);
        }

        $updatedRows = $queryBuilder->execute();

        return $updatedRows > 0;
    }

    private function hasEquivalentJob(
        string $eventName,
        string $recipient,
        string $payloadJson,
        string $scheduledAt,
        ?int $idTemplate,
        ?int $idFlow,
        ?int $flowVersion,
        ?string $stepId,
        ?int $idShop
    ): bool {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('1')
            ->from($this->getTableName('mailsendvx_queue'))
            ->where('id_shop = :idShop')
            ->andWhere('event_name = :eventName')
            ->andWhere('recipient = :recipient')
            ->andWhere('scheduled_at = :scheduledAt')
            ->andWhere('payload_json = :payloadJson')
            ->setMaxResults(1)
            ->setParameter('idShop', (int) ($idShop ?: $this->getCurrentShopId()), ParameterType::INTEGER)
            ->setParameter('eventName', $eventName)
            ->setParameter('recipient', $recipient)
            ->setParameter('scheduledAt', $scheduledAt)
            ->setParameter('payloadJson', $payloadJson);

        if ($idTemplate === null) {
            $queryBuilder->andWhere('id_template IS NULL');
        } else {
            $queryBuilder
                ->andWhere('id_template = :idTemplate')
                ->setParameter('idTemplate', $idTemplate, ParameterType::INTEGER);
        }

        if ($idFlow === null) {
            $queryBuilder->andWhere('id_flow IS NULL');
        } else {
            $queryBuilder
                ->andWhere('id_flow = :idFlow')
                ->andWhere('flow_version = :flowVersion')
                ->setParameter('idFlow', $idFlow, ParameterType::INTEGER)
                ->setParameter('flowVersion', max(1, (int) ($flowVersion ?: 1)), ParameterType::INTEGER);
        }

        if ($stepId === null || $stepId === '') {
            $queryBuilder->andWhere('(step_id IS NULL OR step_id = \'\')');
        } else {
            $queryBuilder
                ->andWhere('step_id = :stepId')
                ->setParameter('stepId', $stepId);
        }

        return (bool) $queryBuilder->execute()->fetchOne();
    }
}
