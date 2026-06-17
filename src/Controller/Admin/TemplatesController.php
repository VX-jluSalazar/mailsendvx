<?php

namespace Velox\MailSendVx\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Velox\MailSendVx\Form\Type\TemplateFormType;
use Velox\MailSendVx\Service\TemplateAdminService;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

class TemplatesController extends FrameworkBundleAdminController
{
    /**
     * @var TemplateAdminService
     */
    private $templateAdminService;

    public function __construct(TemplateAdminService $templateAdminService)
    {
        parent::__construct();
        $this->templateAdminService = $templateAdminService;
    }

    public function indexAction(Request $request): Response
    {
        $editId = $request->query->getInt('edit', 0) ?: null;
        $previewId = $request->query->getInt('preview', 0) ?: null;
        $form = $this->createForm(TemplateFormType::class, $this->templateAdminService->getFormData($editId), [
            'event_choices' => array_flip($this->templateAdminService->getSupportedEvents()),
            'language_choices' => $this->buildLanguageChoices(),
            'default_shop_id' => (int) $this->getContext()->shop->id,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $saved = $this->templateAdminService->saveTemplate($form->getData());
            if ($saved) {
                $this->addFlash('success', $this->trans('Template saved.', 'Admin.Notifications.Success', []));

                return $this->redirectToRoute('mailsendvx_templates');
            }

            $this->addFlash('danger', $this->trans('Template could not be saved.', 'Modules.Mailsendvx.Admin', []));
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/templates.html.twig', [
            'templateForm' => $form->createView(),
            'templates' => $this->templateAdminService->getTemplates(),
            'preview' => $this->templateAdminService->getPreviewData($previewId),
        ]);
    }

    public function deleteAction(int $idTemplate): Response
    {
        if ($this->templateAdminService->deleteTemplate($idTemplate)) {
            $this->addFlash('success', $this->trans('Template deleted.', 'Admin.Notifications.Success', []));
        } else {
            $this->addFlash('danger', $this->trans('Template could not be deleted.', 'Modules.Mailsendvx.Admin', []));
        }

        return $this->redirectToRoute('mailsendvx_templates');
    }

    public function sendTestAction(Request $request, int $idTemplate): Response
    {
        $result = $this->templateAdminService->sendTest($idTemplate, trim((string) $request->request->get('test_email')));
        if ($result === true) {
            $this->addFlash('success', $this->trans('Test email sent.', 'Admin.Notifications.Success', []));
        } else {
            $this->addFlash('danger', (string) $result);
        }

        return $this->redirectToRoute('mailsendvx_templates');
    }

    /**
     * @return array<string, int>
     */
    private function buildLanguageChoices(): array
    {
        $choices = ['All languages' => 0];
        foreach ($this->templateAdminService->getLanguages() as $language) {
            $choices[(string) $language['name']] = (int) $language['id_lang'];
        }

        return $choices;
    }
}
