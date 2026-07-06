<?php

namespace Velox\MailSendVx\Service\Template;

class LegacyPlaceholderTemplateEngine implements TemplateEngineInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function renderSubject(string $content, array $context): string
    {
        return $this->render($content, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderHtml(string $content, array $context): string
    {
        return $this->render($content, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderText(string $content, array $context): string
    {
        return $this->render($content, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $content, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($content, $replace);
    }
}
