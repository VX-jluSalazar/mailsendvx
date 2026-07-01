<?php

namespace Velox\MailSendVx\Controller\Admin;

use Language;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Velox\MailSendVx\Service\MailTemplateWrapperService;
use Velox\MailSendVx\Service\TemplateAdminService;
use Velox\MailSendVx\Service\TemplateContentService;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

class WrappersController extends FrameworkBundleAdminController
{
    /**
     * @var MailTemplateWrapperService
     */
    private $wrapperService;

    /**
     * @var TemplateContentService
     */
    private $templateContentService;

    /**
     * @var TemplateAdminService
     */
    private $templateAdminService;

    public function __construct(
        MailTemplateWrapperService $wrapperService,
        TemplateContentService $templateContentService,
        TemplateAdminService $templateAdminService
    ) {
        parent::__construct();
        $this->wrapperService = $wrapperService;
        $this->templateContentService = $templateContentService;
        $this->templateAdminService = $templateAdminService;
    }

    public function indexAction(Request $request): Response
    {
        $availableWrappers = $this->templateAdminService->getWrapperChoices();
        $selectedWrapper = trim((string) $request->get('wrapper', 'mailsendvx_default'));
        if ($selectedWrapper === '') {
            $selectedWrapper = 'mailsendvx_default';
        }

        $selectedLangId = $request->query->getInt('id_lang', (int) $this->getContext()->language->id);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mailsendvx-wrapper-save', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []));

                return $this->redirectToRoute('mailsendvx_wrappers', [
                    'wrapper' => $selectedWrapper,
                    'id_lang' => $selectedLangId,
                ]);
            }

            $selectedWrapper = trim((string) $request->request->get('wrapper_name', $selectedWrapper));
            $selectedLangId = (int) $request->request->get('id_lang', $selectedLangId);
            $wrapperHtml = (string) $request->request->get('wrapper_html', '');
            $wrapperText = trim((string) $request->request->get('wrapper_text', ''));

            if ($wrapperText === '' && $wrapperHtml !== '') {
                $wrapperText = str_replace(
                    '{mailsendvx_html_content}',
                    '{mailsendvx_text_content}',
                    $this->templateContentService->generateTextContentFromHtml($wrapperHtml)
                );
            }

            try {
                $savedWrapper = $this->wrapperService->saveWrapperContent($selectedWrapper, $wrapperHtml, $wrapperText);
                $this->addFlash('success', $this->trans('Wrapper guardado.', 'Admin.Notifications.Success', []));

                return $this->redirectToRoute('mailsendvx_wrappers', [
                    'wrapper' => $savedWrapper,
                    'id_lang' => $selectedLangId,
                ]);
            } catch (\Throwable $exception) {
                $this->addFlash('danger', (string) $exception->getMessage());
            }
        }

        $wrapperContent = $this->wrapperService->getWrapperContent($selectedWrapper, $selectedLangId);
        $languages = Language::getLanguages(false);
        $selectedLanguageCode = '';
        foreach ($languages as $language) {
            if ((int) $language['id_lang'] === $selectedLangId) {
                $selectedLanguageCode = strtoupper((string) ($language['iso_code'] ?? ''));
                break;
            }
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/wrappers.html.twig', [
            'wrappers' => $availableWrappers,
            'selectedWrapper' => $selectedWrapper,
            'selectedLangId' => $selectedLangId,
            'selectedLanguageCode' => $selectedLanguageCode,
            'languages' => $languages,
            'wrapperHtml' => $wrapperContent['html'],
            'wrapperText' => $wrapperContent['text'],
            'shopName' => (string) $this->getContext()->shop->name,
        ]);
    }
}
