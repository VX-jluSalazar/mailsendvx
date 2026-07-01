<?php

namespace Velox\MailSendVx\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Service\Template\TemplateAdminService;

class DocumentationController extends FrameworkBundleAdminController
{
    /**
     * @var TemplateAdminService
     */
    private $templateAdminService;

    public function __construct(TemplateAdminService $templateAdminService)
    {
        parent::__construct();
        $this->templateAdminService = $templateAdminService;
    }

    public function indexAction(Request $request): Response
    {
        $contextBuilderSections = $this->buildContextBuilderSections();

        return $this->render('@Modules/mailsendvx/views/templates/admin/documentation.html.twig', [
            'shopName' => (string) $this->getContext()->shop->name,
            'contextBuilderSections' => $contextBuilderSections,
            'abandonedCartSettingsGuide' => $this->getAbandonedCartSettingsGuide(),
        ]);
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
                'builder' => 'OrderTemplateContextBuilder',
                'description' => 'Variables creadas por OrderTemplateContextBuilder para pedidos, cambios de estado y estados dinamicos del pedido.',
                'matches' => static function (string $eventName): bool {
                    return $eventName === ModuleConstants::EVENT_ORDER_CREATED
                        || $eventName === ModuleConstants::EVENT_ORDER_STATUS_CHANGED
                        || $eventName === ModuleConstants::EVENT_ORDER_STATUS_LEGACY
                        || strpos($eventName, ModuleConstants::EVENT_ORDER_STATUS_CHANGED . '_') === 0;
                },
            ],
            [
                'title' => 'Variables de Cliente',
                'builder' => 'CustomerTemplateContextBuilder',
                'description' => 'Variables creadas por CustomerTemplateContextBuilder para el registro de cliente.',
                'matches' => static function (string $eventName): bool {
                    return $eventName === ModuleConstants::EVENT_CUSTOMER_REGISTERED;
                },
            ],
            [
                'title' => 'Variables de Newsletter',
                'builder' => 'NewsletterTemplateContextBuilder',
                'description' => 'Variables creadas por NewsletterTemplateContextBuilder para eventos de suscripcion newsletter.',
                'matches' => static function (string $eventName): bool {
                    return $eventName === ModuleConstants::EVENT_NEWSLETTER_REGISTERED;
                },
            ],
            [
                'title' => 'Variables de Carrito',
                'builder' => 'CartTemplateContextBuilder',
                'description' => 'Variables creadas por CartTemplateContextBuilder para carrito abandonado y enlaces de recuperacion.',
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

            if (in_array($sectionConfig['builder'], ['OrderTemplateContextBuilder', 'CustomerTemplateContextBuilder', 'NewsletterTemplateContextBuilder', 'CartTemplateContextBuilder'], true)) {
                $scalarAttributes = array_filter($scalarAttributes, static function (array $item): bool {
                    $path = (string) ($item['path'] ?? '');

                    return strpos($path, '.') !== false;
                });
            }

            $sections[] = [
                'title' => $sectionConfig['title'],
                'builder' => $sectionConfig['builder'],
                'description' => $sectionConfig['description'],
                'events' => array_values($matchedEventNames),
                'scalar_attributes' => array_values($scalarAttributes),
                'collection_attributes' => array_values($collectionAttributes),
            ];
        }

        return $sections;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getAbandonedCartSettingsGuide(): array
    {
        return [
            [
                'key' => 'Enable abandoned cart detection',
                'description' => 'Activa o desactiva por completo la deteccion de carritos abandonados. Si esta en No, el cron no captura eventos `cart_abandoned`.',
            ],
            [
                'key' => 'Abandoned cart delay value',
                'description' => 'Es el numero base de tiempo que debe pasar sin actividad antes de considerar abandonado un carrito.',
            ],
            [
                'key' => 'Abandoned cart delay unit',
                'description' => 'Define la unidad del retraso: minutos, horas, dias o semanas. Junto con el valor anterior forma la ventana de abandono.',
            ],
            [
                'key' => 'Require customer email',
                'description' => 'Si esta en Si, solo se evaluan carritos con email resoluble. Es lo recomendado cuando quieres enviar correo inmediatamente.',
            ],
            [
                'key' => 'Require products in cart',
                'description' => 'Si esta en Si, excluye carritos vacios. Es la configuracion normal para evitar ruido y falsos positivos.',
            ],
            [
                'key' => 'Abandoned cart batch size',
                'description' => 'Limita cuantos carritos procesa el cron en cada corrida. Sirve para controlar carga y tiempos de ejecucion.',
            ],
        ];
    }
}
