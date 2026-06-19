<?php

namespace Velox\MailSendVx\Repository;

use Context;
use Db;

class MailSendVxEventRepository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function add(
        string $eventName,
        array $payload = [],
        ?string $objectType = null,
        ?string $objectId = null,
        string $status = 'captured',
        ?int $idShop = null
    ): bool {
        return Db::getInstance()->insert('mailsendvx_event', [
            'id_shop' => (int) ($idShop ?: Context::getContext()->shop->id),
            'event_name' => pSQL($eventName),
            'object_type' => $objectType ? pSQL($objectType) : null,
            'object_id' => $objectId ? pSQL($objectId) : null,
            'payload' => pSQL((string) json_encode($payload)),
            'status' => pSQL($status),
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }
}
