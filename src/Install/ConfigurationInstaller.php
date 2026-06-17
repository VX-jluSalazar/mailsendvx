<?php

namespace Velox\MailSendVx\Install;

use Configuration;
use Mailsendvx;
use Tools;

class ConfigurationInstaller
{
    public function install(bool $force = true): bool
    {
        $values = [
            Mailsendvx::CONFIG_ENABLED => '0',
            Mailsendvx::CONFIG_DEBUG => '0',
            Mailsendvx::CONFIG_PROVIDER => 'prestashop_mail',
            Mailsendvx::CONFIG_CRON_TOKEN => Tools::passwdGen(32),
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
        return Configuration::deleteByName(Mailsendvx::CONFIG_ENABLED)
            && Configuration::deleteByName(Mailsendvx::CONFIG_DEBUG)
            && Configuration::deleteByName(Mailsendvx::CONFIG_PROVIDER)
            && Configuration::deleteByName(Mailsendvx::CONFIG_CRON_TOKEN);
    }
}
