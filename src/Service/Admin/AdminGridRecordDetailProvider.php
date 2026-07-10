<?php

namespace Velox\MailSendVx\Service\Admin;

use InvalidArgumentException;
use Velox\MailSendVx\Repository\MailSendVxEventRepository;
use Velox\MailSendVx\Repository\MailSendVxLogRepository;
use Velox\MailSendVx\Repository\MailSendVxQueueRepository;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;

class AdminGridRecordDetailProvider
{
    /**
     * @var MailSendVxEventRepository
     */
    private $eventRepository;

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

    public function __construct(
        MailSendVxEventRepository $eventRepository,
        MailSendVxQueueRepository $queueRepository,
        MailSendVxLogRepository $logRepository,
        MailSendVxTemplateRepository $templateRepository
    ) {
        $this->eventRepository = $eventRepository;
        $this->queueRepository = $queueRepository;
        $this->logRepository = $logRepository;
        $this->templateRepository = $templateRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboardRecordDetail(string $gridId, int $recordId): array
    {
        if ($gridId === 'mailsendvx_events') {
            $record = $this->eventRepository->findById($recordId);
            if (!$record) {
                throw new InvalidArgumentException('No se encontró el evento indicado.');
            }

            return [
                'eyebrow' => 'Evento capturado',
                'title' => (string) ($record['event_name'] ?? ('Evento #' . $recordId)),
                'summary' => 'Detalle completo del evento capturado por el módulo.',
                'meta' => [
                    ['label' => 'ID', 'value' => (string) ($record['id_mailsendvx_event'] ?? '')],
                    ['label' => 'Estado', 'value' => (string) ($record['status'] ?? '')],
                    ['label' => 'Objeto', 'value' => trim((string) (($record['object_type'] ?? '') . ' #' . ($record['object_id'] ?? '')), ' #')],
                    ['label' => 'Shop', 'value' => (string) ($record['id_shop'] ?? '')],
                    ['label' => 'Fecha', 'value' => (string) ($record['date_add'] ?? '')],
                ],
                'blocks' => [
                    [
                        'label' => 'Payload',
                        'content' => $this->formatJsonBlock($record['payload'] ?? null),
                        'code' => true,
                    ],
                ],
            ];
        }

        if ($gridId === 'mailsendvx_queue') {
            $record = $this->queueRepository->findById($recordId);
            if (!$record) {
                throw new InvalidArgumentException('No se encontró el job indicado.');
            }

            return [
                'eyebrow' => 'Job en queue',
                'title' => (string) ($record['recipient'] ?? ('Job #' . $recordId)),
                'summary' => 'Estado operativo y contexto persistido del job programado.',
                'meta' => [
                    ['label' => 'ID', 'value' => (string) ($record['id_mailsendvx_queue'] ?? '')],
                    ['label' => 'Estado', 'value' => (string) ($record['status'] ?? '')],
                    ['label' => 'Evento', 'value' => (string) ($record['event_name'] ?? '')],
                    ['label' => 'Flow', 'value' => !empty($record['id_flow']) ? ('#' . $record['id_flow']) : '-'],
                    ['label' => 'Step', 'value' => (string) ($record['step_id'] ?? '-')],
                    ['label' => 'Template', 'value' => !empty($record['id_template']) ? ('#' . $record['id_template']) : '-'],
                    ['label' => 'Programado', 'value' => (string) ($record['scheduled_at'] ?? '')],
                    ['label' => 'Intentos', 'value' => sprintf('%d / %d', (int) ($record['attempts'] ?? 0), (int) ($record['max_attempts'] ?? 0))],
                ],
                'blocks' => array_values(array_filter([
                    [
                        'label' => 'Payload',
                        'content' => $this->formatJsonBlock($record['payload_json'] ?? $record['payload'] ?? null),
                        'code' => true,
                    ],
                    !empty($record['last_error']) ? [
                        'label' => 'Último error',
                        'content' => (string) $record['last_error'],
                        'code' => false,
                    ] : null,
                    !empty($record['cancel_reason']) ? [
                        'label' => 'Motivo de cancelación',
                        'content' => (string) $record['cancel_reason'],
                        'code' => false,
                    ] : null,
                ])),
            ];
        }

        if ($gridId === 'mailsendvx_logs') {
            $record = $this->logRepository->findById($recordId);
            if (!$record) {
                throw new InvalidArgumentException('No se encontró el log indicado.');
            }

            return [
                'eyebrow' => 'Log operativo',
                'title' => (string) ($record['event_name'] ?? ('Log #' . $recordId)),
                'summary' => 'Resultado persistido del intento de envío o procesamiento.',
                'meta' => [
                    ['label' => 'ID', 'value' => (string) ($record['id_mailsendvx_log'] ?? '')],
                    ['label' => 'Estado', 'value' => (string) ($record['status'] ?? '')],
                    ['label' => 'Destinatario', 'value' => (string) ($record['recipient'] ?? '-')],
                    ['label' => 'Template', 'value' => !empty($record['id_template']) ? ('#' . $record['id_template']) : '-'],
                    ['label' => 'Queue', 'value' => !empty($record['id_queue']) ? ('#' . $record['id_queue']) : '-'],
                    ['label' => 'Shop', 'value' => (string) ($record['id_shop'] ?? '')],
                    ['label' => 'Fecha', 'value' => (string) ($record['date_add'] ?? '')],
                ],
                'blocks' => [
                    [
                        'label' => 'Mensaje',
                        'content' => (string) ($record['message'] ?? 'Sin mensaje operativo.'),
                        'code' => false,
                    ],
                    [
                        'label' => 'Payload',
                        'content' => $this->formatJsonBlock($record['payload'] ?? null),
                        'code' => true,
                    ],
                ],
            ];
        }

        throw new InvalidArgumentException('La grid indicada no soporta detalle.');
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplateDetail(int $templateId): array
    {
        $record = $this->templateRepository->findById($templateId);
        if (!$record) {
            throw new InvalidArgumentException('No se encontró la plantilla indicada.');
        }

        $eventName = trim((string) ($record['event_name'] ?? ''));

        return [
            'eyebrow' => 'Plantilla',
            'title' => (string) ($record['name'] ?? ('Template #' . $templateId)),
            'summary' => 'Resumen del alcance, contenido y configuración actual de la plantilla.',
            'meta' => [
                ['label' => 'ID', 'value' => (string) ($record['id_mailsendvx_template'] ?? '')],
                ['label' => 'Contexto', 'value' => (string) ($record['context_type'] ?? '')],
                ['label' => 'Evento', 'value' => $eventName !== '' ? $eventName : 'Reusable en flows'],
                ['label' => 'Idioma', 'value' => (string) ($record['id_lang'] ?? '')],
                ['label' => 'Shop', 'value' => (string) ($record['id_shop'] ?? '')],
                ['label' => 'Estado', 'value' => !empty($record['active']) ? 'Activa' : 'Inactiva'],
                ['label' => 'Mail template', 'value' => (string) ($record['mail_template'] ?? '')],
                ['label' => 'Actualizada', 'value' => (string) ($record['date_upd'] ?? '')],
            ],
            'blocks' => [
                [
                    'label' => 'Subject',
                    'content' => (string) ($record['subject'] ?? ''),
                    'code' => false,
                ],
                [
                    'label' => 'HTML',
                    'content' => (string) ($record['html_content'] ?? ''),
                    'code' => true,
                ],
                [
                    'label' => 'Texto plano',
                    'content' => (string) ($record['text_content'] ?? ''),
                    'code' => true,
                ],
            ],
        ];
    }

    /**
     * @param mixed $value
     */
    private function formatJsonBlock($value): string
    {
        if ($value === null || $value === '') {
            return '{}';
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $decoded = json_decode((string) $value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }
}
