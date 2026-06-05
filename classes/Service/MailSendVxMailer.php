<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MailSendVxMailer
{
    /** @var MailSendVxTemplateRepository */
    private $templates;

    /** @var MailSendVxLogRepository */
    private $logs;

    /** @var MailSendVxMailProviderInterface */
    private $provider;

    /** @var MailSendVxVariableRenderer */
    private $renderer;

    public function __construct(
        ?MailSendVxTemplateRepository $templates = null,
        ?MailSendVxLogRepository $logs = null,
        ?MailSendVxMailProviderInterface $provider = null,
        ?MailSendVxVariableRenderer $renderer = null
    ) {
        $this->templates = $templates ?: new MailSendVxTemplateRepository();
        $this->logs = $logs ?: new MailSendVxLogRepository();
        $this->provider = $provider ?: new MailSendVxPrestaShopMailProvider();
        $this->renderer = $renderer ?: new MailSendVxVariableRenderer();
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function sendEvent(string $eventName, string $recipient, ?string $recipientName, array $variables, int $idLang, int $idShop): bool
    {
        $template = $this->templates->findActiveByEvent($eventName, $idLang, $idShop);
        if (!$template) {
            $this->logs->add($eventName, 'skipped', $recipient, null, null, $variables, 'No active template found.', $idShop);

            return false;
        }

        return $this->sendTemplate($template, $recipient, $recipientName, $variables, $idLang, $idShop);
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $variables
     */
    public function sendTemplate(array $template, string $recipient, ?string $recipientName, array $variables, int $idLang, int $idShop): bool
    {
        $eventName = (string) $template['event_name'];
        $subject = $this->renderer->render((string) $template['subject'], $variables);
        $mailTemplate = (string) $template['mail_template'];
        $mailVars = [];
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $mailVars['{' . $key . '}'] = (string) $value;
            }
        }
        $mailVars['{mailsendvx_html_content}'] = $this->renderer->render((string) $template['html_content'], $variables);
        $mailVars['{mailsendvx_text_content}'] = $this->renderer->render((string) $template['text_content'], $variables);

        try {
            $sent = $this->provider->send($idLang, $mailTemplate, $subject, $recipient, $recipientName, $mailVars, $idShop);
            $this->logs->add(
                $eventName,
                $sent ? 'sent' : 'failed',
                $recipient,
                (int) $template['id_mailsendvx_template'],
                null,
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
                null,
                $variables,
                $exception->getMessage(),
                $idShop
            );

            return false;
        }
    }
}
