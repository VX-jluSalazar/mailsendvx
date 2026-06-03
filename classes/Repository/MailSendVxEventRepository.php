<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

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
            'payload' => pSQL(json_encode($payload)),
            'status' => pSQL($status),
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }
}

