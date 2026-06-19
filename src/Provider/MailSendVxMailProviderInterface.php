<?php

namespace Velox\MailSendVx\Provider;

interface MailSendVxMailProviderInterface
{
    /**
     * @param array<string, mixed> $templateVars
     */
    public function send(
        int $idLang,
        string $template,
        string $subject,
        string $to,
        ?string $toName,
        array $templateVars,
        ?int $idShop = null
    ): bool;
}
