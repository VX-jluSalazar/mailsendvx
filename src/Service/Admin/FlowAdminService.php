<?php

namespace Velox\MailSendVx\Service\Admin;

use Context;
use InvalidArgumentException;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Repository\MailSendVxFlowRepository;
use Velox\MailSendVx\Repository\MailSendVxLogRepository;
use Velox\MailSendVx\Repository\MailSendVxQueueRepository;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;
use Velox\MailSendVx\Service\Event\OrderStateEventService;
use Velox\MailSendVx\Service\Flow\FlowWorkerService;

class FlowAdminService
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var MailSendVxFlowRepository
     */
    private $flowRepository;

    /**
     * @var MailSendVxQueueRepository
     */
    private $queueRepository;

    /**
     * @var MailSendVxLogRepository
     */
    private $logRepository;

    /**
     * @var MailSendVxTemplateRepository
     */
    private $templateRepository;

    /**
     * @var OrderStateEventService
     */
    private $orderStateEventService;

    /**
     * @var FlowWorkerService
     */
    private $flowWorker;

    public function __construct(
        Context $context,
        MailSendVxFlowRepository $flowRepository,
        MailSendVxQueueRepository $queueRepository,
        MailSendVxLogRepository $logRepository,
        MailSendVxTemplateRepository $templateRepository,
        OrderStateEventService $orderStateEventService,
        FlowWorkerService $flowWorker
    ) {
        $this->context = $context;
        $this->flowRepository = $flowRepository;
        $this->queueRepository = $queueRepository;
        $this->logRepository = $logRepository;
        $this->templateRepository = $templateRepository;
        $this->orderStateEventService = $orderStateEventService;
        $this->flowWorker = $flowWorker;
    }

    /**
     * @return array<string, string>
     */
    public function getSupportedEvents(): array
    {
        return array_merge(
            [
                ModuleConstants::EVENT_ORDER_CREATED => 'Pedido creado',
                ModuleConstants::EVENT_ORDER_STATUS_CHANGED => 'Cambio de estado de pedido',
                ModuleConstants::EVENT_CUSTOMER_REGISTERED => 'Registro de cliente',
                ModuleConstants::EVENT_NEWSLETTER_REGISTERED => 'Suscripcion newsletter',
                ModuleConstants::EVENT_CART_ABANDONED => 'Carrito abandonado',
            ],
            $this->orderStateEventService->getSupportedEvents([
                'generic' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED,
            ])
        );
    }

    /**
     * @return array<string, string>
     */
    public function getSupportedContextTypes(): array
    {
        return [
            ModuleConstants::CONTEXT_ORDER => 'Pedido',
            ModuleConstants::CONTEXT_CART => 'Carrito',
            ModuleConstants::CONTEXT_CUSTOMER => 'Cliente',
            ModuleConstants::CONTEXT_NEWSLETTER => 'Newsletter',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTemplatesForBuilder(): array
    {
        $templates = $this->templateRepository->getAll(500);
        $eventLabels = $this->getSupportedEvents();
        $contextLabels = $this->getSupportedContextTypes();

        foreach ($templates as &$template) {
            $eventName = trim((string) ($template['event_name'] ?? ''));
            $contextType = (string) ($template['context_type'] ?? '');
            $template['usage'] = $eventName === '' ? 'reusable' : 'instant';
            $template['usage_label'] = $eventName === '' ? 'Reusable en flows' : 'Instantánea';
            $template['event_label'] = $eventName !== '' ? ($eventLabels[$eventName] ?? $eventName) : 'Sin evento';
            $template['context_label'] = $contextLabels[$contextType] ?? $contextType;
            $template['active'] = !empty($template['active']);
        }
        unset($template);

        usort($templates, static function (array $left, array $right): int {
            if ($left['active'] !== $right['active']) {
                return $left['active'] ? -1 : 1;
            }

            if ($left['usage'] !== $right['usage']) {
                return $left['usage'] === 'reusable' ? -1 : 1;
            }

            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return $templates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFlowsForView(): array
    {
        $flows = $this->flowRepository->getAll(200);
        $eventLabels = $this->getSupportedEvents();
        $contextLabels = $this->getSupportedContextTypes();
        $templateIndex = $this->indexTemplatesById($this->getTemplatesForBuilder());

        foreach ($flows as &$flow) {
            $flow['context_label'] = $contextLabels[(string) ($flow['context_type'] ?? '')] ?? (string) ($flow['context_type'] ?? '');
            $flow['trigger_label'] = $eventLabels[(string) ($flow['trigger_event'] ?? '')] ?? (string) ($flow['trigger_event'] ?? '');
            $flow['steps_count'] = count((array) ($flow['steps_json'] ?? []));
            $flow['active'] = !empty($flow['active']);
            $flow['step_cards'] = [];

            foreach ((array) ($flow['steps_json'] ?? []) as $index => $step) {
                if (!is_array($step)) {
                    continue;
                }

                $templateId = (int) ($step['template_id'] ?? 0);
                $template = $templateIndex[$templateId] ?? null;
                $delay = is_array($step['delay'] ?? null) ? $step['delay'] : [];
                $flow['step_cards'][] = [
                    'position' => $index + 1,
                    'id' => (string) ($step['id'] ?? ''),
                    'active' => !isset($step['active']) || (bool) $step['active'],
                    'template_name' => $template['name'] ?? ('Template #' . $templateId),
                    'template_usage' => $template['usage_label'] ?? 'Template',
                    'conditions_count' => is_array($step['conditions'] ?? null) ? count($step['conditions']) : 0,
                    'cancel_rules_count' => is_array($step['cancel_rules'] ?? null) ? count($step['cancel_rules']) : 0,
                    'delay_label' => $this->formatDelayLabel($delay),
                ];
            }
        }
        unset($flow);

        return $flows;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFlowFormData(?int $idFlow = null): array
    {
        $flow = $idFlow ? $this->flowRepository->findById($idFlow) : false;

        return [
            'id_mailsendvx_flow' => $flow ? (int) ($flow['id_mailsendvx_flow'] ?? 0) : 0,
            'name' => $flow ? (string) ($flow['name'] ?? '') : '',
            'trigger_event' => $flow ? (string) ($flow['trigger_event'] ?? ModuleConstants::EVENT_CART_ABANDONED) : ModuleConstants::EVENT_CART_ABANDONED,
            'context_type' => $flow ? (string) ($flow['context_type'] ?? ModuleConstants::CONTEXT_CART) : ModuleConstants::CONTEXT_CART,
            'description' => $flow ? (string) ($flow['description'] ?? '') : '',
            'priority' => $flow ? (int) ($flow['priority'] ?? 0) : 0,
            'version' => $flow ? (int) ($flow['version'] ?? 1) : 1,
            'active' => $flow ? !empty($flow['active']) : true,
            'conditions_json' => $flow && is_array($flow['conditions_json'] ?? null) ? $flow['conditions_json'] : [],
            'steps_json' => $flow && is_array($flow['steps_json'] ?? null) ? $flow['steps_json'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveFlow(array $payload): bool
    {
        $steps = $this->normalizeStepsInput($payload['steps_json'] ?? []);
        $conditions = $this->normalizeConditionsInput($payload['conditions_json'] ?? []);
        $idFlow = !empty($payload['id_mailsendvx_flow']) ? (int) $payload['id_mailsendvx_flow'] : null;
        $version = max(1, (int) ($payload['version'] ?? 1));

        if ($idFlow) {
            $existing = $this->flowRepository->findById($idFlow);
            if ($existing) {
                $existingSteps = (array) ($existing['steps_json'] ?? []);
                $existingConditions = (array) ($existing['conditions_json'] ?? []);
                if ($existingSteps !== $steps || $existingConditions !== $conditions) {
                    $version = max((int) ($existing['version'] ?? 1) + 1, $version + 1);
                }
            }
        }

        return $this->flowRepository->save([
            'id_shop' => (int) $this->context->shop->id,
            'name' => trim((string) ($payload['name'] ?? '')),
            'trigger_event' => trim((string) ($payload['trigger_event'] ?? '')),
            'context_type' => trim((string) ($payload['context_type'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'priority' => (int) ($payload['priority'] ?? 0),
            'conditions_json' => $conditions,
            'steps_json' => $steps,
            'version' => $version,
            'active' => !empty($payload['active']),
        ], $idFlow);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOperationsViewData(): array
    {
        $recentQueue = $this->queueRepository->getRecent(40);
        $recentLogs = $this->logRepository->getRecent(20);
        $flows = $this->getFlowsForView();
        $templates = $this->getTemplatesForBuilder();
        $activeFlowsCount = 0;
        $reusableTemplatesCount = 0;

        foreach ($flows as $flow) {
            if (!empty($flow['active'])) {
                ++$activeFlowsCount;
            }
        }

        foreach ($templates as $template) {
            if (($template['usage'] ?? '') === 'reusable') {
                ++$reusableTemplatesCount;
            }
        }

        foreach ($recentQueue as &$job) {
            $payload = $this->decodePayload($job['payload_json'] ?? $job['payload'] ?? null);
            $job['payload_pretty'] = !empty($payload)
                ? (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : '{}';
            $job['can_cancel'] = in_array((string) ($job['status'] ?? ''), ['pending', 'scheduled'], true);
        }
        unset($job);

        foreach ($recentLogs as &$log) {
            $payload = $this->decodePayload($log['payload'] ?? null);
            $log['payload_pretty'] = !empty($payload)
                ? (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : '{}';
        }
        unset($log);

        return [
            'flows' => $flows,
            'active_flows_count' => $activeFlowsCount,
            'templates_for_builder' => $templates,
            'reusable_templates_count' => $reusableTemplatesCount,
            'queue_jobs' => $recentQueue,
            'recent_logs' => $recentLogs,
            'supported_events' => $this->getSupportedEvents(),
            'supported_context_types' => $this->getSupportedContextTypes(),
            'event_context_map' => $this->buildEventContextMap(),
            'queue_counts' => [
                'pending' => $this->queueRepository->countByStatus('pending'),
                'scheduled' => $this->queueRepository->countByStatus('scheduled'),
                'processing' => $this->queueRepository->countByStatus('processing'),
                'sent' => $this->queueRepository->countByStatus('sent'),
                'failed' => $this->queueRepository->countByStatus('failed'),
                'cancelled' => $this->queueRepository->countByStatus('cancelled'),
                'skipped' => $this->queueRepository->countByStatus('skipped'),
            ],
            'preset_catalog' => $this->getPresetCatalog(),
        ];
    }

    public function cancelQueueJob(int $idQueue, string $reason = 'Cancelled manually from Back Office.'): bool
    {
        $job = $this->queueRepository->findById($idQueue);
        if (!$job) {
            throw new InvalidArgumentException('No se encontró el job indicado.');
        }

        $status = (string) ($job['status'] ?? '');
        if (!in_array($status, ['pending', 'scheduled'], true)) {
            throw new InvalidArgumentException('Solo se pueden cancelar jobs pendientes o programados.');
        }

        if (!$this->queueRepository->cancelPendingJob($idQueue, $reason)) {
            return false;
        }

        $this->logRepository->add(
            (string) ($job['event_name'] ?? ''),
            'cancelled',
            isset($job['recipient']) ? (string) $job['recipient'] : null,
            !empty($job['id_template']) ? (int) $job['id_template'] : null,
            $idQueue,
            $this->decodePayload($job['payload_json'] ?? $job['payload'] ?? null) ?: [],
            $reason,
            !empty($job['id_shop']) ? (int) $job['id_shop'] : null
        );

        return true;
    }

    public function createPresetFlow(string $presetId): bool
    {
        $preset = $this->buildPresetDefinition($presetId);

        return $this->saveFlow($preset);
    }

    /**
     * @return array<string, mixed>
     */
    public function runQueueWorker(int $limit = 50): array
    {
        return $this->flowWorker->processDueJobs($limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPresetCatalog(): array
    {
        return [
            [
                'id' => 'cart_recovery_triptych',
                'title' => 'Carrito abandonado en 3 tiempos',
                'eyebrow' => 'Recuperación',
                'description' => 'Tres contactos escalonados para recuperar carritos con recordatorio, beneficio y último aviso.',
                'context_type' => ModuleConstants::CONTEXT_CART,
                'trigger_event' => ModuleConstants::EVENT_CART_ABANDONED,
                'step_count' => 3,
            ],
            [
                'id' => 'order_created_confirmation',
                'title' => 'Postcompra: confirmación',
                'eyebrow' => 'Postcompra',
                'description' => 'Confirmación inmediata al crear pedido para dejar cubierta la primera tranquilidad del comprador.',
                'context_type' => ModuleConstants::CONTEXT_ORDER,
                'trigger_event' => ModuleConstants::EVENT_ORDER_CREATED,
                'step_count' => 1,
            ],
            [
                'id' => 'payment_accepted_thanks',
                'title' => 'Postcompra: pago aceptado',
                'eyebrow' => 'Postcompra',
                'description' => 'Agradecimiento y siguiente paso cuando el pedido cambia a pago aceptado.',
                'context_type' => ModuleConstants::CONTEXT_ORDER,
                'trigger_event' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED . '_payment_accepted',
                'step_count' => 1,
            ],
            [
                'id' => 'shipped_followup',
                'title' => 'Postcompra: enviado',
                'eyebrow' => 'Postcompra',
                'description' => 'Mensaje de acompañamiento al despacho con timing operativo de logística.',
                'context_type' => ModuleConstants::CONTEXT_ORDER,
                'trigger_event' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED . '_shipped',
                'step_count' => 1,
            ],
            [
                'id' => 'delivered_review',
                'title' => 'Postcompra: entregado + review',
                'eyebrow' => 'Postcompra',
                'description' => 'Solicitud de reseña luego de la entrega con una espera corta para no interrumpir la recepción.',
                'context_type' => ModuleConstants::CONTEXT_ORDER,
                'trigger_event' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED . '_delivered',
                'step_count' => 1,
            ],
            [
                'id' => 'newsletter_nurture',
                'title' => 'Suscriptores: bienvenida y nurture',
                'eyebrow' => 'Captación',
                'description' => 'Serie inicial con bienvenida inmediata, educación breve y un incentivo opcional más tarde.',
                'context_type' => ModuleConstants::CONTEXT_NEWSLETTER,
                'trigger_event' => ModuleConstants::EVENT_NEWSLETTER_REGISTERED,
                'step_count' => 3,
            ],
        ];
    }

    /**
     * @param mixed $input
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStepsInput($input): array
    {
        if (is_string($input) && trim($input) !== '') {
            $decoded = json_decode($input, true);
            $input = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($input)) {
            throw new InvalidArgumentException('Los steps del flow deben ser una lista válida.');
        }

        $steps = [];
        foreach (array_values($input) as $index => $step) {
            if (!is_array($step)) {
                continue;
            }

            $templateId = (int) ($step['template_id'] ?? 0);
            if ($templateId <= 0) {
                throw new InvalidArgumentException(sprintf('El step #%d requiere una plantilla válida.', $index + 1));
            }

            $steps[] = [
                'id' => trim((string) ($step['id'] ?? ('step_' . ($index + 1)))),
                'type' => trim((string) ($step['type'] ?? 'email')),
                'template_id' => $templateId,
                'delay' => [
                    'value' => max(0, (int) (($step['delay']['value'] ?? $step['delay_value'] ?? 0))),
                    'unit' => trim((string) (($step['delay']['unit'] ?? $step['delay_unit'] ?? 'hour'))),
                    'mode' => trim((string) (($step['delay']['mode'] ?? $step['delay_mode'] ?? 'after_trigger'))),
                ],
                'conditions' => $this->normalizeConditionsInput($step['conditions'] ?? []),
                'cancel_rules' => $this->normalizeConditionsInput($step['cancel_rules'] ?? []),
                'active' => !isset($step['active']) || (bool) $step['active'],
            ];
        }

        if (empty($steps)) {
            throw new InvalidArgumentException('Debes agregar al menos un step para guardar el flow.');
        }

        return $steps;
    }

    /**
     * @param mixed $input
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeConditionsInput($input): array
    {
        if (is_string($input)) {
            $input = trim($input);
            if ($input === '') {
                return [];
            }

            $decoded = json_decode($input, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Las condiciones deben estar en JSON válido.');
            }

            $input = $decoded;
        }

        if (!is_array($input)) {
            return [];
        }

        return array_values(array_filter($input, static function ($condition): bool {
            return is_array($condition);
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     *
     * @return array<int, array<string, mixed>>
     */
    private function indexTemplatesById(array $templates): array
    {
        $indexed = [];
        foreach ($templates as $template) {
            $idTemplate = (int) ($template['id_mailsendvx_template'] ?? 0);
            if ($idTemplate > 0) {
                $indexed[$idTemplate] = $template;
            }
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $delay
     */
    private function formatDelayLabel(array $delay): string
    {
        $value = max(0, (int) ($delay['value'] ?? 0));
        $unit = (string) ($delay['unit'] ?? 'hour');
        $mode = (string) ($delay['mode'] ?? 'after_trigger');

        if ($mode === 'immediate' || $value === 0) {
            return 'Inmediato';
        }

        $base = $mode === 'after_previous_step' ? 'Después del step anterior' : 'Después del trigger';

        return sprintf('%s · %d %s', $base, $value, $unit);
    }

    /**
     * @param mixed $payload
     *
     * @return array<string, mixed>|null
     */
    private function decodePayload($payload): ?array
    {
        if (!is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPresetDefinition(string $presetId): array
    {
        switch ($presetId) {
            case 'cart_recovery_triptych':
                return [
                    'name' => 'Carrito abandonado · Recuperación en 3 tiempos',
                    'trigger_event' => ModuleConstants::EVENT_CART_ABANDONED,
                    'context_type' => ModuleConstants::CONTEXT_CART,
                    'description' => 'Recupera carritos con una secuencia de tres correos y cancelación automática al convertirse en pedido.',
                    'priority' => 90,
                    'active' => true,
                    'conditions_json' => [],
                    'steps_json' => $this->buildPresetSteps(ModuleConstants::CONTEXT_CART, [
                        ['delay_value' => 0, 'delay_unit' => 'hour', 'delay_mode' => 'immediate'],
                        ['delay_value' => 24, 'delay_unit' => 'hour', 'delay_mode' => 'after_previous_step'],
                        ['delay_value' => 72, 'delay_unit' => 'hour', 'delay_mode' => 'after_previous_step'],
                    ], [
                        [
                            'path' => 'cart.recovered_at',
                            'operator' => 'exists',
                        ],
                    ]),
                ];
            case 'order_created_confirmation':
                return [
                    'name' => 'Postcompra · Confirmación inmediata',
                    'trigger_event' => ModuleConstants::EVENT_ORDER_CREATED,
                    'context_type' => ModuleConstants::CONTEXT_ORDER,
                    'description' => 'Confirmación inmediata luego de crear el pedido.',
                    'priority' => 70,
                    'active' => true,
                    'conditions_json' => [],
                    'steps_json' => $this->buildPresetSteps(ModuleConstants::CONTEXT_ORDER, [
                        ['delay_value' => 0, 'delay_unit' => 'hour', 'delay_mode' => 'immediate'],
                    ]),
                ];
            case 'payment_accepted_thanks':
                return [
                    'name' => 'Postcompra · Pago aceptado',
                    'trigger_event' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED . '_payment_accepted',
                    'context_type' => ModuleConstants::CONTEXT_ORDER,
                    'description' => 'Agradecimiento cuando el pedido pasa a pago aceptado.',
                    'priority' => 65,
                    'active' => true,
                    'conditions_json' => [],
                    'steps_json' => $this->buildPresetSteps(ModuleConstants::CONTEXT_ORDER, [
                        ['delay_value' => 0, 'delay_unit' => 'hour', 'delay_mode' => 'immediate'],
                    ]),
                ];
            case 'shipped_followup':
                return [
                    'name' => 'Postcompra · Seguimiento de envío',
                    'trigger_event' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED . '_shipped',
                    'context_type' => ModuleConstants::CONTEXT_ORDER,
                    'description' => 'Mensaje operativo al despachar el pedido.',
                    'priority' => 60,
                    'active' => true,
                    'conditions_json' => [],
                    'steps_json' => $this->buildPresetSteps(ModuleConstants::CONTEXT_ORDER, [
                        ['delay_value' => 2, 'delay_unit' => 'hour', 'delay_mode' => 'after_trigger'],
                    ]),
                ];
            case 'delivered_review':
                return [
                    'name' => 'Postcompra · Reseña tras entrega',
                    'trigger_event' => ModuleConstants::EVENT_ORDER_STATUS_CHANGED . '_delivered',
                    'context_type' => ModuleConstants::CONTEXT_ORDER,
                    'description' => 'Solicitud de review después de la entrega.',
                    'priority' => 55,
                    'active' => true,
                    'conditions_json' => [],
                    'steps_json' => $this->buildPresetSteps(ModuleConstants::CONTEXT_ORDER, [
                        ['delay_value' => 3, 'delay_unit' => 'day', 'delay_mode' => 'after_trigger'],
                    ]),
                ];
            case 'newsletter_nurture':
                return [
                    'name' => 'Newsletter · Bienvenida y nurture',
                    'trigger_event' => ModuleConstants::EVENT_NEWSLETTER_REGISTERED,
                    'context_type' => ModuleConstants::CONTEXT_NEWSLETTER,
                    'description' => 'Serie de bienvenida con educación progresiva para suscriptores.',
                    'priority' => 75,
                    'active' => true,
                    'conditions_json' => [],
                    'steps_json' => $this->buildPresetSteps(ModuleConstants::CONTEXT_NEWSLETTER, [
                        ['delay_value' => 0, 'delay_unit' => 'hour', 'delay_mode' => 'immediate'],
                        ['delay_value' => 2, 'delay_unit' => 'day', 'delay_mode' => 'after_previous_step'],
                        ['delay_value' => 5, 'delay_unit' => 'day', 'delay_mode' => 'after_previous_step'],
                    ]),
                ];
            default:
                throw new InvalidArgumentException('Preset no soportado.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildEventContextMap(): array
    {
        $map = [];
        foreach (array_keys($this->getSupportedEvents()) as $eventName) {
            $contextType = ModuleConstants::getEventContextType((string) $eventName);
            if ($contextType !== null) {
                $map[(string) $eventName] = $contextType;
            }
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $delays
     * @param array<int, array<string, mixed>> $cancelRules
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPresetSteps(string $contextType, array $delays, array $cancelRules = []): array
    {
        $templates = $this->pickTemplatesForPreset($contextType, count($delays));
        $steps = [];

        foreach ($delays as $index => $delay) {
            $template = $templates[$index] ?? null;
            if (!is_array($template)) {
                throw new InvalidArgumentException('No hay suficientes plantillas compatibles para crear este preset.');
            }

            $steps[] = [
                'id' => 'step_' . ($index + 1),
                'type' => 'email',
                'template_id' => (int) $template['id_mailsendvx_template'],
                'delay' => [
                    'value' => max(0, (int) ($delay['delay_value'] ?? 0)),
                    'unit' => (string) ($delay['delay_unit'] ?? 'hour'),
                    'mode' => (string) ($delay['delay_mode'] ?? 'after_trigger'),
                ],
                'conditions' => [],
                'cancel_rules' => $cancelRules,
                'active' => true,
            ];
        }

        return $steps;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pickTemplatesForPreset(string $contextType, int $needed): array
    {
        $pool = [];
        $fallback = [];

        foreach ($this->getTemplatesForBuilder() as $template) {
            if ((string) ($template['context_type'] ?? '') !== $contextType || empty($template['active'])) {
                continue;
            }

            if ((string) ($template['usage'] ?? '') === 'reusable') {
                $pool[] = $template;
                continue;
            }

            $fallback[] = $template;
        }

        $selected = array_slice(array_merge($pool, $fallback), 0, $needed);
        if (count($selected) < $needed && !empty($selected)) {
            while (count($selected) < $needed) {
                $selected[] = $selected[count($selected) % max(1, count($selected))];
            }
        }

        return $selected;
    }
}
