<?php

namespace Velox\MailSendVx\Service;

use Context;
use Language;
use Validate;
use Twig\Error\Error as TwigError;
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
     * @var MailSendVxTemplateRenderer
     */
    private $templateRenderer;

    public function __construct(
        Context $context,
        OrderStateEventService $orderStateEventService,
        TemplateContentService $templateContentService,
        MailSendVxTemplateRepository $templateRepository,
        MailSendVxMailer $mailer,
        MailSendVxTemplateRenderer $templateRenderer
    ) {
        $this->context = $context;
        $this->orderStateEventService = $orderStateEventService;
        $this->templateContentService = $templateContentService;
        $this->templateRepository = $templateRepository;
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
                ModuleConstants::EVENT_ORDER_CREATED,
                ModuleConstants::EVENT_CUSTOMER_REGISTERED,
                ModuleConstants::EVENT_NEWSLETTER_REGISTERED
            ),
            'text_content' => $template ? (string) $template['text_content'] : $this->templateContentService->getDefaultTextContent(
                $eventName,
                ModuleConstants::EVENT_ORDER_CREATED,
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

        try {
            $this->templateRenderer->renderTemplate(
                $template,
                $this->getSampleVariables((string) $template['event_name'])
            );
        } catch (TwigError $exception) {
            return sprintf('Twig syntax error: %s', $exception->getMessage());
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
            'subject' => $this->templateRenderer->renderSubject((string) $template['subject'], $variables),
            'html' => $this->templateRenderer->renderHtml((string) $template['html_content'], $variables),
            'text' => $this->templateRenderer->renderText((string) $template['text_content'], $variables),
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
            'order_totals' => [
                'paid' => '$89.50',
                'products' => '$75.00',
                'shipping' => '$9.50',
                'discounts' => '$5.00',
                'tax' => '$10.00',
            ],
            'billing_address' => [
                'firstname' => 'Cliente',
                'lastname' => 'Prueba',
                'full_name' => 'Cliente Prueba',
                'company' => 'Velox Labs',
                'address1' => 'Av. Siempre Viva 123',
                'address2' => 'Depto 4B',
                'city' => 'Guayaquil',
                'postcode' => '090101',
                'country' => 'Ecuador',
                'state' => 'Guayas',
                'phone' => '+593999999999',
                'phone_mobile' => '+593988888888',
                'formatted' => "Cliente Prueba\nAv. Siempre Viva 123\nDepto 4B\nGuayaquil 090101\nEcuador",
            ],
            'shipping_address' => [
                'firstname' => 'Cliente',
                'lastname' => 'Prueba',
                'full_name' => 'Cliente Prueba',
                'company' => '',
                'address1' => 'Calle Comercio 456',
                'address2' => '',
                'city' => 'Samborondon',
                'postcode' => '092301',
                'country' => 'Ecuador',
                'state' => 'Guayas',
                'phone' => '+593977777777',
                'phone_mobile' => '',
                'formatted' => "Cliente Prueba\nCalle Comercio 456\nSamborondon 092301\nEcuador",
            ],
            'shipping' => [
                'carrier_name' => 'Envio express',
                'cost' => '$9.50',
                'tracking_url' => 'https://example.com/tracking/VX123456',
            ],
            'products' => [
                [
                    'id' => 10,
                    'attribute_id' => 0,
                    'name' => 'Camisa Azul',
                    'reference' => 'CA-001',
                    'quantity' => 2,
                    'unit_price' => '$25.00',
                    'total_price' => '$50.00',
                    'url' => 'https://example.com/camisa-azul',
                    'image_url' => 'https://via.placeholder.com/120x120.png?text=Camisa+Azul',
                ],
                [
                    'id' => 11,
                    'attribute_id' => 0,
                    'name' => 'Pantalon Negro',
                    'reference' => 'PN-010',
                    'quantity' => 1,
                    'unit_price' => '$25.00',
                    'total_price' => '$25.00',
                    'url' => 'https://example.com/pantalon-negro',
                    'image_url' => 'https://via.placeholder.com/120x120.png?text=Pantalon+Negro',
                ],
            ],
            'related_products' => [
                [
                    'id' => 21,
                    'name' => 'Zapatos Urbanos',
                    'price' => '$59.00',
                    'url' => 'https://example.com/zapatos-urbanos',
                    'image_url' => 'https://via.placeholder.com/120x120.png?text=Zapatos',
                ],
            ],
            'reviews' => [
                [
                    'author' => 'Maria',
                    'rating' => 5,
                    'title' => 'Excelente compra',
                    'content' => 'Entrega rapida y producto en perfecto estado.',
                ],
            ],
            'newsletter_action' => 'subscribe',
            'shop_name' => (string) $this->context->shop->name,
            'shop_url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
        ];
    }
}
