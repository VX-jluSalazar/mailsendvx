<?php

namespace Velox\MailSendVx\Install;

use Configuration;
use Tools;
use Velox\MailSendVx\ModuleConstants;

class ConfigurationInstaller
{
    public function install(bool $force = true): bool
    {
        $values = [
            ModuleConstants::CONFIG_ENABLED => '0',
            ModuleConstants::CONFIG_DEBUG => '0',
            ModuleConstants::CONFIG_PROVIDER => 'prestashop_mail',
            ModuleConstants::CONFIG_CRON_TOKEN => Tools::passwdGen(32),
            ModuleConstants::CONFIG_ABANDONED_CART_ENABLED => '0',
            ModuleConstants::CONFIG_ABANDONED_CART_DELAY_VALUE => '1',
            ModuleConstants::CONFIG_ABANDONED_CART_DELAY_UNIT => 'hour',
            ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_CUSTOMER => '1',
            ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_PRODUCTS => '1',
            ModuleConstants::CONFIG_ABANDONED_CART_CRON_BATCH_SIZE => '100',
        ];

        foreach ($values as $key => $value) {
            if ($force || Configuration::get($key) === false) {
                if (!Configuration::updateValue($key, $value)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function uninstall(): bool
    {
        return Configuration::deleteByName(ModuleConstants::CONFIG_ENABLED)
            && Configuration::deleteByName(ModuleConstants::CONFIG_DEBUG)
            && Configuration::deleteByName(ModuleConstants::CONFIG_PROVIDER)
            && Configuration::deleteByName(ModuleConstants::CONFIG_CRON_TOKEN)
            && Configuration::deleteByName(ModuleConstants::CONFIG_ABANDONED_CART_ENABLED)
            && Configuration::deleteByName(ModuleConstants::CONFIG_ABANDONED_CART_DELAY_VALUE)
            && Configuration::deleteByName(ModuleConstants::CONFIG_ABANDONED_CART_DELAY_UNIT)
            && Configuration::deleteByName(ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_CUSTOMER)
            && Configuration::deleteByName(ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_PRODUCTS)
            && Configuration::deleteByName(ModuleConstants::CONFIG_ABANDONED_CART_CRON_BATCH_SIZE);
    }
}
