<?php

namespace Velox\MailSendVx\Repository;

use Context;
use Db;

class MailSendVxQueueRepository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function schedule(
        string $eventName,
        string $recipient,
        array $payload,
        string $scheduledAt,
        ?int $idTemplate = null,
        ?int $idFlow = null,
        ?int $idShop = null
    ): bool {
        return Db::getInstance()->insert('mailsendvx_queue', [
            'id_shop' => (int) ($idShop ?: Context::getContext()->shop->id),
            'id_template' => $idTemplate ? (int) $idTemplate : null,
            'id_flow' => $idFlow ? (int) $idFlow : null,
            'event_name' => pSQL($eventName),
            'recipient' => pSQL($recipient),
            'payload' => pSQL((string) json_encode($payload)),
            'status' => 'scheduled',
            'attempts' => 0,
            'scheduled_at' => pSQL($scheduledAt),
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);
    }

    public function countByStatus(string $status): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mailsendvx_queue` WHERE `status` = "' . pSQL($status) . '"'
        );
    }
}
