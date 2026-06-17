<?php

namespace Velox\MailSendVx\Controller\Admin;

use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
                $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success', []));

                return $this->redirectToRoute('mailsendvx_configuration');
            }

            foreach ($errors as $error) {
                $this->addFlash('danger', (string) $error);
            }
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/configuration.html.twig', [
            'configurationForm' => $form->createView(),
        ]);
    }
}
