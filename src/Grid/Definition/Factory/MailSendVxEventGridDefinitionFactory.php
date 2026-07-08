<?php

namespace Velox\MailSendVx\Grid\Definition\Factory;

use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DateTimeColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollectionInterface;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
use PrestaShopBundle\Form\Admin\Type\DateRangeType;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class MailSendVxEventGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    public const GRID_ID = 'mailsendvx_events';

    /**
     * @var string
     */
    private $contextDateFormat;

    public function __construct(HookDispatcherInterface $hookDispatcher, string $contextDateFormat)
    {
        parent::__construct($hookDispatcher);
        $this->contextDateFormat = $contextDateFormat;
    }

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->trans('Eventos capturados', [], 'Modules.Mailsendvx.Admin');
    }

    protected function getColumns(): ColumnCollectionInterface
    {
        return (new ColumnCollection())
            ->add(
                (new DataColumn('id_mailsendvx_event'))
                    ->setName($this->trans('ID', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_mailsendvx_event',
                    ])
            )
            ->add(
                (new DateTimeColumn('date_add'))
                    ->setName($this->trans('Date', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'date_add',
                        'format' => $this->contextDateFormat,
                    ])
            )
            ->add(
                (new DataColumn('event_name'))
                    ->setName($this->trans('Event', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'event_name',
                    ])
            )
            ->add(
                (new DataColumn('status'))
                    ->setName($this->trans('Status', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'status',
                    ])
            )
            ->add(
                (new DataColumn('object_type'))
                    ->setName($this->trans('Tipo de objeto', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'object_type',
                    ])
            )
            ->add(
                (new DataColumn('object_id'))
                    ->setName($this->trans('ID del objeto', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'object_id',
                    ])
            )
            ->add(
                (new DataColumn('id_shop'))
                    ->setName($this->trans('Shop', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_shop',
                    ])
            )
            ->add(
                (new DataColumn('payload_excerpt'))
                    ->setName($this->trans('Payload', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'payload_excerpt',
                    ])
            );
    }

    protected function getFilters(): FilterCollectionInterface
    {
        return (new FilterCollection())
            ->add(
                (new Filter('id_mailsendvx_event', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('id_mailsendvx_event')
            )
            ->add(
                (new Filter('event_name', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('event_name')
            )
            ->add(
                (new Filter('status', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => $this->getStatusChoices(),
                        'choice_translation_domain' => false,
                    ])
                    ->setAssociatedColumn('status')
            )
            ->add(
                (new Filter('object_type', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('object_type')
            )
            ->add(
                (new Filter('object_id', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('object_id')
            )
            ->add(
                (new Filter('id_shop', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('id_shop')
            )
            ->add(
                (new Filter('date_add', DateRangeType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('date_add')
            )
            ->add(
                (new Filter('actions', SearchAndResetType::class))
                    ->setTypeOptions([
                        'reset_route' => 'admin_common_reset_search_by_filter_id',
                        'reset_route_params' => ['filterId' => self::GRID_ID],
                        'redirect_route' => 'mailsendvx_dashboard',
                    ])
                    ->setAssociatedColumn('payload_excerpt')
            );
    }

    protected function getGridActions(): GridActionCollectionInterface
    {
        return (new GridActionCollection())
            ->add(
                (new SimpleGridAction('common_refresh_list'))
                    ->setName($this->trans('Refresh list', [], 'Admin.Advparameters.Feature'))
                    ->setIcon('refresh')
            );
    }

    /**
     * @return array<string, string>
     */
    private function getStatusChoices(): array
    {
        return [
            'captured' => 'captured',
            'pending' => 'pending',
            'scheduled' => 'scheduled',
            'sent' => 'sent',
            'success' => 'success',
            'skipped' => 'skipped',
            'cancelled' => 'cancelled',
            'failed' => 'failed',
        ];
    }
}

