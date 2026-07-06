<?php

namespace Velox\MailSendVx\Service\Mail;

use Throwable;
use Velox\MailSendVx\Provider\MailSendVxMailProviderInterface;
use Velox\MailSendVx\Repository\MailSendVxLogRepository;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;
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

    public function __construct(
        MailSendVxTemplateRepository $templates,
        MailSendVxLogRepository $logs,
        MailSendVxMailProviderInterface $provider,
        MailSendVxTemplateRenderer $renderer
    ) {
        $this->templates = $templates;
        $this->logs = $logs;
        $this->provider = $provider;
        $this->renderer = $renderer;
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
        $mailTemplate = (string) $template['mail_template'];
        $mailVars = $this->buildMailVarsFromContext($variables);
        if (isset($mailVars['{shop_unsubscribe_url}']) && !isset($mailVars['{unsubscribe_url}'])) {
            $mailVars['{unsubscribe_url}'] = $mailVars['{shop_unsubscribe_url}'];
        }
        $mailVars['{mailsendvx_html_content}'] = $renderedTemplate['html'];
        $mailVars['{mailsendvx_text_content}'] = $renderedTemplate['text'];

        try {
            $sent = $this->provider->send($idLang, $mailTemplate, $subject, $recipient, $recipientName, $mailVars, $idShop);
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

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, string>
     */
    private function buildMailVarsFromContext(array $context, string $prefix = ''): array
    {
        $mailVars = [];

        foreach ($context as $key => $value) {
            if (!is_string($key) || $key === '' || $key[0] === '_') {
                continue;
            }

            $path = $prefix === '' ? $key : $prefix . '_' . $key;
            if (is_array($value)) {
                $mailVars = array_merge($mailVars, $this->buildMailVarsFromContext($value, $path));
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $mailVars['{' . $path . '}'] = (string) $value;
            }
        }

        return $mailVars;
    }
}
