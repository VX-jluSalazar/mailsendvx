<?php

namespace Velox\MailSendVx\Service\Documentation;

use RuntimeException;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Service\Event\EventTemplateContextService;

class FixtureGeneratorService
{
    /**
     * @var EventTemplateContextService
     */
    private $eventContextService;

    public function __construct(EventTemplateContextService $eventContextService)
    {
        $this->eventContextService = $eventContextService;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getDefinitions(): array
    {
        return [
            'order' => [
                'title' => 'Pedido / order',
                'filename' => 'order.json',
                'event_name' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED . '_shipped',
                'context_type' => ModuleConstants::CONTEXT_ORDER,
                'description' => 'Referencia para `order_created`, `order_status_changed` y variantes `order_status_changed_*`.',
            ],
            'cart' => [
                'title' => 'Carrito / cart',
                'filename' => 'cart.json',
                'event_name' => ModuleConstants::EVENT_CART_ABANDONED,
                'context_type' => ModuleConstants::CONTEXT_CART,
                'description' => 'Referencia para `cart_abandoned`, con `cart.items`, `related_products`, `reviews` y datos de recuperacion.',
            ],
            'subscriber' => [
                'title' => 'Newsletter / subscriber',
                'filename' => 'subscriber.json',
                'event_name' => ModuleConstants::EVENT_NEWSLETTER_REGISTERED,
                'context_type' => ModuleConstants::CONTEXT_NEWSLETTER,
                'description' => 'Referencia para `newsletter_registered` con `event.newsletter_action`, `shop.*` y `customer.*`.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateFixture(string $key): array
    {
        $definitions = $this->getDefinitions();
        if (!isset($definitions[$key])) {
            throw new RuntimeException(sprintf('Unknown fixture key "%s".', $key));
        }

        $definition = $definitions[$key];

        return $this->eventContextService->getSampleContextForContextType(
            (string) $definition['context_type'],
            (string) $definition['event_name']
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function generateAllFixtures(): array
    {
        $fixtures = [];

        foreach (array_keys($this->getDefinitions()) as $key) {
            $fixtures[$key] = $this->generateFixture($key);
        }

        return $fixtures;
    }

    /**
     * @return array<string, string>
     */
    public function syncAllToDisk(): array
    {
        $directory = $this->getFixturesDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create fixtures directory "%s".', $directory));
        }

        $writtenFiles = [];
        foreach ($this->getDefinitions() as $key => $definition) {
            $path = $directory . '/' . $definition['filename'];
            $json = (string) json_encode(
                $this->generateFixture($key),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if (@file_put_contents($path, $json . PHP_EOL) === false) {
                throw new RuntimeException(sprintf('Unable to write fixture file "%s".', $path));
            }

            $writtenFiles[$key] = $path;
        }

        return $writtenFiles;
    }

    public function getFixturesDirectory(): string
    {
        return dirname(__DIR__, 3) . '/docs/fixtures';
    }
}
