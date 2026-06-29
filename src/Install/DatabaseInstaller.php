<?php

namespace Velox\MailSendVx\Install;

use Db;

class DatabaseInstaller
{
    public function install(): bool
    {
        $engine = _MYSQL_ENGINE_;
        $charset = 'DEFAULT CHARSET=utf8mb4';

        $queries = [
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_template` (
                `id_mailsendvx_template` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_lang` INT UNSIGNED NOT NULL DEFAULT 0,
                `event_name` VARCHAR(128) NOT NULL,
                `name` VARCHAR(191) NOT NULL,
                `subject` VARCHAR(191) NOT NULL,
                `mail_template` VARCHAR(128) NOT NULL DEFAULT "mailsendvx_default",
                `html_content` MEDIUMTEXT NULL,
                `text_content` MEDIUMTEXT NULL,
                `json_design` MEDIUMTEXT NULL,
                `provider` VARCHAR(64) NOT NULL DEFAULT "prestashop_mail",
                `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_template`),
                KEY `event_lookup` (`event_name`, `id_shop`, `id_lang`, `active`)
            ) ENGINE=' . $engine . ' ' . $charset,
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_event` (
                `id_mailsendvx_event` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `event_name` VARCHAR(128) NOT NULL,
                `object_type` VARCHAR(64) NULL,
                `object_id` VARCHAR(128) NULL,
                `payload` MEDIUMTEXT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT "captured",
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_event`),
                KEY `event_name` (`event_name`),
                KEY `object_lookup` (`object_type`, `object_id`)
            ) ENGINE=' . $engine . ' ' . $charset,
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_flow` (
                `id_mailsendvx_flow` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `name` VARCHAR(191) NOT NULL,
                `trigger_event` VARCHAR(128) NOT NULL,
                `conditions_json` MEDIUMTEXT NULL,
                `steps_json` MEDIUMTEXT NULL,
                `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_flow`),
                KEY `trigger_event` (`trigger_event`, `active`)
            ) ENGINE=' . $engine . ' ' . $charset,
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_queue` (
                `id_mailsendvx_queue` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_template` INT UNSIGNED NULL,
                `id_flow` INT UNSIGNED NULL,
                `event_name` VARCHAR(128) NOT NULL,
                `recipient` VARCHAR(191) NOT NULL,
                `payload` MEDIUMTEXT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT "pending",
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `scheduled_at` DATETIME NOT NULL,
                `processed_at` DATETIME NULL,
                `last_error` TEXT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_queue`),
                KEY `next_jobs` (`status`, `scheduled_at`),
                KEY `recipient` (`recipient`)
            ) ENGINE=' . $engine . ' ' . $charset,
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_abandoned_cart` (
                `id_mailsendvx_abandoned_cart` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_cart` INT UNSIGNED NOT NULL,
                `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
                `email` VARCHAR(191) NULL,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_lang` INT UNSIGNED NOT NULL DEFAULT 0,
                `status` VARCHAR(32) NOT NULL DEFAULT "active",
                `cart_snapshot` MEDIUMTEXT NULL,
                `last_activity_at` DATETIME NULL,
                `abandoned_at` DATETIME NULL,
                `recovered_at` DATETIME NULL,
                `last_event_hash` VARCHAR(191) NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_abandoned_cart`),
                UNIQUE KEY `uniq_cart` (`id_cart`),
                KEY `status_shop` (`status`, `id_shop`),
                KEY `abandoned_at` (`abandoned_at`)
            ) ENGINE=' . $engine . ' ' . $charset,
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_log` (
                `id_mailsendvx_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_template` INT UNSIGNED NULL,
                `id_queue` INT UNSIGNED NULL,
                `event_name` VARCHAR(128) NOT NULL,
                `recipient` VARCHAR(191) NULL,
                `status` VARCHAR(32) NOT NULL,
                `payload` MEDIUMTEXT NULL,
                `message` TEXT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_log`),
                KEY `event_status` (`event_name`, `status`),
                KEY `date_add` (`date_add`)
            ) ENGINE=' . $engine . ' ' . $charset,
        ];

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    public function uninstall(): bool
    {
        $tables = [
            'mailsendvx_log',
            'mailsendvx_queue',
            'mailsendvx_flow',
            'mailsendvx_event',
            'mailsendvx_abandoned_cart',
            'mailsendvx_template',
        ];

        foreach ($tables as $table) {
            if (!Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . bqSQL($table) . '`')) {
                return false;
            }
        }

        return true;
    }
}
