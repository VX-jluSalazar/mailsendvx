<?php

namespace Velox\MailSendVx\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use Velox\MailSendVx\ModuleConstants;

class MailSendVxFlowRepository extends AbstractMailSendVxRepository
{
    /**
     * @var MailSendVxTemplateRepository
     */
    private $templateRepository;

    public function __construct(Connection $connection, string $databasePrefix, MailSendVxTemplateRepository $templateRepository)
    {
        parent::__construct($connection, $databasePrefix);
        $this->templateRepository = $templateRepository;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(int $limit = 100): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_flow'))
            ->orderBy('priority', 'DESC')
            ->addOrderBy('date_upd', 'DESC')
            ->setMaxResults(max(1, min(500, $limit)));

        return $this->hydrateFlows($queryBuilder->execute()->fetchAllAssociative());
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findById(int $idFlow)
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_flow'))
            ->where('id_mailsendvx_flow = :idFlow')
            ->setParameter('idFlow', $idFlow, ParameterType::INTEGER);

        $result = $queryBuilder->execute()->fetchAssociative();

        if (!$result) {
            return false;
        }

        $flows = $this->hydrateFlows([$result]);

        return $flows[0] ?? false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveByTriggerEvent(string $triggerEvent, ?int $idShop = null): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->getTableName('mailsendvx_flow'))
            ->where('trigger_event = :triggerEvent')
            ->andWhere('active = :active')
            ->orderBy('priority', 'DESC')
            ->addOrderBy('id_mailsendvx_flow', 'ASC')
            ->setParameter('triggerEvent', $triggerEvent)
            ->setParameter('active', 1, ParameterType::INTEGER);

        if ($idShop !== null) {
            $queryBuilder
                ->andWhere('id_shop IN (:allShops, :idShop)')
                ->setParameter('allShops', 0, ParameterType::INTEGER)
                ->setParameter('idShop', $idShop, ParameterType::INTEGER);
        }

        return $this->hydrateFlows($queryBuilder->execute()->fetchAllAssociative());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data, ?int $idFlow = null): bool
    {
        $contextType = trim((string) ($data['context_type'] ?? ''));
        $triggerEvent = trim((string) ($data['trigger_event'] ?? ''));
        $conditions = $this->normalizeConditions($data['conditions_json'] ?? []);
        $steps = $this->normalizeSteps($data['steps_json'] ?? [], $contextType);

        if ($contextType === '' || !ModuleConstants::isSupportedContextType($contextType)) {
            throw new InvalidArgumentException('Flow context_type is required and must be supported.');
        }

        if ($triggerEvent === '') {
            throw new InvalidArgumentException('Flow trigger_event is required.');
        }

        $eventContextType = ModuleConstants::getEventContextType($triggerEvent);
        if ($eventContextType !== null && $eventContextType !== $contextType) {
            throw new InvalidArgumentException('Flow trigger_event is not compatible with the selected context_type.');
        }

        $now = date('Y-m-d H:i:s');
        $row = [
            'id_shop' => (int) ($data['id_shop'] ?? $this->getCurrentShopId()),
            'name' => trim((string) ($data['name'] ?? '')),
            'trigger_event' => $triggerEvent,
            'context_type' => $contextType,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'active' => (int) !empty($data['active']),
            'priority' => (int) ($data['priority'] ?? 0),
            'conditions_json' => json_encode($conditions),
            'steps_json' => json_encode($steps),
            'version' => max(1, (int) ($data['version'] ?? 1)),
            'date_upd' => $now,
        ];

        if ($row['name'] === '') {
            throw new InvalidArgumentException('Flow name is required.');
        }

        if ($idFlow) {
            $this->connection->update(
                $this->getTableName('mailsendvx_flow'),
                $row,
                ['id_mailsendvx_flow' => $idFlow]
            );

            return true;
        }

        $row['date_add'] = $now;
        $this->connection->insert($this->getTableName('mailsendvx_flow'), $row);

        return true;
    }

    /**
     * @param mixed $conditions
     *
     * @return array<int, mixed>
     */
    private function normalizeConditions($conditions): array
    {
        if (is_string($conditions) && $conditions !== '') {
            $decoded = json_decode($conditions, true);
            $conditions = is_array($decoded) ? $decoded : [];
        }

        return is_array($conditions) ? array_values($conditions) : [];
    }

    /**
     * @param mixed $steps
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSteps($steps, string $contextType): array
    {
        if (is_string($steps) && $steps !== '') {
            $decoded = json_decode($steps, true);
            $steps = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($steps)) {
            throw new InvalidArgumentException('Flow steps_json must be a valid list.');
        }

        $normalized = [];
        foreach (array_values($steps) as $index => $step) {
            if (!is_array($step)) {
                throw new InvalidArgumentException(sprintf('Flow step #%d is invalid.', $index + 1));
            }

            $templateId = (int) ($step['template_id'] ?? 0);
            if ($templateId <= 0) {
                throw new InvalidArgumentException(sprintf('Flow step #%d requires a valid template_id.', $index + 1));
            }

            $template = $this->templateRepository->findById($templateId);
            if (!$template) {
                throw new InvalidArgumentException(sprintf('Flow step #%d references an unknown template.', $index + 1));
            }

            if ((string) ($template['context_type'] ?? '') !== $contextType) {
                throw new InvalidArgumentException(sprintf('Flow step #%d references a template with incompatible context_type.', $index + 1));
            }

            $delay = $step['delay'] ?? [];
            if (!is_array($delay)) {
                throw new InvalidArgumentException(sprintf('Flow step #%d has an invalid delay definition.', $index + 1));
            }

            $normalized[] = [
                'id' => trim((string) ($step['id'] ?? ('step_' . ($index + 1)))),
                'type' => trim((string) ($step['type'] ?? 'email')),
                'template_id' => $templateId,
                'delay' => [
                    'value' => max(0, (int) ($delay['value'] ?? 0)),
                    'unit' => trim((string) ($delay['unit'] ?? 'hour')),
                    'mode' => trim((string) ($delay['mode'] ?? 'after_trigger')),
                ],
                'conditions' => $this->normalizeConditions($step['conditions'] ?? []),
                'cancel_rules' => $this->normalizeConditions($step['cancel_rules'] ?? []),
                'active' => !isset($step['active']) || (bool) $step['active'],
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    private function hydrateFlows(array $rows): array
    {
        foreach ($rows as &$row) {
            $conditions = json_decode((string) ($row['conditions_json'] ?? '[]'), true);
            $steps = json_decode((string) ($row['steps_json'] ?? '[]'), true);

            $row['conditions_json'] = is_array($conditions) ? $conditions : [];
            $row['steps_json'] = is_array($steps) ? $steps : [];
            $row['version'] = max(1, (int) ($row['version'] ?? 1));
            $row['priority'] = (int) ($row['priority'] ?? 0);
            $row['active'] = !empty($row['active']);
        }
        unset($row);

        return $rows;
    }
}
