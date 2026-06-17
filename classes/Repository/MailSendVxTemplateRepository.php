<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MailSendVxTemplateRepository
{
    /**
     * @return array<string, mixed>|false
     */
    public function findByScope(string $eventName, int $idLang, int $idShop, ?int $excludeId = null)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('mailsendvx_template');
        $sql->where('event_name = "' . pSQL($eventName) . '"');
        $sql->where('id_lang = ' . (int) $idLang);
        $sql->where('id_shop = ' . (int) $idShop);

        if ($excludeId) {
            $sql->where('id_mailsendvx_template != ' . (int) $excludeId);
        }

        $sql->orderBy('date_upd DESC, id_mailsendvx_template DESC');

        return Db::getInstance()->getRow($sql);
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findActiveByEvent(string $eventName, int $idLang, int $idShop)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('mailsendvx_template');
        $sql->where('event_name = "' . pSQL($eventName) . '"');
        $sql->where('id_lang IN (0, ' . (int) $idLang . ')');
        $sql->where('id_shop IN (0, ' . (int) $idShop . ')');
        $sql->where('active = 1');
        $sql->orderBy('id_lang DESC, id_shop DESC, date_upd DESC');

        return Db::getInstance()->getRow($sql);
    }

    public function countAll(): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mailsendvx_template`'
        );
    }

    public function hasActiveByEvent(string $eventName, int $idLang, int $idShop): bool
    {
        return (bool) $this->findActiveByEvent($eventName, $idLang, $idShop);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(int $limit = 100): array
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('mailsendvx_template');
        $sql->orderBy('date_upd DESC');
        $sql->limit(max(1, min(500, $limit)));

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findById(int $idTemplate)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('mailsendvx_template');
        $sql->where('id_mailsendvx_template = ' . (int) $idTemplate);

        return Db::getInstance()->getRow($sql);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data, ?int $idTemplate = null): bool
    {
        $now = date('Y-m-d H:i:s');
        $eventName = (string) $data['event_name'];
        $idLang = (int) $data['id_lang'];
        $idShop = (int) $data['id_shop'];

        if (!$idTemplate) {
            $existing = $this->findByScope($eventName, $idLang, $idShop);
            if ($existing) {
                $idTemplate = (int) $existing['id_mailsendvx_template'];
            }
        }

        $row = [
            'id_shop' => $idShop,
            'id_lang' => $idLang,
            'event_name' => pSQL($eventName),
            'name' => pSQL((string) $data['name']),
            'subject' => pSQL((string) $data['subject']),
            'mail_template' => pSQL((string) $data['mail_template']),
            'html_content' => pSQL((string) $data['html_content'], true),
            'text_content' => pSQL((string) $data['text_content'], true),
            'json_design' => isset($data['json_design']) ? pSQL((string) $data['json_design'], true) : null,
            'provider' => pSQL((string) $data['provider']),
            'active' => (int) !empty($data['active']),
            'date_upd' => $now,
        ];

        if ($idTemplate) {
            $updated = Db::getInstance()->update(
                'mailsendvx_template',
                $row,
                'id_mailsendvx_template = ' . (int) $idTemplate
            );

            if ($updated && !empty($data['active'])) {
                $this->deactivateByScope($eventName, $idLang, $idShop, (int) $idTemplate);
            }

            return $updated;
        }

        $row['date_add'] = $now;

        $inserted = Db::getInstance()->insert('mailsendvx_template', $row);
        if ($inserted && !empty($data['active'])) {
            $this->deactivateByScope($eventName, $idLang, $idShop, (int) Db::getInstance()->Insert_ID());
        }

        return $inserted;
    }

    public function delete(int $idTemplate): bool
    {
        return Db::getInstance()->delete(
            'mailsendvx_template',
            'id_mailsendvx_template = ' . (int) $idTemplate
        );
    }

    private function deactivateByScope(string $eventName, int $idLang, int $idShop, int $excludeId): bool
    {
        return Db::getInstance()->update(
            'mailsendvx_template',
            [
                'active' => 0,
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            'event_name = "' . pSQL($eventName) . '"'
            . ' AND id_lang = ' . (int) $idLang
            . ' AND id_shop = ' . (int) $idShop
            . ' AND id_mailsendvx_template != ' . (int) $excludeId
        );
    }
}
