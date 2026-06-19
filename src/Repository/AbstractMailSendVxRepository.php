<?php

namespace Velox\MailSendVx\Repository;

use Context;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

abstract class AbstractMailSendVxRepository
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $databasePrefix;

    public function __construct(Connection $connection, string $databasePrefix)
    {
        $this->connection = $connection;
        $this->databasePrefix = $databasePrefix;
    }

    protected function getTableName(string $table): string
    {
        return $this->databasePrefix . $table;
    }

    protected function createQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder();
    }

    protected function getCurrentShopId(): int
    {
        return (int) Context::getContext()->shop->id;
    }
}
