<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

class EventContextSegmentBuilder
{
    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    public function build(string $eventName, array $extra = []): array
    {
        return array_merge([
            'name' => $eventName,
        ], $extra);
    }
}
