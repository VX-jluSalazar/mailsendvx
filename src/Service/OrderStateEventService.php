<?php

namespace Velox\MailSendVx\Service;

use Context;
use OrderState;
use Tools;
use Validate;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;

class OrderStateEventService
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @param mixed $status
     */
    public function resolveOrderStateKey($status, string $fallbackName, int $fallbackId): string
    {
        if ($status instanceof OrderState && Validate::isLoadedObject($status)) {
            $template = $this->extractOrderStateTemplateValue($status->template ?? null);
            if ($template !== '') {
                return $this->mapOrderStateTemplateToKey($template);
            }
        }

        $normalizedName = $this->normalizeEventKey($fallbackName);
        if ($normalizedName !== '') {
            return $normalizedName;
        }

        return $fallbackId > 0 ? 'state_' . $fallbackId : 'state_unknown';
    }

    /**
     * @return string[]
     */
    public function buildDispatchEventNames(array $variables, MailSendVxTemplateRepository $templateRepository, string $genericEvent, string $legacyEvent): array
    {
        $eventNames = [$genericEvent];
        $orderStateKey = $variables['order']['state']['key'] ?? null;
        if (is_string($orderStateKey) && $orderStateKey !== '') {
            $eventNames[] = $genericEvent . '_' . $orderStateKey;
        }

        $idLang = (int) ($variables['shop']['id_lang'] ?? $this->context->language->id);
        $idShop = (int) ($variables['shop']['id'] ?? $this->context->shop->id);
        if ($templateRepository->hasActiveByEvent($legacyEvent, $idLang, $idShop)) {
            $eventNames[] = $legacyEvent;
        }

        return array_values(array_unique($eventNames));
    }

    /**
     * @param array<string, string> $baseEvents
     *
     * @return array<string, string>
     */
    public function getSupportedEvents(array $baseEvents): array
    {
        $events = [];
        foreach (OrderState::getOrderStates((int) $this->context->language->id) as $state) {
            $stateId = isset($state['id_order_state']) ? (int) $state['id_order_state'] : 0;
            $stateName = isset($state['name']) ? (string) $state['name'] : '';
            $template = isset($state['template']) && is_string($state['template']) ? $state['template'] : '';
            $stateKey = $this->resolveOrderStateKey(
                $stateId > 0 ? new OrderState($stateId) : null,
                $stateName,
                $stateId
            );

            if ($stateKey === '') {
                $stateKey = $this->normalizeEventKey($template ?: $stateName);
            }

            if ($stateKey === '') {
                continue;
            }

            $events[$baseEvents['generic'] . '_' . $stateKey] = sprintf(
                'Cambio de estado: %s',
                $stateName !== '' ? $stateName : ('Estado #' . $stateId)
            );
        }

        ksort($events);

        return $events;
    }

    /**
     * @param mixed $template
     */
    private function extractOrderStateTemplateValue($template): string
    {
        if (is_string($template)) {
            return trim($template);
        }

        if (!is_array($template)) {
            return '';
        }

        $idLang = (int) $this->context->language->id;
        if (isset($template[$idLang]) && is_string($template[$idLang])) {
            return trim($template[$idLang]);
        }

        foreach ($template as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function mapOrderStateTemplateToKey(string $template): string
    {
        $normalizedTemplate = $this->normalizeEventKey($template);
        $map = [
            'payment' => 'payment_accepted',
            'cheque' => 'payment_accepted',
            'bankwire' => 'payment_accepted',
            'preparation' => 'preparation_in_progress',
            'in_transit' => 'shipped',
            'shipped' => 'shipped',
            'delivery' => 'delivered',
            'delivered' => 'delivered',
            'canceled' => 'canceled',
            'cancelled' => 'canceled',
            'refund' => 'refunded',
            'refunded' => 'refunded',
            'payment_error' => 'payment_error',
            'outofstock' => 'out_of_stock',
            'awaiting_bank_wire_payment' => 'awaiting_bank_wire_payment',
            'awaiting_cheque_payment' => 'awaiting_cheque_payment',
            'remote_payment_accepted' => 'payment_accepted',
        ];

        return $map[$normalizedTemplate] ?? $normalizedTemplate;
    }

    private function normalizeEventKey(string $value): string
    {
        $value = trim(Tools::strtolower($value));
        if ($value === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';

        return trim($value, '_');
    }
}
