<?php

namespace Velox\MailSendVx\Service;

class MailSendVxVariableRenderer
{
    /**
     * @param array<string, mixed> $variables
     */
    public function render(string $content, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($content, $replace);
    }
}
