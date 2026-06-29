<?php

namespace Velox\MailSendVx\Service;

class MailSendVxVariableRenderer
{
    /**
     * @param array<string, mixed> $variables
     */
    public function render(string $content, array $variables): string
    {
        $engine = new LegacyPlaceholderTemplateEngine();

        return $engine->renderHtml($content, $variables);
    }
}
