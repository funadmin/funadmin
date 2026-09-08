<?php

declare(strict_types=1);

namespace app\common\form\validation;

/**
 * 执行 FormSchema v2 运行值校验，并与前端共享相同规则语义。
 */
final class FormSchemaDataValidator
{
    private const DEFAULT_MESSAGES = [
        'required' => '字段不能为空',
        'type' => '字段类型不正确',
        'min' => '字段值过小',
        'max' => '字段值过大',
        'minLength' => '字段长度不足',
        'maxLength' => '字段长度过长',
        'length' => '字段长度不正确',
        'enum' => '字段值不在允许范围内',
        'pattern' => '字段格式不正确',
        'format' => '字段格式不正确',
        'same' => '字段值不一致',
        'different' => '字段值必须不同',
        'before' => '字段日期必须更早',
        'after' => '字段日期必须更晚',
        'precision' => '字段小数位数过多',
        'array' => '字段必须为数组',
        'object' => '字段必须为对象',
    ];

    /**
     * 按字段声明顺序校验整份表单值。
     *
     * @param array<string, mixed> $values
     * @param array<string, array<int, array<string, mixed>>> $fieldRules
     * @return array<int, array{field: string, rule: string, message: string}>
     */
    public function validate(array $values, array $fieldRules): array
    {
        $errors = [];
        foreach ($fieldRules as $field => $rules) {
            $errors = array_merge(
                $errors,
                $this->validateField((string) $field, $values[$field] ?? null, $rules, $values)
            );
        }
        return $errors;
    }

    /**
     * 校验单个字段，命中第一条失败规则后停止。
     *
     * @param array<int, array<string, mixed>> $rules
     * @param array<string, mixed> $values
     * @return array<int, array{field: string, rule: string, message: string}>
     */
    public function validateField(string $field, mixed $value, array $rules, array $values): array
    {
        foreach ($rules as $rule) {
            $type = (string) ($rule['type'] ?? '');
            if ($type !== 'required' && $this->isEmpty($value)) {
                continue;
            }
            if ($this->passes($type, $value, $rule['value'] ?? null, $values)) {
                continue;
            }
            return [[
                'field' => $field,
                'rule' => $type,
                'message' => (string) ($rule['message'] ?? self::DEFAULT_MESSAGES[$type] ?? '字段校验失败'),
            ]];
        }
        return [];
    }

    /** @param array<string, mixed> $values */
    private function passes(string $type, mixed $value, mixed $argument, array $values): bool
    {
        return match ($type) {
            'required' => !$this->isEmpty($value),
            'type' => $this->hasType($value, (string) $argument),
            'min' => is_numeric($value) && (float) $value >= (float) $argument,
            'max' => is_numeric($value) && (float) $value <= (float) $argument,
            'minLength' => ($length = $this->lengthOf($value)) !== null && $length >= (int) $argument,
            'maxLength' => ($length = $this->lengthOf($value)) !== null && $length <= (int) $argument,
            'length' => $this->lengthOf($value) === (int) $argument,
            'enum' => is_array($argument) && in_array($value, $argument, true),
            'pattern' => $this->matchesPattern($value, $argument),
            'format' => $this->matchesFormat($value, (string) $argument),
            'same' => array_key_exists((string) $argument, $values) && $value === $values[(string) $argument],
            'different' => array_key_exists((string) $argument, $values) && $value !== $values[(string) $argument],
            'before' => $this->compareDate($value, $values[(string) $argument] ?? null, -1),
            'after' => $this->compareDate($value, $values[(string) $argument] ?? null, 1),
            'precision' => $this->hasPrecision($value, (int) $argument),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && !array_is_list($value),
            default => false,
        };
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || $value === [];
    }

    private function hasType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && !array_is_list($value),
            'null' => $value === null,
            default => false,
        };
    }

    private function lengthOf(mixed $value): ?int
    {
        if (is_array($value)) {
            return count($value);
        }
        if (!is_string($value)) {
            return null;
        }
        preg_match_all('/./us', $value, $characters);
        return count($characters[0]);
    }

    private function matchesPattern(mixed $value, mixed $pattern): bool
    {
        if (!is_string($value) || !is_string($pattern)) {
            return false;
        }
        $expression = '~' . str_replace('~', '\\~', $pattern) . '~u';
        return @preg_match($expression, $value) === 1;
    }

    private function matchesFormat(mixed $value, string $format): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return match ($format) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false,
            'dateTime', 'datetime' => $this->dateTimestamp($value) !== null && str_contains($value, 'T'),
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1,
            default => false,
        };
    }

    private function compareDate(mixed $left, mixed $right, int $direction): bool
    {
        $leftTimestamp = $this->dateTimestamp($left);
        $rightTimestamp = $this->dateTimestamp($right);
        if ($leftTimestamp === null || $rightTimestamp === null) {
            return false;
        }
        return $direction < 0 ? $leftTimestamp < $rightTimestamp : $leftTimestamp > $rightTimestamp;
    }

    private function dateTimestamp(mixed $value): ?int
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|[+-]\d{2}:\d{2})?)?$/', $value) !== 1) {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    private function hasPrecision(mixed $value, int $precision): bool
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value) || $precision < 0) {
            return false;
        }
        $text = (string) $value;
        $decimal = str_contains($text, '.') ? substr($text, strpos($text, '.') + 1) : '';
        return strlen(rtrim($decimal, "\r\n")) <= $precision;
    }
}
