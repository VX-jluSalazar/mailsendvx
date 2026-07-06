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
            'abandoned_cart_enabled' => (bool) $this->configuration->get(ModuleConstants::CONFIG_ABANDONED_CART_ENABLED),
            'abandoned_cart_delay_value' => (int) ($this->configuration->get(ModuleConstants::CONFIG_ABANDONED_CART_DELAY_VALUE) ?: 1),
            'abandoned_cart_delay_unit' => (string) ($this->configuration->get(ModuleConstants::CONFIG_ABANDONED_CART_DELAY_UNIT) ?: 'hour'),
            'abandoned_cart_require_customer' => (bool) $this->configuration->get(ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_CUSTOMER),
            'abandoned_cart_require_products' => (bool) $this->configuration->get(ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_PRODUCTS),
            'abandoned_cart_cron_batch_size' => (int) ($this->configuration->get(ModuleConstants::CONFIG_ABANDONED_CART_CRON_BATCH_SIZE) ?: 100),
        ];
    }

    public function updateConfiguration(array $configuration): array
    {
        $this->configuration->set(ModuleConstants::CONFIG_ENABLED, !empty($configuration['enabled']));
        $this->configuration->set(ModuleConstants::CONFIG_DEBUG, !empty($configuration['debug']));
        $this->configuration->set(ModuleConstants::CONFIG_PROVIDER, 'prestashop_mail');
        $this->configuration->set(ModuleConstants::CONFIG_ABANDONED_CART_ENABLED, !empty($configuration['abandoned_cart_enabled']));
        $this->configuration->set(ModuleConstants::CONFIG_ABANDONED_CART_DELAY_VALUE, max(0, (int) ($configuration['abandoned_cart_delay_value'] ?? 1)));
        $this->configuration->set(ModuleConstants::CONFIG_ABANDONED_CART_DELAY_UNIT, (string) ($configuration['abandoned_cart_delay_unit'] ?? 'hour'));
        $this->configuration->set(ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_CUSTOMER, !empty($configuration['abandoned_cart_require_customer']));
        $this->configuration->set(ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_PRODUCTS, !empty($configuration['abandoned_cart_require_products']));
        $this->configuration->set(ModuleConstants::CONFIG_ABANDONED_CART_CRON_BATCH_SIZE, max(1, (int) ($configuration['abandoned_cart_cron_batch_size'] ?? 100)));

        return [];
    }

    public function validateConfiguration(array $configuration): bool
    {
        return true;
    }
}
