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
            'abandoned_cart_cron_batch_size' => (int) ($this->configuration->get(ModuleConstants::CONFIG_ABANDONED_CART_CRON_BATCH_SIZE) ?: 100),
            'primary_500' => (string) ($this->configuration->get(ModuleConstants::CONFIG_COLOR_PRIMARY_500) ?: '#1B3A5C'),
            'secondary_500' => (string) ($this->configuration->get(ModuleConstants::CONFIG_COLOR_SECONDARY_500) ?: '#C4690A'),
            'neutral_500' => (string) ($this->configuration->get(ModuleConstants::CONFIG_COLOR_NEUTRAL_500) ?: '#6E6A62'),
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
        $this->configuration->set(ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_CUSTOMER, true);
        $this->configuration->set(ModuleConstants::CONFIG_ABANDONED_CART_REQUIRE_PRODUCTS, true);
        $this->configuration->set(ModuleConstants::CONFIG_ABANDONED_CART_CRON_BATCH_SIZE, max(1, (int) ($configuration['abandoned_cart_cron_batch_size'] ?? 100)));
        $this->configuration->set(ModuleConstants::CONFIG_COLOR_PRIMARY_500, $this->normalizeHexColor((string) ($configuration['primary_500'] ?? ''), '#1B3A5C'));
        $this->configuration->set(ModuleConstants::CONFIG_COLOR_SECONDARY_500, $this->normalizeHexColor((string) ($configuration['secondary_500'] ?? ''), '#C4690A'));
        $this->configuration->set(ModuleConstants::CONFIG_COLOR_NEUTRAL_500, $this->normalizeHexColor((string) ($configuration['neutral_500'] ?? ''), '#6E6A62'));

        return [];
    }

    public function validateConfiguration(array $configuration): bool
    {
        return true;
    }

    private function normalizeHexColor(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $value, $matches)) {
            $value = '#' . $matches[1][0] . $matches[1][0] . $matches[1][1] . $matches[1][1] . $matches[1][2] . $matches[1][2];
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return $fallback;
        }

        return strtoupper($value);
    }
}
