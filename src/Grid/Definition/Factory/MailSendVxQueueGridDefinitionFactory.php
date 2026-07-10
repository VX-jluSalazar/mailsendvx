<?php

namespace Velox\MailSendVx\Grid\Definition\Factory;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\SubmitRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn;
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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class MailSendVxQueueGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    public const GRID_ID = 'mailsendvx_queue';

    /**
     * @var CsrfTokenManagerInterface
     */
    private $csrfTokenManager;

    public function __construct(
        HookDispatcherInterface $hookDispatcher,
        CsrfTokenManagerInterface $csrfTokenManager
    ) {
        parent::__construct($hookDispatcher);
        $this->csrfTokenManager = $csrfTokenManager;
    }

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->trans('Queue operativa', [], 'Modules.Mailsendvx.Admin');
    }

    protected function getColumns(): ColumnCollectionInterface
    {
        return (new ColumnCollection())
            ->add(
                (new BulkActionColumn('bulk_queue'))
                    ->setOptions([
                        'bulk_field' => 'id_mailsendvx_queue',
                        'disabled_field' => 'bulk_disabled',
                    ])
            )
            ->add(
                (new DataColumn('id_mailsendvx_queue'))
                    ->setName($this->trans('ID', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_mailsendvx_queue',
                    ])
            )
            ->add(
                (new DataColumn('status'))
                    ->setName($this->trans('Estado', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'status',
                    ])
            )
            ->add(
                (new DataColumn('event_name'))
                    ->setName($this->trans('Evento', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'event_name',
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
                (new DateTimeColumn('scheduled_at'))
                    ->setName($this->trans('Programado', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'scheduled_at',
                        'format' => 'd/m/Y H:i:s',
                    ])
            )
            ->add(
                (new DataColumn('flow_step'))
                    ->setName($this->trans('Flow / step', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'flow_step',
                    ])
            )
            ->add(
                (new DataColumn('template_reference'))
                    ->setName($this->trans('Template', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'template_reference',
                    ])
            )
            ->add(
                (new DataColumn('last_error_excerpt'))
                    ->setName($this->trans('Detalle', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'last_error_excerpt',
                    ])
            )
            ->add(
                (new ActionColumn('actions'))
                    ->setName($this->trans('Actions', [], 'Admin.Global'))
                    ->setOptions([
                        'actions' => (new RowActionCollection())
                            ->add(
                                (new LinkRowAction('detail'))
                                    ->setName($this->trans('Detalle', [], 'Modules.Mailsendvx.Admin'))
                                    ->setIcon('visibility')
                                    ->setOptions([
                                        'route' => 'mailsendvx_dashboard_detail',
                                        'route_param_name' => 'recordId',
                                        'route_param_field' => 'id_mailsendvx_queue',
                                        'extra_route_params' => [
                                            'gridId' => self::GRID_ID,
                                        ],
                                        'attr' => [
                                            'class' => 'js-mailsendvx-grid-detail',
                                        ],
                                        'use_inline_display' => true,
                                    ])
                            )
                            ->add(
                                (new SubmitRowAction('cancel'))
                                    ->setName($this->trans('Cancelar', [], 'Modules.Mailsendvx.Admin'))
                                    ->setIcon('cancel')
                                    ->setOptions([
                                        'method' => 'POST',
                                        'route' => 'mailsendvx_queue_cancel',
                                        'route_param_name' => 'idQueue',
                                        'route_param_field' => 'id_mailsendvx_queue',
                                        'confirm_message' => $this->trans('¿Cancelar este job sin borrar el histórico?', [], 'Modules.Mailsendvx.Admin'),
                                        'extra_route_params' => [
                                            '_token' => $this->csrfTokenManager->getToken('mailsendvx-queue-cancel')->getValue(),
                                        ],
                                        'accessibility_checker' => static function (array $record): bool {
                                            return !empty($record['can_cancel']);
                                        },
                                    ])
                            ),
                    ])
            );
    }

    protected function getFilters(): FilterCollectionInterface
    {
        return (new FilterCollection())
            ->add(
                (new Filter('id_mailsendvx_queue', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('id_mailsendvx_queue')
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
                (new Filter('event_name', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('event_name')
            )
            ->add(
                (new Filter('recipient', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('recipient')
            )
            ->add(
                (new Filter('scheduled_at', DateRangeType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('scheduled_at')
            )
            ->add(
                (new Filter('actions', SearchAndResetType::class))
                    ->setTypeOptions([
                        'reset_route' => 'admin_common_reset_search_by_filter_id',
                        'reset_route_params' => ['filterId' => self::GRID_ID],
                        'redirect_route' => 'mailsendvx_dashboard_queue',
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

    protected function getBulkActions(): BulkActionCollectionInterface
    {
        return (new BulkActionCollection())
            ->add(
                (new SubmitBulkAction('cancel_selection'))
                    ->setName($this->trans('Cancelar seleccionados', [], 'Modules.Mailsendvx.Admin'))
                    ->setIcon('cancel')
                    ->setOptions([
                        'submit_route' => 'mailsendvx_queue_bulk_cancel',
                        'submit_method' => 'POST',
                        'route_params' => [
                            '_token' => $this->csrfTokenManager->getToken('mailsendvx-queue-bulk-cancel')->getValue(),
                        ],
                        'confirm_message' => $this->trans('¿Cancelar los jobs seleccionados sin borrar el histórico?', [], 'Modules.Mailsendvx.Admin'),
                    ])
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
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'skipped' => 'skipped',
        ];
    }
}
