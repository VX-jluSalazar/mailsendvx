<?php

namespace Velox\MailSendVx\Controller\Admin;

use Language;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Velox\MailSendVx\Service\Mail\MailTemplateWrapperService;
use Velox\MailSendVx\Service\Template\TemplateAdminService;
use Velox\MailSendVx\Service\Template\TemplateContentService;
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
        $selectedWrapper = trim((string) $request->get('wrapper', 'mailsendvx_default'));
        if ($selectedWrapper === '') {
            $selectedWrapper = 'mailsendvx_default';
        }

        $selectedLangId = $request->query->getInt('id_lang', (int) $this->getContext()->language->id);
        $selectedShopId = (int) $this->getContext()->shop->id;

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
                    '{{ mailsendvx_html_content|raw }}',
                    '{{ mailsendvx_text_content }}',
                    $this->templateContentService->generateTextContentFromHtml($wrapperHtml)
                );
            }

            try {
                $savedWrapper = $this->wrapperService->saveWrapperContent(
                    $selectedWrapper,
                    $wrapperHtml,
                    $wrapperText,
                    $selectedLangId,
                    $selectedShopId
                );
                $this->addFlash('success', $this->trans('Wrapper guardado para la tienda actual.', 'Admin.Notifications.Success', []));

                return $this->redirectToRoute('mailsendvx_wrappers', [
                    'wrapper' => $savedWrapper,
                    'id_lang' => $selectedLangId,
                ]);
            } catch (\Throwable $exception) {
                $this->addFlash('danger', (string) $exception->getMessage());
            }
        }

        $wrapperContent = $this->wrapperService->getWrapperContent($selectedWrapper, $selectedLangId, $selectedShopId);
        $languages = Language::getLanguages(false);
        $languageLabels = $this->buildLanguageLabels($languages);
        $selectedLanguageCode = '';
        foreach ($languages as $language) {
            if ((int) $language['id_lang'] === $selectedLangId) {
                $selectedLanguageCode = strtoupper((string) ($language['iso_code'] ?? ''));
                break;
            }
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/wrappers.html.twig', [
            'wrappers' => $this->templateAdminService->getWrapperChoices(),
            'wrapperRows' => $this->decorateWrapperRows(
                $this->wrapperService->getWrappersTableRows($selectedShopId),
                $languageLabels
            ),
            'selectedWrapper' => $selectedWrapper,
            'selectedLangId' => $selectedLangId,
            'selectedLanguageCode' => $selectedLanguageCode,
            'languages' => $languages,
            'wrapperHtml' => $wrapperContent['html'],
            'wrapperText' => $wrapperContent['text'],
            'shopName' => (string) $this->getContext()->shop->name,
            'shopId' => $selectedShopId,
        ]);
    }

    public function deleteAction(Request $request, int $idWrapper): Response
    {
        if (!$this->isCsrfTokenValid('delete-wrapper-' . $idWrapper, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []));

            return $this->redirectToRoute('mailsendvx_wrappers');
        }

        if ($this->wrapperService->deleteWrapper($idWrapper, (int) $this->getContext()->shop->id)) {
            $this->addFlash('success', $this->trans('Wrapper persistido eliminado.', 'Admin.Notifications.Success', []));
        } else {
            $this->addFlash('danger', $this->trans('No se pudo eliminar el wrapper.', 'Modules.Mailsendvx.Admin', []));
        }

        return $this->redirectToRoute('mailsendvx_wrappers');
    }

    /**
     * @param array<int, array<string, mixed>> $languages
     *
     * @return array<int, string>
     */
    private function buildLanguageLabels(array $languages): array
    {
        $labels = [];
        $labels[0] = 'All languages';

        foreach ($languages as $language) {
            $labels[(int) $language['id_lang']] = (string) $language['name'];
        }

        return $labels;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $languageLabels
     *
     * @return array<int, array<string, mixed>>
     */
    private function decorateWrapperRows(array $rows, array $languageLabels): array
    {
        foreach ($rows as &$row) {
            $idLang = (int) ($row['id_lang'] ?? 0);
            $idShop = (int) ($row['id_shop'] ?? 0);
            $source = (string) ($row['source'] ?? 'file');

            $row['language_label'] = $languageLabels[$idLang] ?? ('#' . $idLang);
            $row['shop_label'] = $idShop > 0 ? ('#' . $idShop) : 'All shops';
            $row['source_label'] = $source === 'database' ? 'Persistido' : 'Base del módulo';
            $row['source_badge_class'] = $source === 'database' ? 'mailsendvx-badge--success' : 'mailsendvx-badge--muted';
        }
        unset($row);

        return $rows;
    }
}
