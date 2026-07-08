<?php

namespace Velox\MailSendVx\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class MailSendVxLogQueryBuilder extends AbstractDoctrineQueryBuilder
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
        $qb->select('l.id_mailsendvx_log, l.id_shop, l.id_template, l.id_queue, l.event_name, l.recipient, l.status, l.message, l.date_add')
            ->addSelect('SUBSTRING(COALESCE(l.payload, \'\'), 1, 120) AS payload_excerpt');

        $this->searchCriteriaApplicator
            ->applySorting($searchCriteria, $qb)
            ->applyPagination($searchCriteria, $qb);

        return $qb;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        return $this->getBaseQueryBuilder($searchCriteria->getFilters())
            ->select('COUNT(l.id_mailsendvx_log)');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function getBaseQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->from($this->dbPrefix . 'mailsendvx_log', 'l');

        foreach ($filters as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($name === 'date_add' && is_array($value)) {
                if (!empty($value['from'])) {
                    $qb->andWhere('l.date_add >= :date_from')
                        ->setParameter('date_from', sprintf('%s 00:00:00', $value['from']));
                }
                if (!empty($value['to'])) {
                    $qb->andWhere('l.date_add <= :date_to')
                        ->setParameter('date_to', sprintf('%s 23:59:59', $value['to']));
                }

                continue;
            }

            if ($name === 'status') {
                $qb->andWhere('l.status = :status')
                    ->setParameter('status', $value);

                continue;
            }

            if ($name === 'id_mailsendvx_log') {
                $qb->andWhere('l.id_mailsendvx_log = :id_mailsendvx_log')
                    ->setParameter('id_mailsendvx_log', (int) $value);

                continue;
            }

            $qb->andWhere(sprintf('l.%s LIKE :%s', $name, $name))
                ->setParameter($name, '%' . $this->escapePercent((string) $value) . '%');
        }

        return $qb;
    }
}

