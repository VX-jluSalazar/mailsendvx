<?php

namespace Velox\MailSendVx\Controller\Admin;

use Configuration;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Service\Documentation\FixtureGeneratorService;
use Velox\MailSendVx\Service\Template\TemplateAdminService;
use Velox\MailSendVx\Service\Theme\ColorPaletteProvider;

class DocumentationController extends FrameworkBundleAdminController
{
    /**
     * @var TemplateAdminService
     */
    private $templateAdminService;

    /**
     * @var FixtureGeneratorService
     */
    private $fixtureGenerator;

    /**
     * @var ColorPaletteProvider
     */
    private $colorPaletteProvider;

    public function __construct(TemplateAdminService $templateAdminService, FixtureGeneratorService $fixtureGenerator, ColorPaletteProvider $colorPaletteProvider)
    {
        parent::__construct();
        $this->templateAdminService = $templateAdminService;
        $this->fixtureGenerator = $fixtureGenerator;
        $this->colorPaletteProvider = $colorPaletteProvider;
    }

    public function indexAction(Request $request): Response
    {
        $contextBuilderSections = $this->buildContextBuilderSections();
        $fixtureSyncError = null;

        try {
            $this->fixtureGenerator->syncAllToDisk();
        } catch (RuntimeException $exception) {
            $fixtureSyncError = $exception->getMessage();
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/documentation.html.twig', [
            'shopName' => (string) $this->getContext()->shop->name,
            'contextBuilderSections' => $contextBuilderSections,
            'cronGuides' => $this->getCronGuides(),
            'fixtureGuides' => $this->getFixtureGuides(),
            'fixtureSyncError' => $fixtureSyncError,
            'colorPaletteGuide' => $this->colorPaletteProvider->getDocumentationGuide(),
        ]);
    }

    public function downloadFixtureAction(string $fixtureKey): Response
    {
        $definitions = $this->fixtureGenerator->getDefinitions();
        if (!isset($definitions[$fixtureKey])) {
            throw $this->createNotFoundException(sprintf('Fixture "%s" not found.', $fixtureKey));
        }

        $json = (string) json_encode(
            $this->fixtureGenerator->generateFixture($fixtureKey),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $response = new Response($json . PHP_EOL);
        $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $definitions[$fixtureKey]['filename']));

        return $response;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildContextBuilderSections(): array
    {
        $eventGuides = $this->templateAdminService->getEventGuideData();
        $supportedEvents = $this->templateAdminService->getSupportedEvents();
        $sectionsConfig = [
            [
                'title' => 'Variables de Pedido',
                'description' => 'Variables disponibles para correos de pedido, cambios de estado y automatizaciones relacionadas.',
                'matches' => static function (string $eventName): bool {
                    return $eventName === ModuleConstants::EVENT_ORDER_CREATED
                        || $eventName === ModuleConstants::EVENT_ORDER_STATUS_CHANGED
                        || $eventName === ModuleConstants::EVENT_ORDER_STATUS_LEGACY
                        || strpos($eventName, ModuleConstants::EVENT_ORDER_STATUS_CHANGED . '_') === 0;
                },
            ],
            [
                'title' => 'Variables de Cliente',
                'description' => 'Variables disponibles para emails disparados cuando se registra un nuevo cliente.',
                'matches' => static function (string $eventName): bool {
                    return $eventName === ModuleConstants::EVENT_CUSTOMER_REGISTERED;
                },
            ],
            [
                'title' => 'Variables de Newsletter',
                'description' => 'Variables disponibles para mensajes de suscripcion y automatizaciones de newsletter.',
                'matches' => static function (string $eventName): bool {
                    return $eventName === ModuleConstants::EVENT_NEWSLETTER_REGISTERED;
                },
            ],
            [
                'title' => 'Variables de Carrito',
                'description' => 'Variables disponibles para recuperacion de carrito y secuencias de carrito abandonado.',
                'matches' => static function (string $eventName): bool {
                    return $eventName === ModuleConstants::EVENT_CART_ABANDONED;
                },
            ],
        ];

        $sections = [];
        foreach ($sectionsConfig as $sectionConfig) {
            $matchedEventNames = [];
            foreach ($supportedEvents as $eventName => $label) {
                $matches = $sectionConfig['matches'];
                if ($matches($eventName)) {
                    $matchedEventNames[$eventName] = $label;
                }
            }

            $scalarAttributes = [];
            $collectionAttributes = [];
            foreach ($matchedEventNames as $eventName => $label) {
                if (!isset($eventGuides[$eventName])) {
                    continue;
                }

                foreach ($eventGuides[$eventName]['scalar_attributes'] ?? [] as $item) {
                    $path = (string) ($item['path'] ?? '');
                    if ($path !== '') {
                        $scalarAttributes[$path] = $item;
                    }
                }

                foreach ($eventGuides[$eventName]['collection_attributes'] ?? [] as $item) {
                    $path = (string) ($item['path'] ?? '');
                    if ($path !== '') {
                        $collectionAttributes[$path] = $item;
                    }
                }
            }

            ksort($scalarAttributes);
            ksort($collectionAttributes);

            $scalarAttributes = array_filter($scalarAttributes, static function (array $item): bool {
                $path = (string) ($item['path'] ?? '');

                return strpos($path, '.') !== false;
            });

            $variables = [];
            foreach ($scalarAttributes as $item) {
                $variables[] = [
                    'path' => (string) ($item['path'] ?? ''),
                    'twig' => (string) ($item['twig'] ?? ''),
                ];
            }

            foreach ($collectionAttributes as $item) {
                $variables[] = [
                    'path' => (string) ($item['path'] ?? ''),
                    'twig' => (string) ($item['twig'] ?? ''),
                ];
            }

            $sections[] = [
                'title' => $sectionConfig['title'],
                'description' => $sectionConfig['description'],
                'events' => array_values($matchedEventNames),
                'variables' => $variables,
            ];
        }

        return $sections;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getCronGuides(): array
    {
        $token = (string) Configuration::get(ModuleConstants::CONFIG_CRON_TOKEN);
        $queueCronUrl = $this->getContext()->link->getModuleLink('mailsendvx', 'queuecron', [
            'token' => $token,
            'limit' => 50,
        ], true);
        $abandonedCartCronUrl = $this->getContext()->link->getModuleLink('mailsendvx', 'abandonedcartcron', [
            'token' => $token,
        ], true);

        return [
            [
                'title' => 'Queue / flows worker',
                'frequency' => 'Cada minuto',
                'importance' => 'Obligatorio',
                'description' => 'Procesa los correos pendientes o programados de flows. Sin este cron, los steps con delay no se enviaran solos.',
                'url' => $queueCronUrl,
                'command' => sprintf('* * * * * /usr/bin/curl -fsS "%s" >/dev/null 2>&1', $queueCronUrl),
            ],
            [
                'title' => 'Deteccion de carrito abandonado',
                'frequency' => 'Cada 5 minutos',
                'importance' => 'Recomendado',
                'description' => 'Escanea carritos candidatos y registra el evento `cart_abandoned`. Si quieres una reaccion mas rapida puedes correrlo cada minuto.',
                'url' => $abandonedCartCronUrl,
                'command' => sprintf('*/5 * * * * /usr/bin/curl -fsS "%s" >/dev/null 2>&1', $abandonedCartCronUrl),
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getFixtureGuides(): array
    {
        $guides = [];

        foreach ($this->fixtureGenerator->getDefinitions() as $key => $definition) {
            $guides[] = [
                'title' => $definition['title'],
                'path' => 'modules/mailsendvx/docs/fixtures/' . $definition['filename'],
                'download_url' => $this->generateUrl('mailsendvx_documentation_fixture_download', [
                    'fixtureKey' => $key,
                ]),
                'filename' => $definition['filename'],
                'description' => $definition['description'],
            ];
        }

        return $guides;
    }
}
