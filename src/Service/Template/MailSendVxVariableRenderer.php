<?php

namespace Velox\MailSendVx\Service\Template;

class MailSendVxVariableRenderer
{
    /**
     * @var TwigTemplateEngine
     */
    private $twigEngine;

    public function __construct(TwigTemplateEngine $twigEngine)
    {
        $this->twigEngine = $twigEngine;
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function render(string $content, array $variables): string
    {
        return $this->twigEngine->renderHtml($content, $variables);
    }
}
