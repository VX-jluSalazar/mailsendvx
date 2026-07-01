<?php

namespace Velox\MailSendVx\Service\Template;

class MailSendVxTemplateRenderer
{
    /**
     * @var LegacyPlaceholderTemplateEngine
     */
    private $legacyEngine;

    /**
     * @var TwigTemplateEngine
     */
    private $twigEngine;

    public function __construct(
        LegacyPlaceholderTemplateEngine $legacyEngine,
        TwigTemplateEngine $twigEngine
    ) {
        $this->legacyEngine = $legacyEngine;
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
        $engine = $this->resolveEngine($content);

        return $engine->renderSubject($content, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderHtml(string $content, array $context): string
    {
        $engine = $this->resolveEngine($content);

        return $engine->renderHtml($content, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderText(string $content, array $context): string
    {
        $engine = $this->resolveEngine($content);

        return $engine->renderText($content, $context);
    }

    private function resolveEngine(string $content): TemplateEngineInterface
    {
        if ($this->usesTwigSyntax($content)) {
            return $this->twigEngine;
        }

        return $this->legacyEngine;
    }

    private function usesTwigSyntax(string $content): bool
    {
        return strpos($content, '{{') !== false
            || strpos($content, '{%') !== false
            || strpos($content, '{#') !== false;
    }
}
