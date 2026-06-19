<?php

namespace Velox\MailSendVx\Form;

use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use Velox\MailSendVx\ModuleConstants;

class ConfigurationDataConfiguration implements DataConfigurationInterface
{
    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getConfiguration(): array
    {
        return [
            'enabled' => (bool) $this->configuration->get(ModuleConstants::CONFIG_ENABLED),
            'debug' => (bool) $this->configuration->get(ModuleConstants::CONFIG_DEBUG),
            'provider' => (string) ($this->configuration->get(ModuleConstants::CONFIG_PROVIDER) ?: 'prestashop_mail'),
        ];
    }

    public function updateConfiguration(array $configuration): array
    {
        $this->configuration->set(ModuleConstants::CONFIG_ENABLED, !empty($configuration['enabled']));
        $this->configuration->set(ModuleConstants::CONFIG_DEBUG, !empty($configuration['debug']));
        $this->configuration->set(ModuleConstants::CONFIG_PROVIDER, 'prestashop_mail');

        return [];
    }

    public function validateConfiguration(array $configuration): bool
    {
        return true;
    }
}
