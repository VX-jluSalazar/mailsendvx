<?php

namespace Velox\MailSendVx\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigurationController extends FrameworkBundleAdminController
{
    public function indexAction(Request $request): Response
    {
        return $this->redirectToRoute('mailsendvx_dashboard', ['tab' => 'configuration']);
    }
}
