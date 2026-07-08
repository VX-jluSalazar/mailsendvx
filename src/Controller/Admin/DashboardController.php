<?php

namespace Velox\MailSendVx\Controller\Admin;

use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\GridDefinitionFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Service\Grid\ResponseBuilder;
use Symfony\Component\HttpFoundation\Response;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxEventGridDefinitionFactory;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxLogGridDefinitionFactory;
use Velox\MailSendVx\Grid\Filters\MailSendVxEventFilters;
use Velox\MailSendVx\Grid\Filters\MailSendVxLogFilters;
use Velox\MailSendVx\Service\Admin\DashboardViewService;

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
    private $logGridFactory;

    /**
     * @var GridDefinitionFactoryInterface
     */
    private $eventGridDefinitionFactory;

    /**
     * @var GridDefinitionFactoryInterface
     */
    private $logGridDefinitionFactory;

    /**
     * @var ResponseBuilder
     */
    private $responseBuilder;

    public function __construct(
        DashboardViewService $dashboardViewService,
        GridFactoryInterface $eventGridFactory,
        GridFactoryInterface $logGridFactory,
        GridDefinitionFactoryInterface $eventGridDefinitionFactory,
        GridDefinitionFactoryInterface $logGridDefinitionFactory,
        ResponseBuilder $responseBuilder
    )
    {
        parent::__construct();
        $this->dashboardViewService = $dashboardViewService;
        $this->eventGridFactory = $eventGridFactory;
        $this->logGridFactory = $logGridFactory;
        $this->eventGridDefinitionFactory = $eventGridDefinitionFactory;
        $this->logGridDefinitionFactory = $logGridDefinitionFactory;
        $this->responseBuilder = $responseBuilder;
    }

    public function indexAction(
        Request $request,
        MailSendVxEventFilters $eventFilters,
        MailSendVxLogFilters $logFilters
    ): Response
    {
        if ($request->isMethod('POST')) {
            if ($request->request->has(MailSendVxEventGridDefinitionFactory::GRID_ID)) {
                return $this->responseBuilder->buildSearchResponse(
                    $this->eventGridDefinitionFactory,
                    $request,
                    MailSendVxEventGridDefinitionFactory::GRID_ID,
                    'mailsendvx_dashboard'
                );
            }

            if ($request->request->has(MailSendVxLogGridDefinitionFactory::GRID_ID)) {
                return $this->responseBuilder->buildSearchResponse(
                    $this->logGridDefinitionFactory,
                    $request,
                    MailSendVxLogGridDefinitionFactory::GRID_ID,
                    'mailsendvx_dashboard'
                );
            }
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/dashboard.html.twig', array_merge(
            $this->dashboardViewService->getViewData(),
            [
                'eventsGrid' => $this->presentGrid($this->eventGridFactory->getGrid($eventFilters)),
                'logsGrid' => $this->presentGrid($this->logGridFactory->getGrid($logFilters)),
            ]
        ));
    }
}
