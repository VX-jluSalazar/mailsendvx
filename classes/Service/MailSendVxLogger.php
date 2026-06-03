<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MailSendVxLogger
{
    /** @var MailSendVxLogRepository */
    private $repository;

    public function __construct(?MailSendVxLogRepository $repository = null)
    {
        $this->repository = $repository ?: new MailSendVxLogRepository();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function info(string $eventName, string $message, array $payload = [], ?string $recipient = null): void
    {
        $this->repository->add($eventName, 'info', $recipient, null, null, $payload, $message);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function error(string $eventName, string $message, array $payload = [], ?string $recipient = null): void
    {
        $this->repository->add($eventName, 'failed', $recipient, null, null, $payload, $message);
    }
}

