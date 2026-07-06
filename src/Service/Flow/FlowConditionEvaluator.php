<?php

namespace Velox\MailSendVx\Service\Flow;

class FlowConditionEvaluator
{
    /**
     * @param array<int, mixed> $conditions
     * @param array<string, mixed> $payload
     */
    public function matches(array $conditions, array $payload): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                return false;
            }

            $path = (string) ($condition['path'] ?? $condition['field'] ?? '');
            $operator = strtolower(trim((string) ($condition['operator'] ?? 'eq')));
            $expectedValue = $condition['value'] ?? null;
            $actualValue = $this->getValueByPath($payload, $path);

            if (!$this->matchesCondition($actualValue, $operator, $expectedValue)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $actualValue
     * @param mixed $expectedValue
     */
    private function matchesCondition($actualValue, string $operator, $expectedValue): bool
    {
        switch ($operator) {
            case 'exists':
                return $actualValue !== null;
            case 'not_exists':
                return $actualValue === null;
            case 'empty':
                return $this->isEmptyValue($actualValue);
            case 'not_empty':
                return !$this->isEmptyValue($actualValue);
            case 'neq':
            case '!=':
                return $actualValue != $expectedValue;
            case 'gt':
                return is_numeric($actualValue) && is_numeric($expectedValue) && (float) $actualValue > (float) $expectedValue;
            case 'gte':
                return is_numeric($actualValue) && is_numeric($expectedValue) && (float) $actualValue >= (float) $expectedValue;
            case 'lt':
                return is_numeric($actualValue) && is_numeric($expectedValue) && (float) $actualValue < (float) $expectedValue;
            case 'lte':
                return is_numeric($actualValue) && is_numeric($expectedValue) && (float) $actualValue <= (float) $expectedValue;
            case 'contains':
                if (is_array($actualValue)) {
                    return in_array($expectedValue, $actualValue, true);
                }

                return is_scalar($actualValue) && is_scalar($expectedValue) && strpos((string) $actualValue, (string) $expectedValue) !== false;
            case 'not_contains':
                if (is_array($actualValue)) {
                    return !in_array($expectedValue, $actualValue, true);
                }

                return is_scalar($actualValue) && is_scalar($expectedValue) && strpos((string) $actualValue, (string) $expectedValue) === false;
            case 'starts_with':
                return is_scalar($actualValue) && is_scalar($expectedValue) && strpos((string) $actualValue, (string) $expectedValue) === 0;
            case 'ends_with':
                if (!is_scalar($actualValue) || !is_scalar($expectedValue)) {
                    return false;
                }

                $actual = (string) $actualValue;
                $expected = (string) $expectedValue;

                if ($expected === '') {
                    return true;
                }

                return substr($actual, -strlen($expected)) === $expected;
            case 'in':
                return is_array($expectedValue) && in_array($actualValue, $expectedValue, true);
            case 'not_in':
                return is_array($expectedValue) && !in_array($actualValue, $expectedValue, true);
            case 'eq':
            case '=':
            default:
                return $actualValue == $expectedValue;
        }
    }

    /**
     * @param mixed $value
     */
    private function isEmptyValue($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return empty($value);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return mixed|null
     */
    private function getValueByPath(array $payload, string $path)
    {
        if ($path === '') {
            return null;
        }

        $segments = explode('.', $path);
        $current = $payload;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }
}
