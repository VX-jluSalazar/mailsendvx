<?php

namespace Velox\MailSendVx\Controller\Admin;

use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\GridDefinitionFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Service\Grid\ResponseBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormErrorIterator;
use Twig\Error\Error as TwigError;
use Velox\MailSendVx\Form\Type\TemplateFormType;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxTemplateGridDefinitionFactory;
use Velox\MailSendVx\Grid\Filters\MailSendVxTemplateFilters;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Service\Template\TemplateAdminService;

class TemplatesController extends FrameworkBundleAdminController
{
    /**
     * @var TemplateAdminService
     */
    private $templateAdminService;

    /**
     * @var GridFactoryInterface
     */
    private $templateGridFactory;

    /**
     * @var GridDefinitionFactoryInterface
     */
    private $templateGridDefinitionFactory;

    /**
     * @var ResponseBuilder
     */
    private $responseBuilder;

    public function __construct(
        TemplateAdminService $templateAdminService,
        GridFactoryInterface $templateGridFactory,
        GridDefinitionFactoryInterface $templateGridDefinitionFactory,
        ResponseBuilder $responseBuilder
    ) {
        parent::__construct();
        $this->templateAdminService = $templateAdminService;
        $this->templateGridFactory = $templateGridFactory;
        $this->templateGridDefinitionFactory = $templateGridDefinitionFactory;
        $this->responseBuilder = $responseBuilder;
    }

    public function indexAction(Request $request, MailSendVxTemplateFilters $templateFilters): Response
    {
        $eventLabels = $this->templateAdminService->getSupportedEvents();
        $languageChoices = $this->buildLanguageChoices();

        if ($request->isMethod('POST') && $request->request->has(MailSendVxTemplateGridDefinitionFactory::GRID_ID)) {
            return $this->responseBuilder->buildSearchResponse(
                $this->templateGridDefinitionFactory,
                $request,
                MailSendVxTemplateGridDefinitionFactory::GRID_ID,
                'mailsendvx_templates'
            );
        }

        $templates = $this->decorateTemplates(
            $this->templateAdminService->getTemplates(),
            $eventLabels,
            $this->buildLanguageLabels($languageChoices)
        );

        return $this->render('@Modules/mailsendvx/views/templates/admin/templates_list.html.twig', [
            'templatesCount' => count($templates),
            'templatesGrid' => $this->presentGrid($this->templateGridFactory->getGrid($templateFilters)),
            'activeTemplatesCount' => $this->countActiveTemplates($templates),
            'defaultTestEmail' => (string) ($this->getContext()->employee->email ?? ''),
            'shopName' => (string) $this->getContext()->shop->name,
        ]);
    }

    public function createAction(Request $request): Response
    {
        return $this->renderTemplateForm($request, null);
    }

    public function editAction(Request $request, int $idTemplate): Response
    {
        return $this->renderTemplateForm($request, $idTemplate);
    }

    public function deleteAction(Request $request, int $idTemplate): Response
    {
        $token = (string) $request->get('_token', $request->request->get('_token'));
        if (!$this->isCsrfTokenValid('delete-template-' . $idTemplate, $token)
            && !$this->isCsrfTokenValid('mailsendvx-template-delete', $token)
        ) {
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

    public function bulkDeleteAction(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('mailsendvx-template-bulk-delete', (string) $request->get('_token'))) {
            $this->addFlash('danger', $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []));

            return $this->redirectToRoute('mailsendvx_templates');
        }

        $selectedIds = array_values(array_filter(array_map('intval', (array) $request->request->get(MailSendVxTemplateGridDefinitionFactory::GRID_ID . '_bulk_templates', []))));
        if (empty($selectedIds)) {
            $this->addFlash('warning', $this->trans('Selecciona al menos una plantilla.', 'Admin.Notifications.Warning', []));

            return $this->redirectToRoute('mailsendvx_templates');
        }

        $deleted = 0;
        foreach ($selectedIds as $idTemplate) {
            if ($this->templateAdminService->deleteTemplate($idTemplate)) {
                ++$deleted;
            }
        }

        if ($deleted > 0) {
            $this->addFlash('success', sprintf('%d plantilla(s) eliminada(s).', $deleted));
        }

        return $this->redirectToRoute('mailsendvx_templates');
    }

    public function previewAction(Request $request, int $idTemplate): Response
    {
        if (!$request->isXmlHttpRequest()) {
            $editId = $request->query->getInt('edit', 0);

            if ($editId > 0) {
                return $this->redirectToRoute('mailsendvx_template_edit', ['idTemplate' => $editId]);
            }

            return $this->redirectToRoute('mailsendvx_templates');
        }

        try {
            $preview = $this->templateAdminService->getPreviewData($idTemplate);

            return new JsonResponse([
                'success' => true,
                'preview' => $preview,
                'context_message' => $preview['context_source'] === 'historical'
                    ? $this->trans('Previsualización renderizada con el último payload capturado para este evento.', 'Modules.Mailsendvx.Admin', [])
                    : $this->trans('Previsualización renderizada con datos de ejemplo para este evento.', 'Modules.Mailsendvx.Admin', []),
                'test_url' => $this->generateUrl('mailsendvx_template_test', ['idTemplate' => $idTemplate]),
                'test_token' => $this->get('security.csrf.token_manager')->getToken('test-template-' . $idTemplate)->getValue(),
            ]);
        } catch (TwigError $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => sprintf('Error de sintaxis Twig: %s', $exception->getMessage()),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => (string) $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function sendTestAction(Request $request, int $idTemplate): Response
    {
        if (!$this->isCsrfTokenValid('test-template-' . $idTemplate, (string) $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []),
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('danger', $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []));

            return $this->redirect($this->getTemplateReturnUrl($request));
        }

        $result = $this->templateAdminService->sendTest($idTemplate, trim((string) $request->request->get('test_email')));
        if ($result === true) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => $this->trans('Correo de prueba enviado.', 'Admin.Notifications.Success', []),
                ]);
            }

            $this->addFlash('success', $this->trans('Correo de prueba enviado.', 'Admin.Notifications.Success', []));
        } else {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => (string) $result,
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('danger', (string) $result);
        }

        return $this->redirect($this->getTemplateReturnUrl($request));
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
            $contextType = (string) ($template['context_type'] ?? ModuleConstants::getEventContextType($eventName));

            $template['event_label'] = $eventName !== '' ? ($eventLabels[$eventName] ?? $eventName) : 'Reusable en flows';
            $template['language_label'] = $languageLabels[$idLang] ?? ('#' . $idLang);
            $template['shop_label'] = $idShop > 0 ? ('#' . $idShop) : 'All shops';
            $template['context_type'] = $contextType;
            $template['context_label'] = $this->templateAdminService->getSupportedContextTypes()[$contextType] ?? $contextType;
            $template['usage_label'] = $eventName !== '' ? 'Instantánea' : 'Reusable';
        }
        unset($template);

        return $templates;
    }

    /**
     * @param array<string, string> $eventLabels
     *
     * @return array<string, string>
     */
    private function buildEventContextMap(array $eventLabels): array
    {
        $map = [];
        foreach (array_keys($eventLabels) as $eventName) {
            $contextType = ModuleConstants::getEventContextType((string) $eventName);
            if ($contextType !== null) {
                $map[(string) $eventName] = $contextType;
            }
        }

        return $map;
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

    /**
     * @return array<int, string>
     */
    private function flattenFormErrors(FormErrorIterator $errors): array
    {
        $messages = [];
        foreach ($errors as $error) {
            $origin = $error->getOrigin();
            $label = $origin ? (string) $origin->getName() : 'form';
            $messages[] = sprintf('%s: %s', $label, $error->getMessage());
        }

        return array_values(array_unique($messages));
    }

    /**
     * @return string
     */
    private function getTemplateReturnUrl(Request $request): string
    {
        $editId = $request->request->getInt('edit', 0);
        if ($editId > 0) {
            return $this->generateUrl('mailsendvx_template_edit', ['idTemplate' => $editId]);
        }

        return $this->generateUrl('mailsendvx_templates');
    }

    private function renderTemplateForm(Request $request, ?int $editId): Response
    {
        $eventLabels = $this->templateAdminService->getSupportedEvents();
        $contextLabels = $this->templateAdminService->getSupportedContextTypes();
        $languageChoices = $this->buildLanguageChoices();
        $templates = $this->decorateTemplates(
            $this->templateAdminService->getTemplates(),
            $eventLabels,
            $this->buildLanguageLabels($languageChoices)
        );

        $form = $this->createForm(TemplateFormType::class, $this->templateAdminService->getFormData($editId), [
            'event_choices' => array_flip($eventLabels),
            'context_choices' => array_flip($contextLabels),
            'wrapper_choices' => array_flip($this->templateAdminService->getWrapperChoices()),
            'language_choices' => $languageChoices,
            'default_shop_id' => (int) $this->getContext()->shop->id,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $saved = $this->templateAdminService->saveTemplate($form->getData());
                if ($saved) {
                    $savedId = (int) ($form->getData()['id_mailsendvx_template'] ?? 0);
                    if ($savedId <= 0) {
                        $savedId = $this->findLastSavedTemplateId($form->getData());
                    }

                    $this->addFlash('success', $this->trans('Plantilla guardada.', 'Admin.Notifications.Success', []));

                    if ($savedId > 0) {
                        return $this->redirectToRoute('mailsendvx_template_edit', ['idTemplate' => $savedId]);
                    }

                    return $this->redirectToRoute('mailsendvx_templates');
                }

                $this->addFlash('danger', $this->trans('No se pudo guardar la plantilla.', 'Modules.Mailsendvx.Admin', []));
            } catch (\Throwable $exception) {
                $this->addFlash('danger', (string) $exception->getMessage());
            }
        } elseif ($form->isSubmitted()) {
            foreach ($this->flattenFormErrors($form->getErrors(true)) as $errorMessage) {
                $this->addFlash('danger', $errorMessage);
            }
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/template_form.html.twig', [
            'templateForm' => $form->createView(),
            'templatesCount' => count($templates),
            'currentEditTemplate' => $this->findTemplate($templates, $editId),
            'activeTemplatesCount' => $this->countActiveTemplates($templates),
            'eventContextMap' => $this->buildEventContextMap($eventLabels),
            'shopName' => (string) $this->getContext()->shop->name,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function findLastSavedTemplateId(array $data): int
    {
        foreach ($this->templateAdminService->getTemplates() as $template) {
            if ((string) ($template['name'] ?? '') !== (string) ($data['template_name'] ?? '')) {
                continue;
            }

            if ((string) ($template['subject'] ?? '') !== (string) ($data['subject'] ?? '')) {
                continue;
            }

            if ((int) ($template['id_lang'] ?? 0) !== (int) ($data['id_lang'] ?? 0)) {
                continue;
            }

            if ((int) ($template['id_shop'] ?? 0) !== (int) ($data['id_shop'] ?? 0)) {
                continue;
            }

            return (int) ($template['id_mailsendvx_template'] ?? 0);
        }

        return 0;
    }
}
