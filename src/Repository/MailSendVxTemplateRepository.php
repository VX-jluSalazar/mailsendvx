<?php

namespace Velox\MailSendVx\Repository;

use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use Velox\MailSendVx\ModuleConstants;

class MailSendVxTemplateRepository extends AbstractMailSendVxRepository
{
    /**
     * @return array<string, mixed>|false
     */
    public function findByScope(?string $eventName, int $idLang, int $idShop, ?int $excludeId = null)
    {
        if ($eventName === null || $eventName === '') {
            return false;
        }

        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_template'))
            ->where('event_name = :eventName')
            ->andWhere('id_lang = :idLang')
            ->andWhere('id_shop = :idShop')
            ->setParameter('eventName', $eventName)
            ->setParameter('idLang', $idLang, ParameterType::INTEGER)
            ->setParameter('idShop', $idShop, ParameterType::INTEGER);

        if ($excludeId) {
            $queryBuilder
                ->andWhere('id_mailsendvx_template != :excludeId')
                ->setParameter('excludeId', $excludeId, ParameterType::INTEGER);
        }

        $queryBuilder->orderBy('date_upd', 'DESC')
            ->addOrderBy('id_mailsendvx_template', 'DESC');

        $result = $queryBuilder->execute()->fetchAssociative();

        return $result ?: false;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findActiveByEvent(string $eventName, int $idLang, int $idShop)
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_template'))
            ->where('event_name = :eventName')
            ->andWhere('id_lang IN (:allLanguages, :idLang)')
            ->andWhere('id_shop IN (:allShops, :idShop)')
            ->andWhere('active = :active')
            ->orderBy('id_lang', 'DESC')
            ->addOrderBy('id_shop', 'DESC')
            ->addOrderBy('date_upd', 'DESC')
            ->setParameter('eventName', $eventName)
            ->setParameter('allLanguages', 0, ParameterType::INTEGER)
            ->setParameter('idLang', $idLang, ParameterType::INTEGER)
            ->setParameter('allShops', 0, ParameterType::INTEGER)
            ->setParameter('idShop', $idShop, ParameterType::INTEGER)
            ->setParameter('active', 1, ParameterType::INTEGER);

        $result = $queryBuilder->execute()->fetchAssociative();

        return $result ?: false;
    }

    public function countAll(): int
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('COUNT(*)')
            ->from($this->getTableName('mailsendvx_template'));

        return (int) $queryBuilder->execute()->fetchOne();
    }

    public function hasActiveByEvent(string $eventName, int $idLang, int $idShop): bool
    {
        return (bool) $this->findActiveByEvent($eventName, $idLang, $idShop);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(int $limit = 100): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_template'))
            ->orderBy('date_upd', 'DESC')
            ->setMaxResults(max(1, min(500, $limit)));

        return $queryBuilder->execute()->fetchAllAssociative();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findById(int $idTemplate)
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_template'))
            ->where('id_mailsendvx_template = :idTemplate')
            ->setParameter('idTemplate', $idTemplate, ParameterType::INTEGER);

        $result = $queryBuilder->execute()->fetchAssociative();

        return $result ?: false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data, ?int $idTemplate = null): bool
    {
        $now = date('Y-m-d H:i:s');
        $eventName = trim((string) ($data['event_name'] ?? ''));
        $eventName = $eventName !== '' ? $eventName : null;
        $contextType = trim((string) ($data['context_type'] ?? ''));
        $idLang = (int) $data['id_lang'];
        $idShop = (int) $data['id_shop'];

        if ($contextType === '' || !ModuleConstants::isSupportedContextType($contextType)) {
            throw new InvalidArgumentException('Template context_type is required and must be supported.');
        }

        $eventContextType = ModuleConstants::getEventContextType($eventName);
        if ($eventContextType !== null && $eventContextType !== $contextType) {
            throw new InvalidArgumentException('Template event_name is not compatible with the selected context_type.');
        }

        if (!$idTemplate && $eventName !== null) {
            $existing = $this->findByScope($eventName, $idLang, $idShop);
            if ($existing) {
                $idTemplate = (int) $existing['id_mailsendvx_template'];
            }
        }

        $row = [
            'id_shop' => $idShop,
            'id_lang' => $idLang,
            'event_name' => $eventName,
            'context_type' => $contextType,
            'name' => (string) $data['name'],
            'subject' => (string) $data['subject'],
            'mail_template' => (string) $data['mail_template'],
            'html_content' => (string) $data['html_content'],
            'text_content' => (string) $data['text_content'],
            'json_design' => $data['json_design'] ?? null,
            'provider' => (string) $data['provider'],
            'active' => (int) !empty($data['active']),
            'date_upd' => $now,
        ];

        $this->connection->beginTransaction();

        try {
            if ($idTemplate) {
                $this->connection->update(
                    $this->getTableName('mailsendvx_template'),
                    $row,
                    ['id_mailsendvx_template' => $idTemplate]
                );

                if (!empty($data['active']) && $eventName !== null) {
                    $this->deactivateByScope($eventName, $idLang, $idShop, (int) $idTemplate);
                }

                $this->connection->commit();

                return true;
            }

            $row['date_add'] = $now;
            $this->connection->insert($this->getTableName('mailsendvx_template'), $row);
            $insertedId = (int) $this->connection->lastInsertId();

            if (!empty($data['active']) && $eventName !== null) {
                $this->deactivateByScope($eventName, $idLang, $idShop, $insertedId);
            }

            $this->connection->commit();

            return true;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }

    public function delete(int $idTemplate): bool
    {
        $this->connection->delete(
            $this->getTableName('mailsendvx_template'),
            ['id_mailsendvx_template' => $idTemplate]
        );

        return true;
    }

    private function deactivateByScope(string $eventName, int $idLang, int $idShop, int $excludeId): bool
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->update($this->getTableName('mailsendvx_template'))
            ->set('active', ':active')
            ->set('date_upd', ':dateUpd')
            ->where('event_name = :eventName')
            ->andWhere('id_lang = :idLang')
            ->andWhere('id_shop = :idShop')
            ->andWhere('id_mailsendvx_template != :excludeId')
            ->setParameter('active', 0, ParameterType::INTEGER)
            ->setParameter('dateUpd', date('Y-m-d H:i:s'))
            ->setParameter('eventName', $eventName)
            ->setParameter('idLang', $idLang, ParameterType::INTEGER)
            ->setParameter('idShop', $idShop, ParameterType::INTEGER)
            ->setParameter('excludeId', $excludeId, ParameterType::INTEGER)
            ->execute();

        return true;
    }
}
