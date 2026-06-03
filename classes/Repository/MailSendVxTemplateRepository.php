<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MailSendVxTemplateRepository
{
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
}

