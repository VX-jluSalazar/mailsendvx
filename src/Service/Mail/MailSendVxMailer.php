<?php

namespace Velox\MailSendVx\Service\Mail;

use Throwable;
use Velox\MailSendVx\Provider\MailSendVxMailProviderInterface;
use Velox\MailSendVx\Repository\MailSendVxLogRepository;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;
use Velox\MailSendVx\Service\Mail\MailTemplateWrapperService;
use Velox\MailSendVx\Service\Template\MailSendVxTemplateRenderer;

class MailSendVxMailer
{
    /**
     * @var MailSendVxTemplateRepository
     */
    private $templates;

    /**
     * @var MailSendVxLogRepository
     */
    private $logs;

    /**
     * @var MailSendVxMailProviderInterface
     */
    private $provider;

    /**
     * @var MailSendVxTemplateRenderer
     */
    private $renderer;

    /**
     * @var MailTemplateWrapperService
     */
    private $wrapperService;

    public function __construct(
        MailSendVxTemplateRepository $templates,
        MailSendVxLogRepository $logs,
        MailSendVxMailProviderInterface $provider,
        MailSendVxTemplateRenderer $renderer,
        MailTemplateWrapperService $wrapperService
    ) {
        $this->templates = $templates;
        $this->logs = $logs;
        $this->provider = $provider;
        $this->renderer = $renderer;
        $this->wrapperService = $wrapperService;
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function sendEvent(
        string $eventName,
        string $recipient,
        ?string $recipientName,
        array $variables,
        int $idLang,
        int $idShop,
        ?int $idQueue = null
    ): bool
    {
        $template = $this->templates->findActiveByEvent($eventName, $idLang, $idShop);
        if (!$template) {
            $this->logs->add($eventName, 'skipped', $recipient, null, $idQueue, $variables, 'No active template found.', $idShop);

            return false;
        }

        return $this->sendTemplate($template, $recipient, $recipientName, $variables, $idLang, $idShop, $idQueue);
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $variables
     */
    public function sendTemplate(
        array $template,
        string $recipient,
        ?string $recipientName,
        array $variables,
        int $idLang,
        int $idShop,
        ?int $idQueue = null
    ): bool
    {
        $eventName = (string) ($template['event_name'] ?? '');
        if ($eventName === '' && isset($variables['event']['name']) && is_scalar($variables['event']['name'])) {
            $eventName = (string) $variables['event']['name'];
        }
        if ($eventName === '') {
            $eventName = 'template_context:' . (string) ($template['context_type'] ?? 'unknown');
        }

        $renderedTemplate = $this->renderer->renderTemplate($template, $variables);
        $subject = $renderedTemplate['subject'];
        $wrapperName = (string) ($template['mail_template'] ?? 'mailsendvx_default');
        $wrapperContent = $this->wrapperService->getWrapperContent($wrapperName, $idLang);
        $wrapperContext = $variables;
        $wrapperContext['mailsendvx_html_content'] = $renderedTemplate['html'];
        $wrapperContext['mailsendvx_text_content'] = $renderedTemplate['text'];
        $wrappedHtml = $this->renderer->renderHtml((string) ($wrapperContent['html'] ?? ''), $wrapperContext);
        $wrappedText = $this->renderer->renderText((string) ($wrapperContent['text'] ?? ''), $wrapperContext);
        $mailVars = [
            '{mailsendvx_html_content}' => $wrappedHtml,
            '{mailsendvx_text_content}' => $wrappedText,
        ];

        try {
            $sent = $this->provider->send($idLang, 'mailsendvx_runtime', $subject, $recipient, $recipientName, $mailVars, $idShop);
            $this->logs->add(
                $eventName,
                $sent ? 'sent' : 'failed',
                $recipient,
                (int) $template['id_mailsendvx_template'],
                $idQueue,
                $variables,
                $sent ? 'Email accepted by PrestaShop mail transport. Delivery depends on SMTP/sendmail and recipient server.' : 'Mail provider returned false.',
                $idShop
            );

            return $sent;
        } catch (Throwable $exception) {
            $this->logs->add(
                $eventName,
                'failed',
                $recipient,
                (int) $template['id_mailsendvx_template'],
                $idQueue,
                $variables,
                $exception->getMessage(),
                $idShop
            );

            return false;
        }
    }
}
