<?php

namespace Velox\MailSendVx\Grid\Filters;

use PrestaShop\PrestaShop\Core\Search\Filters;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxEventGridDefinitionFactory;

final class MailSendVxEventFilters extends Filters
{
    /**
     * @var string
     */
    protected $filterId = MailSendVxEventGridDefinitionFactory::GRID_ID;

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

