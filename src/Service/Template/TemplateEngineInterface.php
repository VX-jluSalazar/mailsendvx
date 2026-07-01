<?php

namespace Velox\MailSendVx\Service\Template;

interface TemplateEngineInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function renderSubject(string $content, array $context): string;

    /**
     * @param array<string, mixed> $context
     */
    public function renderHtml(string $content, array $context): string;

    /**
     * @param array<string, mixed> $context
     */
    public function renderText(string $content, array $context): string;
}
