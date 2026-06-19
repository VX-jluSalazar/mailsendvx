<?php

namespace Velox\MailSendVx\Service;

use Context;
use Language;
use Validate;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;

class TemplateAdminService
{
    /**
     * @var Context
     */
    private $context;

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
     * @var MailSendVxMailer
     */
    private $mailer;

    /**
     * @var MailSendVxVariableRenderer
     */
    private $variableRenderer;

    public function __construct(
        Context $context,
        OrderStateEventService $orderStateEventService,
        TemplateContentService $templateContentService,
        MailSendVxTemplateRepository $templateRepository,
        MailSendVxMailer $mailer,
        MailSendVxVariableRenderer $variableRenderer
    ) {
        $this->context = $context;
        $this->orderStateEventService = $orderStateEventService;
        $this->templateContentService = $templateContentService;
        $this->templateRepository = $templateRepository;
        $this->mailer = $mailer;
        $this->variableRenderer = $variableRenderer;
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
                ModuleConstants::EVENT_ORDER_STATUS_CHANGED => 'Cambio de estado de pedido',
                ModuleConstants::EVENT_ORDER_STATUS_LEGACY => 'Cambio de estado de pedido (legado)',
                ModuleConstants::EVENT_CUSTOMER_REGISTERED => 'Registro de cliente',
                ModuleConstants::EVENT_NEWSLETTER_REGISTERED => 'Suscripcion newsletter',
            ],
            $this->orderStateEventService->getSupportedEvents([
                'generic' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED,
            ])
        );
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

        return [
            'id_mailsendvx_template' => $template ? (int) $template['id_mailsendvx_template'] : 0,
            'id_shop' => $template ? (int) $template['id_shop'] : (int) $this->context->shop->id,
            'id_lang' => $template ? (int) $template['id_lang'] : (int) $this->context->language->id,
            'event_name' => $eventName,
            'template_name' => $template ? (string) $template['name'] : '',
            'subject' => $template ? (string) $template['subject'] : '',
            'mail_template' => $template ? (string) $template['mail_template'] : 'mailsendvx_default',
            'html_content' => $template ? (string) $template['html_content'] : $this->templateContentService->getDefaultHtmlContent(
                $eventName,
                ModuleConstants::EVENT_CUSTOMER_REGISTERED,
                ModuleConstants::EVENT_NEWSLETTER_REGISTERED
            ),
            'text_content' => $template ? (string) $template['text_content'] : $this->templateContentService->getDefaultTextContent(
                $eventName,
                ModuleConstants::EVENT_CUSTOMER_REGISTERED,
                ModuleConstants::EVENT_NEWSLETTER_REGISTERED
            ),
            'active' => $template ? (bool) $template['active'] : true,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveTemplate(array $data): bool
    {
        $htmlContent = (string) ($data['html_content'] ?? '');
        $textContent = trim((string) ($data['text_content'] ?? ''));
        if ($textContent === '') {
            $textContent = $this->templateContentService->generateTextContentFromHtml($htmlContent);
        }

        return $this->templateRepository->save([
            'id_shop' => (int) ($data['id_shop'] ?? $this->context->shop->id),
            'id_lang' => (int) ($data['id_lang'] ?? $this->context->language->id),
            'event_name' => (string) ($data['event_name'] ?? ''),
            'name' => (string) ($data['template_name'] ?? ''),
            'subject' => (string) ($data['subject'] ?? ''),
            'mail_template' => (string) ($data['mail_template'] ?? 'mailsendvx_default'),
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

        $sent = $this->mailer->sendTemplate(
            $template,
            $recipient,
            null,
            $this->getSampleVariables((string) $template['event_name']),
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

        $variables = $this->getSampleVariables((string) $template['event_name']);

        return [
            'name' => (string) $template['name'],
            'subject' => $this->variableRenderer->render((string) $template['subject'], $variables),
            'html' => $this->variableRenderer->render((string) $template['html_content'], $variables),
            'text' => $this->variableRenderer->render((string) $template['text_content'], $variables),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSampleVariables(string $eventName): array
    {
        return [
            'event_name' => $eventName,
            'id_lang' => (int) $this->context->language->id,
            'id_shop' => (int) $this->context->shop->id,
            'customer_id' => 123,
            'customer_name' => 'Cliente de prueba',
            'customer_firstname' => 'Cliente',
            'customer_lastname' => 'Prueba',
            'customer_email' => 'cliente@example.com',
            'order_id' => 456,
            'order_reference' => 'VX123456',
            'order_total' => '$89.50',
            'order_status' => 'Pago aceptado',
            'old_order_status' => 'Pendiente',
            'order_state_id' => 2,
            'order_state_key' => 'payment_accepted',
            'order_state_name' => 'Pago aceptado',
            'old_order_state_id' => 1,
            'old_order_state_key' => 'awaiting_bank_wire_payment',
            'old_order_state_name' => 'Pendiente',
            'newsletter_action' => 'subscribe',
            'shop_name' => (string) $this->context->shop->name,
            'shop_url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
        ];
    }
}
