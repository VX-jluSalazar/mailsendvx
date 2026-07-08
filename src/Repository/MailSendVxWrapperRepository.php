<?php

namespace Velox\MailSendVx\Repository;

use Doctrine\DBAL\ParameterType;

class MailSendVxWrapperRepository extends AbstractMailSendVxRepository
{
    /**
     * @return array<string, mixed>|false
     */
    public function findByScope(string $name, int $idLang, int $idShop)
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_wrapper'))
            ->where('name = :name')
            ->andWhere('id_lang = :idLang')
            ->andWhere('id_shop = :idShop')
            ->setParameter('name', $name)
            ->setParameter('idLang', $idLang, ParameterType::INTEGER)
            ->setParameter('idShop', $idShop, ParameterType::INTEGER)
            ->orderBy('date_upd', 'DESC')
            ->addOrderBy('id_mailsendvx_wrapper', 'DESC');

        $result = $queryBuilder->execute()->fetchAssociative();

        return $result ?: false;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findBestMatch(string $name, int $idLang, int $idShop)
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_wrapper'))
            ->where('name = :name')
            ->andWhere('id_lang = :idLang')
            ->andWhere('id_shop IN (:globalShop, :idShop)')
            ->setParameter('name', $name)
            ->setParameter('idLang', $idLang, ParameterType::INTEGER)
            ->setParameter('globalShop', 0, ParameterType::INTEGER)
            ->setParameter('idShop', $idShop, ParameterType::INTEGER)
            ->orderBy('id_shop', 'DESC')
            ->addOrderBy('date_upd', 'DESC')
            ->addOrderBy('id_mailsendvx_wrapper', 'DESC');

        $result = $queryBuilder->execute()->fetchAssociative();

        return $result ?: false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllByShop(int $idShop, int $limit = 500): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_wrapper'))
            ->where('id_shop IN (:globalShop, :idShop)')
            ->setParameter('globalShop', 0, ParameterType::INTEGER)
            ->setParameter('idShop', $idShop, ParameterType::INTEGER)
            ->orderBy('name', 'ASC')
            ->addOrderBy('id_lang', 'ASC')
            ->addOrderBy('id_shop', 'DESC')
            ->addOrderBy('date_upd', 'DESC')
            ->setMaxResults(max(1, min(1000, $limit)));

        return $queryBuilder->execute()->fetchAllAssociative();
    }

    /**
     * @return array<int, string>
     */
    public function findNamesByShop(int $idShop): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('DISTINCT name')
            ->from($this->getTableName('mailsendvx_wrapper'))
            ->where('id_shop IN (:globalShop, :idShop)')
            ->setParameter('globalShop', 0, ParameterType::INTEGER)
            ->setParameter('idShop', $idShop, ParameterType::INTEGER)
            ->orderBy('name', 'ASC');

        $names = $queryBuilder->execute()->fetchFirstColumn();

        return array_values(array_filter(array_map('strval', $names)));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data, ?int $idWrapper = null): bool
    {
        $now = date('Y-m-d H:i:s');
        $name = trim((string) ($data['name'] ?? ''));
        $idLang = (int) ($data['id_lang'] ?? 0);
        $idShop = (int) ($data['id_shop'] ?? $this->getCurrentShopId());

        if (!$idWrapper) {
            $existing = $this->findByScope($name, $idLang, $idShop);
            if ($existing) {
                $idWrapper = (int) $existing['id_mailsendvx_wrapper'];
            }
        }

        $row = [
            'id_shop' => $idShop,
            'id_lang' => $idLang,
            'name' => $name,
            'html_content' => (string) ($data['html_content'] ?? ''),
            'text_content' => (string) ($data['text_content'] ?? ''),
            'date_upd' => $now,
        ];

        if ($idWrapper) {
            $this->connection->update(
                $this->getTableName('mailsendvx_wrapper'),
                $row,
                ['id_mailsendvx_wrapper' => $idWrapper]
            );

            return true;
        }

        $row['date_add'] = $now;
        $this->connection->insert($this->getTableName('mailsendvx_wrapper'), $row);

        return true;
    }

    public function delete(int $idWrapper, int $idShop): bool
    {
        $this->connection->delete(
            $this->getTableName('mailsendvx_wrapper'),
            [
                'id_mailsendvx_wrapper' => $idWrapper,
                'id_shop' => $idShop,
            ]
        );

        return true;
    }
}
