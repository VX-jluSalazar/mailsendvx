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
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollectionInterface;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class MailSendVxTemplateGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    public const GRID_ID = 'mailsendvx_templates';

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
        return $this->trans('Templates', [], 'Modules.Mailsendvx.Admin');
    }

    protected function getColumns(): ColumnCollectionInterface
    {
        return (new ColumnCollection())
            ->add(
                (new BulkActionColumn('bulk_templates'))
                    ->setOptions([
                        'bulk_field' => 'id_mailsendvx_template',
                    ])
            )
            ->add(
                (new DataColumn('id_mailsendvx_template'))
                    ->setName($this->trans('ID', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_mailsendvx_template',
                    ])
            )
            ->add(
                (new DataColumn('name'))
                    ->setName($this->trans('Name', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'name',
                    ])
            )
            ->add(
                (new DataColumn('usage_label'))
                    ->setName($this->trans('Uso', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'usage_label',
                    ])
            )
            ->add(
                (new DataColumn('context_label'))
                    ->setName($this->trans('Contexto', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'context_label',
                    ])
            )
            ->add(
                (new DataColumn('event_label'))
                    ->setName($this->trans('Event', [], 'Modules.Mailsendvx.Admin'))
                    ->setOptions([
                        'field' => 'event_label',
                    ])
            )
            ->add(
                (new DataColumn('subject'))
                    ->setName($this->trans('Subject', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'subject',
                    ])
            )
            ->add(
                (new DataColumn('language_label'))
                    ->setName($this->trans('Language', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'language_label',
                    ])
            )
            ->add(
                (new DataColumn('shop_label'))
                    ->setName($this->trans('Shop', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'shop_label',
                    ])
            )
            ->add(
                (new DataColumn('active_label'))
                    ->setName($this->trans('Status', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'active_label',
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
                                        'route' => 'mailsendvx_template_detail',
                                        'route_param_name' => 'idTemplate',
                                        'route_param_field' => 'id_mailsendvx_template',
                                        'attr' => [
                                            'class' => 'js-mailsendvx-grid-detail',
                                        ],
                                        'use_inline_display' => true,
                                    ])
                            )
                            ->add(
                                (new LinkRowAction('edit'))
                                    ->setName($this->trans('Edit', [], 'Admin.Actions'))
                                    ->setIcon('edit')
                                    ->setOptions([
                                        'route' => 'mailsendvx_template_edit',
                                        'route_param_name' => 'idTemplate',
                                        'route_param_field' => 'id_mailsendvx_template',
                                        'use_inline_display' => true,
                                    ])
                            )
                            ->add(
                                (new LinkRowAction('preview'))
                                    ->setName($this->trans('Preview', [], 'Admin.Actions'))
                                    ->setIcon('remove_red_eye')
                                    ->setOptions([
                                        'route' => 'mailsendvx_template_preview',
                                        'route_param_name' => 'idTemplate',
                                        'route_param_field' => 'id_mailsendvx_template',
                                        'attr' => [
                                            'class' => 'js-mailsendvx-template-preview',
                                        ],
                                        'use_inline_display' => true,
                                    ])
                            )
                            ->add(
                                (new SubmitRowAction('delete'))
                                    ->setName($this->trans('Delete', [], 'Admin.Actions'))
                                    ->setIcon('delete')
                                    ->setOptions([
                                        'method' => 'POST',
                                        'route' => 'mailsendvx_template_delete',
                                        'route_param_name' => 'idTemplate',
                                        'route_param_field' => 'id_mailsendvx_template',
                                        'confirm_message' => $this->trans('¿Eliminar esta plantilla y quitarla de este alcance?', [], 'Modules.Mailsendvx.Admin'),
                                        'extra_route_params' => [
                                            '_token' => $this->csrfTokenManager->getToken('mailsendvx-template-delete')->getValue(),
                                        ],
                                    ])
                            ),
                    ])
            );
    }

    protected function getFilters(): FilterCollectionInterface
    {
        return (new FilterCollection())
            ->add(
                (new Filter('id_mailsendvx_template', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('id_mailsendvx_template')
            )
            ->add(
                (new Filter('name', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('name')
            )
            ->add(
                (new Filter('usage_kind', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            'Instantánea' => 'instant',
                            'Reusable' => 'reusable',
                        ],
                        'choice_translation_domain' => false,
                    ])
                    ->setAssociatedColumn('usage_label')
            )
            ->add(
                (new Filter('context_type', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            'Pedido' => 'order',
                            'Carrito' => 'cart',
                            'Cliente' => 'customer',
                            'Newsletter' => 'newsletter',
                        ],
                        'choice_translation_domain' => false,
                    ])
                    ->setAssociatedColumn('context_label')
            )
            ->add(
                (new Filter('event_name', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('event_label')
            )
            ->add(
                (new Filter('subject', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('subject')
            )
            ->add(
                (new Filter('id_lang', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('language_label')
            )
            ->add(
                (new Filter('id_shop', TextType::class))
                    ->setTypeOptions(['required' => false])
                    ->setAssociatedColumn('shop_label')
            )
            ->add(
                (new Filter('active', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            'Activa' => 1,
                            'Inactiva' => 0,
                        ],
                        'choice_translation_domain' => false,
                    ])
                    ->setAssociatedColumn('active_label')
            )
            ->add(
                (new Filter('actions', SearchAndResetType::class))
                    ->setTypeOptions([
                        'reset_route' => 'admin_common_reset_search_by_filter_id',
                        'reset_route_params' => ['filterId' => self::GRID_ID],
                        'redirect_route' => 'mailsendvx_templates',
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
                (new SubmitBulkAction('delete_selection'))
                    ->setName($this->trans('Eliminar seleccionados', [], 'Modules.Mailsendvx.Admin'))
                    ->setIcon('delete')
                    ->setOptions([
                        'submit_route' => 'mailsendvx_template_bulk_delete',
                        'submit_method' => 'POST',
                        'route_params' => [
                            '_token' => $this->csrfTokenManager->getToken('mailsendvx-template-bulk-delete')->getValue(),
                        ],
                        'confirm_message' => $this->trans('¿Eliminar las plantillas seleccionadas?', [], 'Modules.Mailsendvx.Admin'),
                    ])
            );
    }
}
