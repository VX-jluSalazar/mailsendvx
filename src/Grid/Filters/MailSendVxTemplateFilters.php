<?php

namespace Velox\MailSendVx\Grid\Filters;

use PrestaShop\PrestaShop\Core\Search\Filters;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxTemplateGridDefinitionFactory;

final class MailSendVxTemplateFilters extends Filters
{
    /**
     * @var string
     */
    protected $filterId = MailSendVxTemplateGridDefinitionFactory::GRID_ID;

    public static function getDefaults(): array
    {
        return [
            'limit' => 25,
            'offset' => 0,
            'orderBy' => 'id_mailsendvx_template',
            'sortOrder' => 'desc',
            'filters' => [],
        ];
    }
}
