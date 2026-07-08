<?php

namespace Velox\MailSendVx\Grid\Filters;

use PrestaShop\PrestaShop\Core\Search\Filters;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxLogGridDefinitionFactory;

final class MailSendVxLogFilters extends Filters
{
    /**
     * @var string
     */
    protected $filterId = MailSendVxLogGridDefinitionFactory::GRID_ID;

    public static function getDefaults(): array
    {
        return [
            'limit' => 20,
            'offset' => 0,
            'orderBy' => 'date_add',
            'sortOrder' => 'desc',
            'filters' => [],
        ];
    }
}

