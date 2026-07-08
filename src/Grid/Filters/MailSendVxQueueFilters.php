<?php

namespace Velox\MailSendVx\Grid\Filters;

use PrestaShop\PrestaShop\Core\Search\Filters;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxQueueGridDefinitionFactory;

final class MailSendVxQueueFilters extends Filters
{
    /**
     * @var string
     */
    protected $filterId = MailSendVxQueueGridDefinitionFactory::GRID_ID;

    public static function getDefaults(): array
    {
        return [
            'limit' => 25,
            'offset' => 0,
            'orderBy' => 'scheduled_at',
            'sortOrder' => 'desc',
            'filters' => [],
        ];
    }
}

