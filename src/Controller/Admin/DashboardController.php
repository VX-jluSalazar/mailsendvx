<?php

namespace Velox\MailSendVx\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Response;
use Velox\MailSendVx\Service\DashboardViewService;

class DashboardController extends FrameworkBundleAdminController
{
    /**
     * @var DashboardViewService
     */
    private $dashboardViewService;

    public function __construct(DashboardViewService $dashboardViewService)
    {
        parent::__construct();
        $this->dashboardViewService = $dashboardViewService;
    }

    public function indexAction(): Response
    {
        return $this->render('@Modules/mailsendvx/views/templates/admin/dashboard.html.twig', $this->dashboardViewService->getViewData());
    }
}
