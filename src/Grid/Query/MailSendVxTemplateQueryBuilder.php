<?php

namespace Velox\MailSendVx\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class MailSendVxTemplateQueryBuilder extends AbstractDoctrineQueryBuilder
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
            ->select('t.id_mailsendvx_template, t.name, t.context_type, t.event_name, t.subject, t.id_lang, t.id_shop, t.active')
            ->addSelect("CASE t.context_type WHEN 'order' THEN 'Pedido' WHEN 'cart' THEN 'Carrito' WHEN 'customer' THEN 'Cliente' WHEN 'newsletter' THEN 'Newsletter' ELSE t.context_type END AS context_label")
            ->addSelect("CASE WHEN t.event_name IS NULL OR t.event_name = '' THEN 'Reusable' ELSE 'Instantánea' END AS usage_label")
            ->addSelect("CASE WHEN t.event_name IS NULL OR t.event_name = '' THEN 'reusable' ELSE 'instant' END AS usage_kind")
            ->addSelect("CASE WHEN t.event_name IS NULL OR t.event_name = '' THEN 'Reusable en flows' ELSE t.event_name END AS event_label")
            ->addSelect("CASE WHEN t.id_lang = 0 THEN 'All languages' ELSE CONCAT(COALESCE(NULLIF(l.name, ''), CONCAT('#', t.id_lang)), ' (#', t.id_lang, ')') END AS language_label")
            ->addSelect("CASE WHEN t.id_shop = 0 THEN 'All shops' ELSE CONCAT(COALESCE(NULLIF(s.name, ''), CONCAT('#', t.id_shop)), ' (#', t.id_shop, ')') END AS shop_label")
            ->addSelect("CASE WHEN t.active = 1 THEN 'Activa' ELSE 'Inactiva' END AS active_label")
            ->leftJoin('t', $this->dbPrefix . 'lang', 'l', 'l.id_lang = t.id_lang')
            ->leftJoin('t', $this->dbPrefix . 'shop', 's', 's.id_shop = t.id_shop');

        $this->searchCriteriaApplicator
            ->applySorting($searchCriteria, $qb)
            ->applyPagination($searchCriteria, $qb);

        return $qb;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        return $this->getBaseQueryBuilder($searchCriteria->getFilters())
            ->select('COUNT(t.id_mailsendvx_template)');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function getBaseQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->from($this->dbPrefix . 'mailsendvx_template', 't');

        foreach ($filters as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($name === 'active') {
                $qb->andWhere('t.active = :active')
                    ->setParameter('active', (int) $value);

                continue;
            }

            if ($name === 'usage_kind') {
                if ($value === 'instant') {
                    $qb->andWhere("t.event_name IS NOT NULL AND t.event_name != ''");
                }
                if ($value === 'reusable') {
                    $qb->andWhere("(t.event_name IS NULL OR t.event_name = '')");
                }

                continue;
            }

            if ($name === 'id_mailsendvx_template' || $name === 'id_lang' || $name === 'id_shop') {
                $qb->andWhere(sprintf('t.%s = :%s', $name, $name))
                    ->setParameter($name, (int) $value);

                continue;
            }

            if ($name === 'context_type') {
                $qb->andWhere('t.context_type = :context_type')
                    ->setParameter('context_type', (string) $value);

                continue;
            }

            if ($name === 'event_name') {
                $qb->andWhere('t.event_name LIKE :event_name')
                    ->setParameter('event_name', '%' . $this->escapePercent((string) $value) . '%');

                continue;
            }

            $qb->andWhere(sprintf('t.%s LIKE :%s', $name, $name))
                ->setParameter($name, '%' . $this->escapePercent((string) $value) . '%');
        }

        return $qb;
    }
}
