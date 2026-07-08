<?php

namespace Velox\MailSendVx\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class MailSendVxEventQueryBuilder extends AbstractDoctrineQueryBuilder
{
    /**
     * @var DoctrineSearchCriteriaApplicatorInterface
     */
    private $searchCriteriaApplicator;

    public function __construct(
        Connection $connection,
        string $dbPrefix,
        DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator
    ) {
        parent::__construct($connection, $dbPrefix);
        $this->searchCriteriaApplicator = $searchCriteriaApplicator;
    }

    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getBaseQueryBuilder($searchCriteria->getFilters());
        $qb->select('e.id_mailsendvx_event, e.id_shop, e.event_name, e.object_type, e.object_id, e.status, e.date_add')
            ->addSelect('SUBSTRING(COALESCE(e.payload, \'\'), 1, 120) AS payload_excerpt');

        $this->searchCriteriaApplicator
            ->applySorting($searchCriteria, $qb)
            ->applyPagination($searchCriteria, $qb);

        return $qb;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        return $this->getBaseQueryBuilder($searchCriteria->getFilters())
            ->select('COUNT(e.id_mailsendvx_event)');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function getBaseQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->from($this->dbPrefix . 'mailsendvx_event', 'e');

        foreach ($filters as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($name === 'date_add' && is_array($value)) {
                if (!empty($value['from'])) {
                    $qb->andWhere('e.date_add >= :date_from')
                        ->setParameter('date_from', sprintf('%s 00:00:00', $value['from']));
                }
                if (!empty($value['to'])) {
                    $qb->andWhere('e.date_add <= :date_to')
                        ->setParameter('date_to', sprintf('%s 23:59:59', $value['to']));
                }

                continue;
            }

            if ($name === 'status') {
                $qb->andWhere('e.status = :status')
                    ->setParameter('status', $value);

                continue;
            }

            if ($name === 'id_mailsendvx_event' || $name === 'id_shop') {
                $qb->andWhere(sprintf('e.%s = :%s', $name, $name))
                    ->setParameter($name, (int) $value);

                continue;
            }

            $qb->andWhere(sprintf('e.%s LIKE :%s', $name, $name))
                ->setParameter($name, '%' . $this->escapePercent((string) $value) . '%');
        }

        return $qb;
    }
}

