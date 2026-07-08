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
                `event_name` VARCHAR(128) NULL,
                `context_type` VARCHAR(64) NOT NULL DEFAULT "",
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
                KEY `event_lookup` (`event_name`, `id_shop`, `id_lang`, `active`),
                KEY `context_lookup` (`context_type`, `id_shop`, `id_lang`, `active`)
            ) ENGINE=' . $engine . ' ' . $charset,
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_wrapper` (
                `id_mailsendvx_wrapper` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_lang` INT UNSIGNED NOT NULL DEFAULT 0,
                `name` VARCHAR(128) NOT NULL,
                `html_content` MEDIUMTEXT NULL,
                `text_content` MEDIUMTEXT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_wrapper`),
                UNIQUE KEY `uniq_wrapper_scope` (`name`, `id_shop`, `id_lang`),
                KEY `wrapper_scope` (`id_shop`, `id_lang`, `name`)
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
                `context_type` VARCHAR(64) NOT NULL DEFAULT "",
                `description` TEXT NULL,
                `priority` INT NOT NULL DEFAULT 0,
                `conditions_json` MEDIUMTEXT NULL,
                `steps_json` MEDIUMTEXT NULL,
                `version` INT UNSIGNED NOT NULL DEFAULT 1,
                `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_flow`),
                KEY `trigger_event` (`trigger_event`, `active`),
                KEY `context_priority` (`context_type`, `priority`, `active`)
            ) ENGINE=' . $engine . ' ' . $charset,
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mailsendvx_queue` (
                `id_mailsendvx_queue` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_template` INT UNSIGNED NULL,
                `id_flow` INT UNSIGNED NULL,
                `flow_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `step_id` VARCHAR(128) NULL,
                `event_name` VARCHAR(128) NOT NULL,
                `recipient` VARCHAR(191) NOT NULL,
                `payload` MEDIUMTEXT NULL,
                `payload_json` MEDIUMTEXT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT "pending",
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
                `scheduled_at` DATETIME NOT NULL,
                `processed_at` DATETIME NULL,
                `locked_at` DATETIME NULL,
                `lock_token` VARCHAR(64) NULL,
                `last_error` TEXT NULL,
                `cancel_reason` VARCHAR(191) NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_mailsendvx_queue`),
                KEY `next_jobs` (`status`, `scheduled_at`),
                KEY `flow_step` (`id_flow`, `step_id`),
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

        return $this->ensureTemplateSchema()
            && $this->ensureWrapperSchema()
            && $this->ensureFlowSchema()
            && $this->ensureQueueSchema();
    }

    public function uninstall(): bool
    {
        $tables = [
            'mailsendvx_log',
            'mailsendvx_queue',
            'mailsendvx_flow',
            'mailsendvx_event',
            'mailsendvx_abandoned_cart',
            'mailsendvx_wrapper',
            'mailsendvx_template',
        ];

        foreach ($tables as $table) {
            if (!Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . bqSQL($table) . '`')) {
                return false;
            }
        }

        return true;
    }

    private function ensureTemplateSchema(): bool
    {
        $table = _DB_PREFIX_ . 'mailsendvx_template';

        return $this->ensureNullableColumn($table, 'event_name', 'ALTER TABLE `' . $table . '` MODIFY `event_name` VARCHAR(128) NULL')
            && $this->ensureColumn($table, 'context_type', 'ALTER TABLE `' . $table . '` ADD `context_type` VARCHAR(64) NOT NULL DEFAULT "" AFTER `event_name`')
            && $this->executeSilently('UPDATE `' . $table . '` SET `context_type` = "order" WHERE (`context_type` IS NULL OR `context_type` = "") AND (`event_name` = "order_created" OR `event_name` = "order_status_changed" OR `event_name` = "order_status_updated" OR `event_name` LIKE "order_status_changed\\_%")')
            && $this->executeSilently('UPDATE `' . $table . '` SET `context_type` = "cart" WHERE (`context_type` IS NULL OR `context_type` = "") AND `event_name` = "cart_abandoned"')
            && $this->executeSilently('UPDATE `' . $table . '` SET `context_type` = "customer" WHERE (`context_type` IS NULL OR `context_type` = "") AND `event_name` = "customer_registered"')
            && $this->executeSilently('UPDATE `' . $table . '` SET `context_type` = "newsletter" WHERE (`context_type` IS NULL OR `context_type` = "") AND `event_name` = "newsletter_registered"')
            && $this->ensureIndex($table, 'context_lookup', 'ALTER TABLE `' . $table . '` ADD KEY `context_lookup` (`context_type`, `id_shop`, `id_lang`, `active`)');
    }

    private function ensureFlowSchema(): bool
    {
        $table = _DB_PREFIX_ . 'mailsendvx_flow';

        return $this->ensureColumn($table, 'context_type', 'ALTER TABLE `' . $table . '` ADD `context_type` VARCHAR(64) NOT NULL DEFAULT "" AFTER `trigger_event`')
            && $this->ensureColumn($table, 'description', 'ALTER TABLE `' . $table . '` ADD `description` TEXT NULL AFTER `context_type`')
            && $this->ensureColumn($table, 'priority', 'ALTER TABLE `' . $table . '` ADD `priority` INT NOT NULL DEFAULT 0 AFTER `active`')
            && $this->ensureColumn($table, 'version', 'ALTER TABLE `' . $table . '` ADD `version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `steps_json`')
            && $this->executeSilently('UPDATE `' . $table . '` SET `context_type` = "order" WHERE (`context_type` IS NULL OR `context_type` = "") AND (`trigger_event` = "order_created" OR `trigger_event` = "order_status_changed" OR `trigger_event` = "order_status_updated" OR `trigger_event` LIKE "order_status_changed\\_%")')
            && $this->executeSilently('UPDATE `' . $table . '` SET `context_type` = "cart" WHERE (`context_type` IS NULL OR `context_type` = "") AND `trigger_event` = "cart_abandoned"')
            && $this->executeSilently('UPDATE `' . $table . '` SET `context_type` = "customer" WHERE (`context_type` IS NULL OR `context_type` = "") AND `trigger_event` = "customer_registered"')
            && $this->executeSilently('UPDATE `' . $table . '` SET `context_type` = "newsletter" WHERE (`context_type` IS NULL OR `context_type` = "") AND `trigger_event` = "newsletter_registered"')
            && $this->ensureIndex($table, 'context_priority', 'ALTER TABLE `' . $table . '` ADD KEY `context_priority` (`context_type`, `priority`, `active`)');
    }

    private function ensureWrapperSchema(): bool
    {
        $table = _DB_PREFIX_ . 'mailsendvx_wrapper';

        return $this->ensureIndex($table, 'uniq_wrapper_scope', 'ALTER TABLE `' . $table . '` ADD UNIQUE KEY `uniq_wrapper_scope` (`name`, `id_shop`, `id_lang`)')
            && $this->ensureIndex($table, 'wrapper_scope', 'ALTER TABLE `' . $table . '` ADD KEY `wrapper_scope` (`id_shop`, `id_lang`, `name`)');
    }

    private function ensureQueueSchema(): bool
    {
        $table = _DB_PREFIX_ . 'mailsendvx_queue';

        return $this->ensureColumn($table, 'flow_version', 'ALTER TABLE `' . $table . '` ADD `flow_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id_flow`')
            && $this->ensureColumn($table, 'step_id', 'ALTER TABLE `' . $table . '` ADD `step_id` VARCHAR(128) NULL AFTER `flow_version`')
            && $this->ensureColumn($table, 'payload_json', 'ALTER TABLE `' . $table . '` ADD `payload_json` MEDIUMTEXT NULL AFTER `payload`')
            && $this->ensureColumn($table, 'max_attempts', 'ALTER TABLE `' . $table . '` ADD `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER `attempts`')
            && $this->ensureColumn($table, 'locked_at', 'ALTER TABLE `' . $table . '` ADD `locked_at` DATETIME NULL AFTER `processed_at`')
            && $this->ensureColumn($table, 'lock_token', 'ALTER TABLE `' . $table . '` ADD `lock_token` VARCHAR(64) NULL AFTER `locked_at`')
            && $this->ensureColumn($table, 'cancel_reason', 'ALTER TABLE `' . $table . '` ADD `cancel_reason` VARCHAR(191) NULL AFTER `last_error`')
            && $this->executeSilently('UPDATE `' . $table . '` SET `payload_json` = `payload` WHERE (`payload_json` IS NULL OR `payload_json` = "") AND `payload` IS NOT NULL')
            && $this->ensureIndex($table, 'flow_step', 'ALTER TABLE `' . $table . '` ADD KEY `flow_step` (`id_flow`, `step_id`)');
    }

    private function ensureColumn(string $table, string $column, string $sql): bool
    {
        if ($this->columnExists($table, $column)) {
            if (strpos($sql, ' MODIFY ') === false) {
                return true;
            }
        }

        return $this->executeSilently($sql);
    }

    private function ensureNullableColumn(string $table, string $column, string $sql): bool
    {
        $definition = $this->getColumnDefinition($table, $column);
        if (empty($definition)) {
            return false;
        }

        if (isset($definition['Null']) && strtoupper((string) $definition['Null']) === 'YES') {
            return true;
        }

        return $this->executeSilently($sql);
    }

    private function ensureIndex(string $table, string $indexName, string $sql): bool
    {
        if ($this->indexExists($table, $indexName)) {
            return true;
        }

        return $this->executeSilently($sql);
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->getColumnDefinition($table, $column);

        return !empty($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function getColumnDefinition(string $table, string $column): array
    {
        $result = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . bqSQL($table) . '` LIKE "' . pSQL($column) . '"');

        if (!is_array($result) || empty($result[0]) || !is_array($result[0])) {
            return [];
        }

        return $result[0];
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = Db::getInstance()->executeS('SHOW INDEX FROM `' . bqSQL($table) . '` WHERE Key_name = "' . pSQL($indexName) . '"');

        return !empty($result);
    }

    private function executeSilently(string $sql): bool
    {
        return (bool) Db::getInstance()->execute($sql);
    }
}
