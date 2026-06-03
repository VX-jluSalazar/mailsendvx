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
            _PS_MAIL_DIR_,
            false,
            $idShop ?: null
        );
    }
}

