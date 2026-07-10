<?php

namespace Velox\MailSendVx\Grid\Definition\Factory;

use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
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

final class MailSendVxLogGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    public const GRID_ID = 'mailsendvx_logs';

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
        return $this->trans('Logs operativos', [], 'Modules.Mailsendvx.Admin');
    }

    protected function getColumns(): ColumnCollectionInterface
    {
        return (new ColumnCollection())
            ->add(
                (new DataColumn('id_mailsendvx_log'))
                    ->setName($this->trans('ID', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_mailsendvx_log',
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
                (new DataColumn('recipient'))
                    ->setName($this->trans('Destinatario', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'recipient',
                    ])
            )
            ->add(
                (new DataColumn('message'))
                    ->setName($this->trans('Message', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'message',
                    ])
            )
            ->add(
                (new DataColumn('payload_excerpt'))
                    ->setName($this->trans('Payload', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'payload_excerpt',
                    ])
            )
            ->add(
                (new ActionColumn('actions'))
                    ->setName($this->trans('Actions', [], 'Admin.Actions'))
                    ->setOptions([
                        'actions' => (new RowActionCollection())
                            ->add(
                                (new LinkRowAction('detail'))
                                    ->setName($this->trans('Detalle', [], 'Modules.Mailsendvx.Admin'))
                                    ->setIcon('visibility')
                                    ->setOptions([
                                        'route' => 'mailsendvx_dashboard_detail',
                                        'route_param_name' => 'recordId',
                                        'route_param_field' => 'id_mailsendvx_log',
                                        'extra_route_params' => [
                                            'gridId' => self::GRID_ID,
                                        ],
                                        'attr' => [
                                            'class' => 'js-mailsendvx-grid-detail',
                                        ],
                                        'use_inline_display' => true,
                                    ])
                            ),
                    ])
            );
    }

    protected function getFilters(): FilterCollectionInterface
    {
        return (new FilterCollection())
            ->add(
                (new Filter('id_mailsendvx_log', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('id_mailsendvx_log')
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
                (new Filter('recipient', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('recipient')
            )
            ->add(
                (new Filter('message', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('message')
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
                    ->setAssociatedColumn('actions')
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
            'pending' => 'pending',
            'scheduled' => 'scheduled',
            'processing' => 'processing',
            'sent' => 'sent',
            'success' => 'success',
            'skipped' => 'skipped',
            'cancelled' => 'cancelled',
            'failed' => 'failed',
        ];
    }
}
