<?php

namespace Velox\MailSendVx\Repository;

use Context;
use Db;
use DbQuery;

class MailSendVxLogRepository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function add(
        string $eventName,
        string $status,
        ?string $recipient = null,
        ?int $idTemplate = null,
        ?int $idQueue = null,
        array $payload = [],
        ?string $message = null,
        ?int $idShop = null
    ): bool {
        return Db::getInstance()->insert('mailsendvx_log', [
            'id_shop' => (int) ($idShop ?: Context::getContext()->shop->id),
            'id_template' => $idTemplate ? (int) $idTemplate : null,
            'id_queue' => $idQueue ? (int) $idQueue : null,
            'event_name' => pSQL($eventName),
            'recipient' => $recipient ? pSQL($recipient) : null,
            'status' => pSQL($status),
            'payload' => pSQL((string) json_encode($payload)),
            'message' => $message ? pSQL($message) : null,
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 10): array
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('mailsendvx_log');
        $sql->orderBy('date_add DESC');
        $sql->limit(max(1, min(100, $limit)));

        return Db::getInstance()->executeS($sql) ?: [];
    }
}
