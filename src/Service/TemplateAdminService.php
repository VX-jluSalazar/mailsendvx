<?php

namespace Velox\MailSendVx\Service;

use Context;
use Language;
use Validate;
use Twig\Error\Error as TwigError;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Repository\MailSendVxEventRepository;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;

class TemplateAdminService
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var EventTemplateContextService
     */
    private $eventContextService;

    /**
     * @var OrderStateEventService
     */
    private $orderStateEventService;

    /**
     * @var TemplateContentService
     */
    private $templateContentService;

    /**
     * @var MailSendVxTemplateRepository
     */
    private $templateRepository;

    /**
     * @var MailSendVxEventRepository
     */
    private $eventRepository;

    /**
     * @var MailTemplateWrapperService
     */
    private $wrapperService;

    /**
     * @var MailSendVxMailer
     */
    private $mailer;

    /**
     * @var MailSendVxTemplateRenderer
     */
    private $templateRenderer;

    public function __construct(
        Context $context,
        EventTemplateContextService $eventContextService,
        OrderStateEventService $orderStateEventService,
        TemplateContentService $templateContentService,
        MailSendVxTemplateRepository $templateRepository,
        MailSendVxEventRepository $eventRepository,
        MailTemplateWrapperService $wrapperService,
        MailSendVxMailer $mailer,
        MailSendVxTemplateRenderer $templateRenderer
    ) {
        $this->context = $context;
        $this->eventContextService = $eventContextService;
        $this->orderStateEventService = $orderStateEventService;
        $this->templateContentService = $templateContentService;
        $this->templateRepository = $templateRepository;
        $this->eventRepository = $eventRepository;
        $this->wrapperService = $wrapperService;
        $this->mailer = $mailer;
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTemplates(): array
    {
        return $this->templateRepository->getAll();
    }

    /**
     * @return array<string, string>
     */
    public function getSupportedEvents(): array
    {
        return array_merge(
            [
                ModuleConstants::EVENT_ORDER_CREATED => 'Pedido creado',
                ModuleConstants::EVENT_ORDER_STATUS_CHANGED => 'Cambio de estado de pedido',
                ModuleConstants::EVENT_CUSTOMER_REGISTERED => 'Registro de cliente',
                ModuleConstants::EVENT_NEWSLETTER_REGISTERED => 'Suscripcion newsletter',
                ModuleConstants::EVENT_CART_ABANDONED => 'Carrito abandonado',
            ],
            $this->orderStateEventService->getSupportedEvents([
                'generic' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED,
            ])
        );
    }

    /**
     * @return array<string, string>
     */
    public function getWrapperChoices(): array
    {
        $wrappers = $this->wrapperService->getAvailableWrappers();
        if (empty($wrappers)) {
            return ['mailsendvx_default' => 'mailsendvx_default'];
        }

        return $wrappers;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLanguages(): array
    {
        return Language::getLanguages(false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormData(?int $idTemplate = null): array
    {
        $template = $idTemplate ? $this->templateRepository->findById($idTemplate) : null;
        $eventName = $template ? (string) $template['event_name'] : ModuleConstants::EVENT_ORDER_STATUS_CHANGED;
        $idLang = $template ? (int) $template['id_lang'] : (int) $this->context->language->id;
        $wrapperName = $template ? (string) $template['mail_template'] : 'mailsendvx_default';
        $wrapperContent = $this->wrapperService->getWrapperContent($wrapperName, $idLang);

        return [
            'id_mailsendvx_template' => $template ? (int) $template['id_mailsendvx_template'] : 0,
            'id_shop' => $template ? (int) $template['id_shop'] : (int) $this->context->shop->id,
            'id_lang' => $idLang,
            'event_name' => $eventName,
            'template_name' => $template ? (string) $template['name'] : '',
            'subject' => $template ? (string) $template['subject'] : '',
            'mail_template' => $wrapperName,
            'html_content' => $template ? (string) $template['html_content'] : $this->templateContentService->getDefaultHtmlContent(
                $eventName,
                ModuleConstants::EVENT_ORDER_CREATED,
                ModuleConstants::EVENT_CUSTOMER_REGISTERED,
                ModuleConstants::EVENT_NEWSLETTER_REGISTERED,
                ModuleConstants::EVENT_CART_ABANDONED
            ),
            'text_content' => $template ? (string) $template['text_content'] : $this->templateContentService->getDefaultTextContent(
                $eventName,
                ModuleConstants::EVENT_ORDER_CREATED,
                ModuleConstants::EVENT_CUSTOMER_REGISTERED,
                ModuleConstants::EVENT_NEWSLETTER_REGISTERED,
                ModuleConstants::EVENT_CART_ABANDONED
            ),
            'wrapper_html' => $wrapperContent['html'],
            'wrapper_text' => $wrapperContent['text'],
            'save_wrapper_changes' => false,
            'active' => $template ? (bool) $template['active'] : true,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveTemplate(array $data): bool
    {
        $htmlContent = (string) ($data['html_content'] ?? '');
        $textContent = $this->templateContentService->generateTextContentFromHtml($htmlContent);
        $wrapperName = trim((string) ($data['mail_template'] ?? 'mailsendvx_default'));
        $wrapperHtml = (string) ($data['wrapper_html'] ?? '');
        $wrapperText = trim((string) ($data['wrapper_text'] ?? ''));
        if ($wrapperText === '' && $wrapperHtml !== '') {
            $wrapperText = str_replace(
                '{mailsendvx_html_content}',
                '{mailsendvx_text_content}',
                $this->templateContentService->generateTextContentFromHtml($wrapperHtml)
            );
        }

        if ($wrapperName === '') {
            $wrapperName = 'mailsendvx_default';
        }

        if (!empty($data['save_wrapper_changes']) || !$this->wrapperService->wrapperExists($wrapperName)) {
            $this->wrapperService->saveWrapperContent($wrapperName, $wrapperHtml, $wrapperText);
        }

        return $this->templateRepository->save([
            'id_shop' => (int) ($data['id_shop'] ?? $this->context->shop->id),
            'id_lang' => (int) ($data['id_lang'] ?? $this->context->language->id),
            'event_name' => (string) ($data['event_name'] ?? ''),
            'name' => (string) ($data['template_name'] ?? ''),
            'subject' => (string) ($data['subject'] ?? ''),
            'mail_template' => $wrapperName,
            'html_content' => $htmlContent,
            'text_content' => $textContent,
            'json_design' => null,
            'provider' => 'prestashop_mail',
            'active' => !empty($data['active']),
        ], !empty($data['id_mailsendvx_template']) ? (int) $data['id_mailsendvx_template'] : null);
    }

    public function deleteTemplate(int $idTemplate): bool
    {
        return $this->templateRepository->delete($idTemplate);
    }

    /**
     * @return bool|string
     */
    public function sendTest(int $idTemplate, string $recipient)
    {
        $template = $this->templateRepository->findById($idTemplate);
        if (!$template) {
            return 'Template not found.';
        }

        if (!Validate::isEmail($recipient)) {
            return 'Invalid test email.';
        }

        try {
            $this->templateRenderer->renderTemplate(
                $template,
                $this->getPreviewContext((string) $template['event_name'])
            );
        } catch (TwigError $exception) {
            return sprintf('Twig syntax error: %s', $exception->getMessage());
        }

        $sent = $this->mailer->sendTemplate(
            $template,
            $recipient,
            null,
            $this->getPreviewContext((string) $template['event_name']),
            (int) ($template['id_lang'] ?: $this->context->language->id),
            (int) ($template['id_shop'] ?: $this->context->shop->id)
        );

        return $sent ? true : 'Test email was not sent. Check logs for details.';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPreviewData(?int $idTemplate): ?array
    {
        if (!$idTemplate) {
            return null;
        }

        $template = $this->templateRepository->findById($idTemplate);
        if (!$template) {
            return null;
        }

        $variables = $this->getPreviewContext((string) $template['event_name']);

        return [
            'name' => (string) $template['name'],
            'subject' => $this->templateRenderer->renderSubject((string) $template['subject'], $variables),
            'html' => $this->templateRenderer->renderHtml((string) $template['html_content'], $variables),
            'text' => $this->templateRenderer->renderText((string) $template['text_content'], $variables),
            'context_source' => !empty($variables['_preview_source']) ? (string) $variables['_preview_source'] : 'sample',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEventGuideData(): array
    {
        $guides = [];
        foreach ($this->getSupportedEvents() as $eventName => $label) {
            $sample = $this->eventContextService->getSampleContext($eventName);
            $guides[$eventName] = [
                'label' => $label,
                'scalar_attributes' => $this->flattenScalarAttributes($sample),
                'collection_attributes' => $this->flattenCollectionAttributes($sample),
                'subject_example' => $this->buildSubjectExample($eventName),
            ];
        }

        return $guides;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPreviewContext(string $eventName): array
    {
        $sample = $this->eventContextService->getSampleContext($eventName);
        $event = $this->eventRepository->findLatestByEvent($eventName, (int) $this->context->shop->id);
        if (!$event || empty($event['payload'])) {
            $sample['_preview_source'] = 'sample';

            return $sample;
        }

        $payload = json_decode((string) $event['payload'], true);
        if (!is_array($payload)) {
            $sample['_preview_source'] = 'sample';

            return $sample;
        }

        $context = $this->mergePreviewContext($sample, $payload);
        $context['_preview_source'] = 'historical';

        return $context;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function mergePreviewContext(array $base, array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                if ($this->isList($base[$key]) || $this->isList($value)) {
                    $base[$key] = $value;

                    continue;
                }

                $base[$key] = $this->mergePreviewContext($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<int, array<string, string>>
     */
    private function flattenScalarAttributes(array $context, string $prefix = ''): array
    {
        $rows = [];
        foreach ($context as $key => $value) {
            if ($key === '_preview_source') {
                continue;
            }

            $path = $prefix === '' ? $key : $prefix . '.' . $key;
            if (is_array($value)) {
                if ($this->isList($value)) {
                    continue;
                }

                $rows = array_merge($rows, $this->flattenScalarAttributes($value, $path));
                continue;
            }

            $rows[] = [
                'path' => $path,
                'twig' => '{{ ' . $path . ' }}',
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<int, array<string, string>>
     */
    private function flattenCollectionAttributes(array $context): array
    {
        return $this->flattenCollectionAttributesByPath($context);
    }

    private function buildSubjectExample(string $eventName): string
    {
        if ($eventName === ModuleConstants::EVENT_CUSTOMER_REGISTERED) {
            return '{{ customer.name }} - Bienvenido a {{ shop.name }}';
        }

        if ($eventName === ModuleConstants::EVENT_NEWSLETTER_REGISTERED) {
            return '{{ shop.name }} - Confirmacion de suscripcion';
        }

        return '{{ order.reference }} - {{ order.status|default("Pedido creado") }}';
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<int, array<string, string>>
     */
    private function flattenCollectionAttributesByPath(array $context, string $prefix = ''): array
    {
        $rows = [];
        foreach ($context as $key => $value) {
            if ($key === '_preview_source' || !is_array($value)) {
                continue;
            }

            $path = $prefix === '' ? $key : $prefix . '.' . $key;
            if ($this->isList($value)) {
                if (empty($value) || !is_array($value[0])) {
                    continue;
                }

                $sampleItem = $value[0];
                $fields = [];
                foreach ($sampleItem as $field => $fieldValue) {
                    if (is_scalar($fieldValue) || $fieldValue === null) {
                        $fields[] = '{{ item.' . $field . ' }}';
                    }
                }

                $rows[] = [
                    'path' => $path,
                    'twig' => "{% for item in " . $path . " %}\n  " . implode("\n  ", array_slice($fields, 0, 3)) . "\n{% endfor %}",
                ];

                continue;
            }

            $rows = array_merge($rows, $this->flattenCollectionAttributesByPath($value, $path));
        }

        return $rows;
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
