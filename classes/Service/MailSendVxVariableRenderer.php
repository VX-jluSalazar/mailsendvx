<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

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

