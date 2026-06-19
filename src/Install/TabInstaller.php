<?php

namespace Velox\MailSendVx\Install;

use Language;
use Tab;
use Validate;
use Velox\MailSendVx\ModuleConstants;

class TabInstaller
{
    public function install(string $moduleName): bool
    {
        $idParent = $this->createOrUpdateAdminTab(
            $moduleName,
            ModuleConstants::ADMIN_PARENT_TAB_CLASS,
            'Mail Send VELOX',
            $this->getConfigureSectionTabId(),
            'markunread_mailbox',
            'mailsendvx_dashboard'
        );

        if (!$idParent) {
            return false;
        }

        return $this->createOrUpdateAdminTab(
            $moduleName,
            ModuleConstants::ADMIN_CONFIGURE_TAB_CLASS,
            'Configuracion',
            $idParent,
            'settings',
            'mailsendvx_configuration'
        ) && $this->createOrUpdateAdminTab(
            $moduleName,
            ModuleConstants::ADMIN_TEMPLATES_TAB_CLASS,
            'Templates',
            $idParent,
            'mail',
            'mailsendvx_templates'
        ) && $this->createOrUpdateAdminTab(
            $moduleName,
            ModuleConstants::ADMIN_DASHBOARD_TAB_CLASS,
            'Dashboard',
            $idParent,
            'dashboard',
            'mailsendvx_dashboard'
        );
    }

    public function uninstall(): bool
    {
        $classes = [
            ModuleConstants::ADMIN_CONFIGURE_TAB_CLASS,
            ModuleConstants::ADMIN_TEMPLATES_TAB_CLASS,
            ModuleConstants::ADMIN_DASHBOARD_TAB_CLASS,
            ModuleConstants::ADMIN_PARENT_TAB_CLASS,
        ];

        foreach ($classes as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);
            if (!$idTab) {
                continue;
            }

            $tab = new Tab($idTab);
            if (Validate::isLoadedObject($tab) && !$tab->delete()) {
                return false;
            }
        }

        return true;
    }

    private function createOrUpdateAdminTab(string $moduleName, string $className, string $name, int $idParent, string $icon, ?string $routeName = null): int
    {
        $idTab = (int) Tab::getIdFromClassName($className);
        $tab = $idTab ? new Tab($idTab) : new Tab();
        $tab->active = 1;
        $tab->enabled = 1;
        $tab->class_name = $className;
        $tab->route_name = $routeName;
        $tab->module = $moduleName;
        $tab->id_parent = $idParent;
        $tab->icon = $icon;

        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = $name;
        }

        $saved = $idTab ? $tab->update() : $tab->add();

        return $saved ? (int) $tab->id : 0;
    }

    private function getConfigureSectionTabId(): int
    {
        return (int) Tab::getIdFromClassName(ModuleConstants::ADMIN_CONFIGURE_SECTION_CLASS);
    }
}
