<?php

namespace Velox\MailSendVx\Service\Template;

class MailSendVxTemplateRenderer
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
     * @param array<string, mixed> $template
     * @param array<string, mixed> $context
     *
     * @return array{subject: string, html: string, text: string}
     */
    public function renderTemplate(array $template, array $context): array
    {
        return [
            'subject' => $this->renderSubject((string) ($template['subject'] ?? ''), $context),
            'html' => $this->renderHtml((string) ($template['html_content'] ?? ''), $context),
            'text' => $this->renderText((string) ($template['text_content'] ?? ''), $context),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderSubject(string $content, array $context): string
    {
        return $this->twigEngine->renderSubject($content, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderHtml(string $content, array $context): string
    {
        return $this->twigEngine->renderHtml($content, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderText(string $content, array $context): string
    {
        return $this->twigEngine->renderText($content, $context);
    }
}
