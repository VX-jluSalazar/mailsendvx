<?php

namespace Velox\MailSendVx\Install;

use Module;

class Installer
{
    /**
     * @var string[]
     */
    private $hooks = [
        'displayBackOfficeHeader',
        'actionValidateOrder',
        'actionOrderStatusPostUpdate',
        'actionCustomerAccountAdd',
        'actionNewsletterRegistrationAfter',
        'actionCartSave',
    ];

    /**
     * @var ConfigurationInstaller
     */
    private $configurationInstaller;

    /**
     * @var DatabaseInstaller
     */
    private $databaseInstaller;

    /**
     * @var TabInstaller
     */
    private $tabInstaller;

    public function __construct(
        ?ConfigurationInstaller $configurationInstaller = null,
        ?DatabaseInstaller $databaseInstaller = null,
        ?TabInstaller $tabInstaller = null
    ) {
        $this->configurationInstaller = $configurationInstaller ?: new ConfigurationInstaller();
        $this->databaseInstaller = $databaseInstaller ?: new DatabaseInstaller();
        $this->tabInstaller = $tabInstaller ?: new TabInstaller();
    }

    public function install(Module $module): bool
    {
        return $this->databaseInstaller->install()
            && $this->configurationInstaller->install()
            && $this->tabInstaller->install($module->name)
            && $this->registerHooks($module);
    }

    public function uninstall(): bool
    {
        return $this->tabInstaller->uninstall()
            && $this->databaseInstaller->uninstall()
            && $this->configurationInstaller->uninstall();
    }

    public function ensureRuntimeSchema(): bool
    {
        return $this->databaseInstaller->install()
            && $this->configurationInstaller->install(false);
    }

    public function ensureAdminTabs(Module $module): bool
    {
        return $this->tabInstaller->install($module->name);
    }

    public function ensureHooks(Module $module): bool
    {
        foreach ($this->hooks as $hookName) {
            if ($module->isRegisteredInHook($hookName)) {
                continue;
            }

            if (!$module->registerHook($hookName)) {
                return false;
            }
        }

        return true;
    }

    private function registerHooks(Module $module): bool
    {
        return (bool) $module->registerHook($this->hooks);
    }
}
