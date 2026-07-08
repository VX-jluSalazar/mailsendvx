<?php

namespace Velox\MailSendVx\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class MailSendVxQueueQueryBuilder extends AbstractDoctrineQueryBuilder
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
        $qb
            ->select('q.id_mailsendvx_queue, q.id_template, q.id_flow, q.step_id, q.event_name, q.recipient, q.status, q.scheduled_at, q.last_error, q.cancel_reason')
            ->addSelect('CONCAT(\'#\', COALESCE(q.id_flow, \'-\'), \' / \', COALESCE(NULLIF(q.step_id, \'\'), \'-\')) AS flow_step')
            ->addSelect('CONCAT(\'T#\', COALESCE(q.id_template, \'-\')) AS template_reference')
            ->addSelect('SUBSTRING(COALESCE(NULLIF(q.last_error, \'\'), q.cancel_reason, \'\'), 1, 120) AS last_error_excerpt')
            ->addSelect('CASE WHEN q.status IN (\'pending\', \'scheduled\') THEN 1 ELSE 0 END AS can_cancel')
            ->addSelect('CASE WHEN q.status IN (\'pending\', \'scheduled\') THEN 0 ELSE 1 END AS bulk_disabled');

        $this->searchCriteriaApplicator
            ->applySorting($searchCriteria, $qb)
            ->applyPagination($searchCriteria, $qb);

        return $qb;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        return $this->getBaseQueryBuilder($searchCriteria->getFilters())
            ->select('COUNT(q.id_mailsendvx_queue)');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function getBaseQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->from($this->dbPrefix . 'mailsendvx_queue', 'q');

        foreach ($filters as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($name === 'scheduled_at' && is_array($value)) {
                if (!empty($value['from'])) {
                    $qb->andWhere('q.scheduled_at >= :scheduled_from')
                        ->setParameter('scheduled_from', sprintf('%s 00:00:00', $value['from']));
                }
                if (!empty($value['to'])) {
                    $qb->andWhere('q.scheduled_at <= :scheduled_to')
                        ->setParameter('scheduled_to', sprintf('%s 23:59:59', $value['to']));
                }

                continue;
            }

            if ($name === 'status') {
                $qb->andWhere('q.status = :status')
                    ->setParameter('status', $value);

                continue;
            }

            if ($name === 'id_mailsendvx_queue') {
                $qb->andWhere('q.id_mailsendvx_queue = :id_mailsendvx_queue')
                    ->setParameter('id_mailsendvx_queue', (int) $value);

                continue;
            }

            $qb->andWhere(sprintf('q.%s LIKE :%s', $name, $name))
                ->setParameter($name, '%' . $this->escapePercent((string) $value) . '%');
        }

        return $qb;
    }
}

