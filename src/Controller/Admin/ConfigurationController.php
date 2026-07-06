<?php

namespace Velox\MailSendVx\Controller\Admin;

use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Configuration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Velox\MailSendVx\ModuleConstants;

class ConfigurationController extends FrameworkBundleAdminController
{
    /**
     * @var FormHandlerInterface
     */
    private $formHandler;

    public function __construct(FormHandlerInterface $formHandler)
    {
        parent::__construct();
        $this->formHandler = $formHandler;
    }

    public function indexAction(Request $request): Response
    {
        $form = $this->formHandler->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $this->formHandler->save($form->getData());
            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Actualización correcta.', 'Admin.Notifications.Success', []));

                return $this->redirectToRoute('mailsendvx_configuration');
            }

            foreach ($errors as $error) {
                $this->addFlash('danger', (string) $error);
            }
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/configuration.html.twig', [
            'configurationForm' => $form->createView(),
            'configurationData' => (array) ($form->getData()['mailsendvx_configuration'] ?? []),
            'shopName' => (string) $this->getContext()->shop->name,
            'abandonedCartCronUrl' => $this->getContext()->link->getModuleLink('mailsendvx', 'abandonedcartcron', [
                'token' => (string) Configuration::get(ModuleConstants::CONFIG_CRON_TOKEN),
            ], true),
            'queueCronUrl' => $this->getContext()->link->getModuleLink('mailsendvx', 'queuecron', [
                'token' => (string) Configuration::get(ModuleConstants::CONFIG_CRON_TOKEN),
                'limit' => 50,
            ], true),
        ]);
    }
}
