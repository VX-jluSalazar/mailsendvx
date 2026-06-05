<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MailSendVxPrestaShopMailProvider implements MailSendVxMailProviderInterface
{
    public function send(
        int $idLang,
        string $template,
        string $subject,
        string $to,
        ?string $toName,
        array $templateVars,
        ?int $idShop = null
    ): bool {
        $this->assertMailConfiguration($idShop);
        $templatePath = $this->resolveTemplatePath($template, $idLang);

        return (bool) Mail::Send(
            $idLang,
            $template,
            $subject,
            $templateVars,
            $to,
            $toName ?: null,
            null,
            null,
            null,
            null,
            $templatePath,
            false,
            $idShop ?: null
        );
    }

    private function assertMailConfiguration(?int $idShop): void
    {
        $idShop = $idShop ?: (int) Context::getContext()->shop->id;
        $method = (int) Configuration::get('PS_MAIL_METHOD', null, null, $idShop);

        if ($method === Mail::METHOD_DISABLE) {
            throw new RuntimeException('PrestaShop mail is disabled in email configuration.');
        }

        if ($method === Mail::METHOD_SMTP) {
            $server = (string) Configuration::get('PS_MAIL_SERVER', null, null, $idShop);
            $port = (string) Configuration::get('PS_MAIL_SMTP_PORT', null, null, $idShop);
            if ($server === '' || $port === '') {
                throw new RuntimeException('SMTP mail method is enabled, but SMTP server or port is empty.');
            }
        }
    }

    private function resolveTemplatePath(string $template, int $idLang): string
    {
        $language = new Language($idLang);
        $isoCode = Validate::isLoadedObject($language) ? (string) $language->iso_code : '';
        $moduleMailDir = _PS_MODULE_DIR_ . 'mailsendvx/mails/';

        if ($isoCode && file_exists($moduleMailDir . $isoCode . '/' . $template . '.html')) {
            return $moduleMailDir;
        }

        return _PS_MAIL_DIR_;
    }
}
