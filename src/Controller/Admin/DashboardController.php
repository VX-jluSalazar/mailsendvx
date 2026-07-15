<?php

namespace Velox\MailSendVx\Controller\Admin;

use Configuration;
use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\GridDefinitionFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Service\Grid\ResponseBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxEventGridDefinitionFactory;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxLogGridDefinitionFactory;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxQueueGridDefinitionFactory;
use Velox\MailSendVx\Grid\Filters\MailSendVxEventFilters;
use Velox\MailSendVx\Grid\Filters\MailSendVxLogFilters;
use Velox\MailSendVx\Grid\Filters\MailSendVxQueueFilters;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Service\Admin\AdminAjaxResponseBuilder;
use Velox\MailSendVx\Service\Admin\AdminGridRecordDetailProvider;
use Velox\MailSendVx\Service\Admin\DashboardViewService;
use Velox\MailSendVx\Service\Theme\ColorPaletteProvider;

class DashboardController extends FrameworkBundleAdminController
{
    /**
     * @var DashboardViewService
     */
    private $dashboardViewService;

    /**
     * @var GridFactoryInterface
     */
    private $eventGridFactory;

    /**
     * @var GridFactoryInterface
     */
    private $queueGridFactory;

    /**
     * @var GridFactoryInterface
     */
    private $logGridFactory;

    /**
     * @var GridDefinitionFactoryInterface
     */
    private $eventGridDefinitionFactory;

    /**
     * @var GridDefinitionFactoryInterface
     */
    private $queueGridDefinitionFactory;

    /**
     * @var GridDefinitionFactoryInterface
     */
    private $logGridDefinitionFactory;

    /**
     * @var ResponseBuilder
     */
    private $responseBuilder;

    /**
     * @var FormHandlerInterface
     */
    private $formHandler;

    /**
     * @var AdminAjaxResponseBuilder
     */
    private $ajaxResponseBuilder;

    /**
     * @var AdminGridRecordDetailProvider
     */
    private $gridRecordDetailProvider;

    /**
     * @var ColorPaletteProvider
     */
    private $colorPaletteProvider;

    public function __construct(
        DashboardViewService $dashboardViewService,
        GridFactoryInterface $eventGridFactory,
        GridFactoryInterface $queueGridFactory,
        GridFactoryInterface $logGridFactory,
        GridDefinitionFactoryInterface $eventGridDefinitionFactory,
        GridDefinitionFactoryInterface $queueGridDefinitionFactory,
        GridDefinitionFactoryInterface $logGridDefinitionFactory,
        ResponseBuilder $responseBuilder,
        FormHandlerInterface $formHandler,
        AdminAjaxResponseBuilder $ajaxResponseBuilder,
        AdminGridRecordDetailProvider $gridRecordDetailProvider,
        ColorPaletteProvider $colorPaletteProvider
    )
    {
        parent::__construct();
        $this->dashboardViewService = $dashboardViewService;
        $this->eventGridFactory = $eventGridFactory;
        $this->queueGridFactory = $queueGridFactory;
        $this->logGridFactory = $logGridFactory;
        $this->eventGridDefinitionFactory = $eventGridDefinitionFactory;
        $this->queueGridDefinitionFactory = $queueGridDefinitionFactory;
        $this->logGridDefinitionFactory = $logGridDefinitionFactory;
        $this->responseBuilder = $responseBuilder;
        $this->formHandler = $formHandler;
        $this->ajaxResponseBuilder = $ajaxResponseBuilder;
        $this->gridRecordDetailProvider = $gridRecordDetailProvider;
        $this->colorPaletteProvider = $colorPaletteProvider;
    }

    public function indexAction(
        Request $request,
        MailSendVxEventFilters $eventFilters,
        MailSendVxQueueFilters $queueFilters,
        MailSendVxLogFilters $logFilters
    ): Response
    {
        $activeTab = $this->resolveActiveTab((string) $request->query->get('tab', (string) $request->attributes->get('tab', 'events')));

        if ($request->isMethod('POST')) {
            if ($request->request->has(MailSendVxEventGridDefinitionFactory::GRID_ID)) {
                return $this->responseBuilder->buildSearchResponse(
                    $this->eventGridDefinitionFactory,
                    $request,
                    MailSendVxEventGridDefinitionFactory::GRID_ID,
                    'mailsendvx_dashboard',
                    ['tab']
                );
            }

            if ($request->request->has(MailSendVxQueueGridDefinitionFactory::GRID_ID)) {
                return $this->responseBuilder->buildSearchResponse(
                    $this->queueGridDefinitionFactory,
                    $request,
                    MailSendVxQueueGridDefinitionFactory::GRID_ID,
                    'mailsendvx_dashboard',
                    ['tab']
                );
            }

            if ($request->request->has(MailSendVxLogGridDefinitionFactory::GRID_ID)) {
                return $this->responseBuilder->buildSearchResponse(
                    $this->logGridDefinitionFactory,
                    $request,
                    MailSendVxLogGridDefinitionFactory::GRID_ID,
                    'mailsendvx_dashboard',
                    ['tab']
                );
            }
        }

        $form = $this->formHandler->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $this->formHandler->save($form->getData());
            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Actualización correcta.', 'Admin.Notifications.Success', []));

                return $this->redirectToRoute('mailsendvx_dashboard', ['tab' => 'configuration']);
            }

            foreach ($errors as $error) {
                $this->addFlash('danger', (string) $error);
            }

            $activeTab = 'configuration';
        } elseif ($form->isSubmitted()) {
            $activeTab = 'configuration';
        }

        $viewData = array_merge(
            $this->dashboardViewService->getViewData(),
            [
                'activeTab' => $activeTab,
                'eventsGrid' => $this->presentGrid($this->eventGridFactory->getGrid($eventFilters)),
                'queueGrid' => $this->presentGrid($this->queueGridFactory->getGrid($queueFilters)),
                'logsGrid' => $this->presentGrid($this->logGridFactory->getGrid($logFilters)),
                'configurationForm' => $form->createView(),
                'configurationData' => (array) ($form->getData()['mailsendvx_configuration'] ?? []),
                'generatedColorPalette' => $this->colorPaletteProvider->getDocumentationGuide(),
                'shopName' => (string) $this->getContext()->shop->name,
                'abandonedCartCronUrl' => $this->getContext()->link->getModuleLink('mailsendvx', 'abandonedcartcron', [
                    'token' => (string) Configuration::get(ModuleConstants::CONFIG_CRON_TOKEN),
                ], true),
                'queueCronUrl' => $this->getContext()->link->getModuleLink('mailsendvx', 'queuecron', [
                    'token' => (string) Configuration::get(ModuleConstants::CONFIG_CRON_TOKEN),
                    'limit' => 50,
                ], true),
            ]
        );

        if ($request->isXmlHttpRequest() && $request->query->getBoolean('ajax_tab')) {
            return new JsonResponse([
                'activeTab' => $activeTab,
                'html' => $this->renderView('@Modules/mailsendvx/views/templates/admin/dashboard_tab_panels.html.twig', $viewData),
            ]);
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/dashboard.html.twig', $viewData);
    }

    public function gridAction(
        Request $request,
        MailSendVxEventFilters $eventFilters,
        MailSendVxQueueFilters $queueFilters,
        MailSendVxLogFilters $logFilters,
        string $gridId
    ): JsonResponse {
        if (!$request->isXmlHttpRequest()) {
            return $this->ajaxResponseBuilder->createErrorResponse(
                $this->trans('Esta operación solo está disponible mediante AJAX.', 'Admin.Notifications.Error', []),
                Response::HTTP_BAD_REQUEST
            );
        }

        $activeTab = $this->resolveActiveTab((string) $request->query->get('tab', $this->resolveTabByGridId($gridId)));
        $gridHtml = $this->renderDashboardGrid($gridId, $eventFilters, $queueFilters, $logFilters);

        return $this->ajaxResponseBuilder->createSuccessResponse([
            'gridId' => $gridId,
            'html' => $gridHtml,
            'activeTab' => $activeTab,
        ]);
    }

    public function detailAction(Request $request, string $gridId, int $recordId): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->ajaxResponseBuilder->createErrorResponse(
                $this->trans('Esta operación solo está disponible mediante AJAX.', 'Admin.Notifications.Error', []),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $detail = $this->gridRecordDetailProvider->getDashboardRecordDetail($gridId, $recordId);

            return $this->ajaxResponseBuilder->createSuccessResponse([
                'detail' => $detail,
                'html' => $this->renderView('@Modules/mailsendvx/views/templates/admin/partials/detail_modal_content.html.twig', [
                    'detail' => $detail,
                ]),
            ]);
        } catch (\Throwable $exception) {
            return $this->ajaxResponseBuilder->createErrorResponse((string) $exception->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    private function resolveActiveTab(string $tab): string
    {
        $allowedTabs = ['events', 'queue', 'logs', 'configuration'];

        return in_array($tab, $allowedTabs, true) ? $tab : 'events';
    }

    private function resolveTabByGridId(string $gridId): string
    {
        if ($gridId === MailSendVxQueueGridDefinitionFactory::GRID_ID) {
            return 'queue';
        }

        if ($gridId === MailSendVxLogGridDefinitionFactory::GRID_ID) {
            return 'logs';
        }

        return 'events';
    }

    private function renderDashboardGrid(
        string $gridId,
        MailSendVxEventFilters $eventFilters,
        MailSendVxQueueFilters $queueFilters,
        MailSendVxLogFilters $logFilters
    ): string {
        if ($gridId === MailSendVxEventGridDefinitionFactory::GRID_ID) {
            return $this->renderView('@Modules/mailsendvx/views/templates/admin/partials/grid_panel.html.twig', [
                'grid' => $this->presentGrid($this->eventGridFactory->getGrid($eventFilters)),
            ]);
        }

        if ($gridId === MailSendVxQueueGridDefinitionFactory::GRID_ID) {
            return $this->renderView('@Modules/mailsendvx/views/templates/admin/partials/grid_panel.html.twig', [
                'grid' => $this->presentGrid($this->queueGridFactory->getGrid($queueFilters)),
            ]);
        }

        if ($gridId === MailSendVxLogGridDefinitionFactory::GRID_ID) {
            return $this->renderView('@Modules/mailsendvx/views/templates/admin/partials/grid_panel.html.twig', [
                'grid' => $this->presentGrid($this->logGridFactory->getGrid($logFilters)),
            ]);
        }

        throw $this->createNotFoundException(sprintf('Grid "%s" no soportada.', $gridId));
    }
}
