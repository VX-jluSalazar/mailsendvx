<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

interface DomainTemplateContextBuilderInterface
{
    public function supportsEvent(string $eventName): bool;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildHookContext(string $eventName, array $params): array;

    /**
     * @return array<string, mixed>
     */
    public function buildSampleContext(string $eventName): array;
}
