<?php

declare(strict_types=1);

namespace app\common\form\validation;

/** 执行 FormSchema v2 运行值校验，并与前端共享相同规则语义。 */
final class FormSchemaDataValidator
{
    private const DEFAULT_MESSAGES = [
        'required' => '字段不能为空', 'type' => '字段类型不正确', 'min' => '字段值过小', 'max' => '字段值过大',
        'minLength' => '字段长度不足', 'maxLength' => '字段长度过长', 'length' => '字段长度不正确',
        'enum' => '字段值不在允许范围内', 'pattern' => '字段格式不正确', 'format' => '字段格式不正确',
        'same' => '字段值不一致', 'different' => '字段值必须不同', 'before' => '字段日期必须更早',
        'after' => '字段日期必须更晚', 'precision' => '字段小数位数过多', 'file' => '文件不符合要求',
        'array' => '字段必须为数组', 'object' => '字段必须为对象', 'items' => '数组成员不符合要求',
        'properties' => '对象属性不符合要求',
    ];

    /** @return array<int, array{field: string, rule: string, message: string, severity: string}> */
    public function validate(array $values, array $fieldRules): array
    {
        $errors = [];
        foreach ($fieldRules as $field => $rules) {
            $errors = array_merge($errors, $this->validateField((string) $field, $values[$field] ?? null, $rules, $values));
        }
        return $errors;
    }

    /** @return array<int, array{field: string, rule: string, message: string, severity: string}> */
    public function validateField(string $field, mixed $value, array $rules, array $values): array
    {
        $errors = [];
        foreach ($rules as $rule) {
            if (!is_array($rule) || !$this->whenMatches($rule['when'] ?? null, $values)) {
                continue;
            }
            $type = (string) ($rule['type'] ?? '');
            if ($type === 'async' || ($type !== 'required' && $this->isEmpty($value))) {
                continue;
            }
            $nested = $this->nestedErrors($type, $field, $value, $rule['value'] ?? null, $values);
            if ($nested === [] && $this->passes($type, $value, $rule['value'] ?? null, $values)) {
                continue;
            }
            $errors[] = [
                'field' => $nested[0]['field'] ?? $field,
                'rule' => $type,
                'message' => (string) ($rule['message'] ?? $nested[0]['message'] ?? self::DEFAULT_MESSAGES[$type] ?? '字段校验失败'),
                'severity' => (string) ($rule['severity'] ?? 'error'),
            ];
            if (($rule['bail'] ?? true) === true) {
                break;
            }
        }
        return $errors;
    }

    public function conditionMatches(mixed $condition, array $values): bool
    {
        return $this->whenMatches($condition, $values);
    }

    public function valueIsEmpty(mixed $value): bool
    {
        return $this->isEmpty($value);
    }

    public static function isPatternSafe(string $pattern): bool
    {
        if ($pattern === '' || strlen($pattern) > 512 || preg_match('~\(\?(?!:)|\\\\(?:[1-9kAGRKXCQEhHvV])|&&|--|\[\[:|[+*?}]\+~', $pattern)) {
            return false;
        }
        if (preg_match('~\((?:[^()\\\\]|\\\\.)*[+*](?:[^()\\\\]|\\\\.)*\)[+*{]~', $pattern)) {
            return false;
        }
        if (preg_match('~\([^()]*\|[^()]*\)[+*{]~', $pattern)) {
            return false;
        }
        $expression = '~' . str_replace('~', '\\~', $pattern) . '~u';
        return @preg_match($expression, '') !== false;
    }

    private function passes(string $type, mixed $value, mixed $argument, array $values): bool
    {
        return match ($type) {
            'required' => !$this->isEmpty($value),
            'type' => $this->hasType($value, (string) $argument),
            'min' => is_int($value) || is_float($value) ? $value >= (float) $argument : false,
            'max' => is_int($value) || is_float($value) ? $value <= (float) $argument : false,
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
            'file' => $this->validFiles($value, $argument),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && !array_is_list($value),
            'items', 'properties' => true,
            default => false,
        };
    }

    private function nestedErrors(string $type, string $field, mixed $value, mixed $argument, array $values): array
    {
        if ($type === 'items' && is_array($value) && array_is_list($value) && is_array($argument)) {
            foreach ($value as $index => $item) {
                $errors = $this->validateField($field . '.' . $index, $item, $argument, $values);
                if ($errors !== []) return $errors;
            }
        }
        if ($type === 'properties' && is_array($value) && !array_is_list($value) && is_array($argument)) {
            foreach ($argument as $property => $rules) {
                if (!is_array($rules)) continue;
                $errors = $this->validateField($field . '.' . $property, $value[$property] ?? null, $rules, $values);
                if ($errors !== []) return $errors;
            }
        }
        return [];
    }

    private function whenMatches(mixed $condition, array $values): bool
    {
        if ($condition === null) return true;
        if (!is_array($condition)) return false;
        $op = (string) ($condition['op'] ?? '');
        if (in_array($op, ['and', 'or'], true)) {
            $matches = array_map(fn (mixed $item): bool => $this->whenMatches($item, $values), (array) ($condition['conditions'] ?? []));
            return $op === 'and' ? !in_array(false, $matches, true) : in_array(true, $matches, true);
        }
        if ($op === 'not') return !$this->whenMatches($condition['condition'] ?? null, $values);
        $actual = $this->readPath($values, (string) ($condition['field'] ?? ''));
        $expected = $condition['value'] ?? null;
        return match ($op) {
            'eq' => $actual === $expected, 'neq' => $actual !== $expected,
            'gt' => (is_int($actual) || is_float($actual)) && (is_int($expected) || is_float($expected)) && $actual > $expected,
            'gte' => (is_int($actual) || is_float($actual)) && (is_int($expected) || is_float($expected)) && $actual >= $expected,
            'lt' => (is_int($actual) || is_float($actual)) && (is_int($expected) || is_float($expected)) && $actual < $expected,
            'lte' => (is_int($actual) || is_float($actual)) && (is_int($expected) || is_float($expected)) && $actual <= $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'notIn' => is_array($expected) && !in_array($actual, $expected, true),
            'contains' => is_array($actual)
                ? in_array($expected, $actual, true)
                : is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'startsWith' => is_string($actual) && is_string($expected) && str_starts_with($actual, $expected),
            'endsWith' => is_string($actual) && is_string($expected) && str_ends_with($actual, $expected),
            'empty' => $this->isEmpty($actual), 'notEmpty' => !$this->isEmpty($actual),
            'matches' => $this->matchesPattern($actual, $expected), default => false,
        };
    }

    private function readPath(array $values, string $path): mixed
    {
        $current = $values;
        foreach (array_values(array_filter(explode('.', $path), 'strlen')) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) return null;
            $current = $current[$segment];
        }
        return $current;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || $value === [];
    }

    private function hasType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value), 'number' => is_int($value) || is_float($value), 'integer' => is_int($value),
            'boolean' => is_bool($value), 'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && !array_is_list($value), 'null' => $value === null, default => false,
        };
    }

    private function lengthOf(mixed $value): ?int
    {
        if (is_array($value)) return count($value);
        if (!is_string($value)) return null;
        preg_match_all('/./us', $value, $characters);
        return count($characters[0]);
    }

    private function matchesPattern(mixed $value, mixed $pattern): bool
    {
        if (!is_string($value) || !is_string($pattern) || !self::isPatternSafe($pattern)) return false;
        return @preg_match('~' . str_replace('~', '\\~', $pattern) . '~u', $value) === 1;
    }

    private function matchesFormat(mixed $value, string $format): bool
    {
        if (!is_string($value)) return false;
        return match ($format) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && $this->dateTimestamp($value) !== null,
            'dateTime', 'datetime' => $this->dateTimestamp($value) !== null && str_contains($value, 'T'),
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1,
            default => false,
        };
    }

    private function compareDate(mixed $left, mixed $right, int $direction): bool
    {
        $leftTimestamp = $this->dateTimestamp($left);
        $rightTimestamp = $this->dateTimestamp($right);
        if ($leftTimestamp === null || $rightTimestamp === null) return false;
        return $direction < 0 ? $leftTimestamp < $rightTimestamp : $leftTimestamp > $rightTimestamp;
    }

    private function dateTimestamp(mixed $value): ?int
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|[+-]\d{2}:\d{2})?)?$/', $value) !== 1) return null;
        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    private function hasPrecision(mixed $value, int $precision): bool
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value) || $precision < 0) return false;
        $decimal = str_contains((string) $value, '.') ? substr((string) $value, strpos((string) $value, '.') + 1) : '';
        return strlen($decimal) <= $precision;
    }

    private function validFiles(mixed $value, mixed $constraints): bool
    {
        if (!is_array($constraints)) return false;
        $files = is_array($value) && array_is_list($value) ? $value : [$value];
        if (isset($constraints['count']) && count($files) > (int) $constraints['count']) return false;
        foreach ($files as $file) {
            if (!is_array($file) || array_is_list($file)) return false;
            if (isset($constraints['size']) && (!is_numeric($file['size'] ?? null) || (float) $file['size'] > (float) $constraints['size'])) return false;
            if (isset($constraints['type']) && !$this->fileTypeAllowed($file, (array) $constraints['type'])) return false;
        }
        return true;
    }

    private function fileTypeAllowed(array $file, array $allowed): bool
    {
        $mime = strtolower((string) ($file['type'] ?? $file['mime'] ?? ''));
        $name = strtolower((string) ($file['name'] ?? ''));
        foreach ($allowed as $type) {
            $type = strtolower((string) $type);
            if ($type === $mime || (str_ends_with($type, '/*') && str_starts_with($mime, substr($type, 0, -1)))) return true;
            if (str_starts_with($type, '.') && str_ends_with($name, $type)) return true;
        }
        return false;
    }
}
