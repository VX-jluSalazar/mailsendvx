<?php

namespace Velox\MailSendVx\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\Error as TwigError;
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
        $eventLabels = $this->templateAdminService->getSupportedEvents();
        $languageChoices = $this->buildLanguageChoices();
        $form = $this->createForm(TemplateFormType::class, $this->templateAdminService->getFormData($editId), [
            'event_choices' => array_flip($eventLabels),
            'wrapper_choices' => array_flip($this->templateAdminService->getWrapperChoices()),
            'language_choices' => $languageChoices,
            'default_shop_id' => (int) $this->getContext()->shop->id,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $saved = $this->templateAdminService->saveTemplate($form->getData());
                if ($saved) {
                    $this->addFlash('success', $this->trans('Plantilla guardada.', 'Admin.Notifications.Success', []));

                    return $this->redirectToRoute('mailsendvx_templates');
                }

                $this->addFlash('danger', $this->trans('No se pudo guardar la plantilla.', 'Modules.Mailsendvx.Admin', []));
            } catch (\Throwable $exception) {
                $this->addFlash('danger', (string) $exception->getMessage());
            }
        }

        $templates = $this->decorateTemplates(
            $this->templateAdminService->getTemplates(),
            $eventLabels,
            $this->buildLanguageLabels($languageChoices)
        );

        $preview = null;
        if ($previewId) {
            try {
                $preview = $this->templateAdminService->getPreviewData($previewId);
            } catch (TwigError $exception) {
                $this->addFlash('danger', sprintf('Error de sintaxis Twig: %s', $exception->getMessage()));
            }
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/templates.html.twig', [
            'templateForm' => $form->createView(),
            'templates' => $templates,
            'preview' => $preview,
            'currentEditTemplate' => $this->findTemplate($templates, $editId),
            'activeTemplatesCount' => $this->countActiveTemplates($templates),
            'defaultTestEmail' => (string) ($this->getContext()->employee->email ?? ''),
            'shopName' => (string) $this->getContext()->shop->name,
        ]);
    }

    public function deleteAction(Request $request, int $idTemplate): Response
    {
        if (!$this->isCsrfTokenValid('delete-template-' . $idTemplate, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []));

            return $this->redirectToRoute('mailsendvx_templates');
        }

        if ($this->templateAdminService->deleteTemplate($idTemplate)) {
            $this->addFlash('success', $this->trans('Plantilla eliminada.', 'Admin.Notifications.Success', []));
        } else {
            $this->addFlash('danger', $this->trans('No se pudo eliminar la plantilla.', 'Modules.Mailsendvx.Admin', []));
        }

        return $this->redirectToRoute('mailsendvx_templates');
    }

    public function sendTestAction(Request $request, int $idTemplate): Response
    {
        if (!$this->isCsrfTokenValid('test-template-' . $idTemplate, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []));

            return $this->redirectToRoute('mailsendvx_templates');
        }

        $result = $this->templateAdminService->sendTest($idTemplate, trim((string) $request->request->get('test_email')));
        if ($result === true) {
            $this->addFlash('success', $this->trans('Correo de prueba enviado.', 'Admin.Notifications.Success', []));
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

    /**
     * @param array<string, int> $languageChoices
     *
     * @return array<int, string>
     */
    private function buildLanguageLabels(array $languageChoices): array
    {
        $labels = [];
        foreach ($languageChoices as $label => $idLang) {
            $labels[(int) $idLang] = (string) $label;
        }

        return $labels;
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     * @param array<string, string> $eventLabels
     * @param array<int, string> $languageLabels
     *
     * @return array<int, array<string, mixed>>
     */
    private function decorateTemplates(array $templates, array $eventLabels, array $languageLabels): array
    {
        foreach ($templates as &$template) {
            $idLang = (int) ($template['id_lang'] ?? 0);
            $idShop = (int) ($template['id_shop'] ?? 0);
            $eventName = (string) ($template['event_name'] ?? '');

            $template['event_label'] = $eventLabels[$eventName] ?? $eventName;
            $template['language_label'] = $languageLabels[$idLang] ?? ('#' . $idLang);
            $template['shop_label'] = $idShop > 0 ? ('#' . $idShop) : 'All shops';
        }
        unset($template);

        return $templates;
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     *
     * @return array<string, mixed>|null
     */
    private function findTemplate(array $templates, ?int $editId): ?array
    {
        if (!$editId) {
            return null;
        }

        foreach ($templates as $template) {
            if ((int) ($template['id_mailsendvx_template'] ?? 0) === $editId) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     */
    private function countActiveTemplates(array $templates): int
    {
        $count = 0;
        foreach ($templates as $template) {
            if (!empty($template['active'])) {
                ++$count;
            }
        }

        return $count;
    }
}
