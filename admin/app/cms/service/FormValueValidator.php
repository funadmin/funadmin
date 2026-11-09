<?php
declare(strict_types=1);
namespace app\cms\service;
final class FormValueValidator
{
    /** Every error key validate() can produce; callers translate them per locale. */
    public const ERRORS = ['required', 'invalid', 'too_short', 'too_long', 'invalid_email', 'invalid_phone', 'invalid_number', 'invalid_option', 'out_of_range', 'invalid_date', 'must_accept', 'too_many_files'];
    private const TEMPORAL_FORMATS = [
        'date' => ['Y-m-d'],
        'datetime' => ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'],
        'time' => ['H:i', 'H:i:s'],
    ];

    /**
     * @param list<array<string,mixed>> $fields normalized by FormFieldCatalog::normalize
     * @param array<string,int> $fileCounts field name => number of uploaded files
     * @return array{values: array<string,mixed>, errors: array<string,string>}
     */
    public static function validate(array $fields, array $input, array $fileCounts): array
    {
        $values = [];
        $errors = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            if (!in_array($field['type'], FormFieldCatalog::DATA_TYPES, true)) continue;
            if (in_array($field['type'], FormFieldCatalog::FILE_TYPES, true)) {
                $count = (int)($fileCounts[$name] ?? 0);
                if ($count === 0 && $field['required']) $errors[$name] = 'required';
                elseif ($count > $field['max_files']) $errors[$name] = 'too_many_files';
                continue;
            }
            [$value, $error] = self::value($field, $input[$name] ?? null);
            if ($error === null) $values[$name] = $value;
            else $errors[$name] = $error;
        }
        return ['values' => $values, 'errors' => $errors];
    }

    /** Parses an HTML date/datetime-local/time value; returns the normalized string or null when malformed. */
    public static function temporal(string $type, string $value): ?string
    {
        foreach (self::TEMPORAL_FORMATS[$type] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($parsed !== false && $parsed->format($format) === $value) return $parsed->format(str_replace('\T', ' ', $format));
        }
        return null;
    }

    /** @return array{0: mixed, 1: ?string} */
    private static function value(array $field, mixed $raw): array
    {
        $type = $field['type'];
        if ($type === 'hidden') return self::hidden($field, $raw);
        if ($type === 'consent') {
            $accepted = in_array($raw, [true, 1, '1', 'on', 'true', 'yes'], true);
            return $field['required'] && !$accepted ? [null, 'must_accept'] : [$accepted, null];
        }
        if ($type === 'checkbox' || ($type === 'select' && $field['multiple'])) return self::choices($field, $raw);
        $options = in_array($type, FormFieldCatalog::OPTION_TYPES, true);
        if ($raw !== null && !is_scalar($raw)) return [null, $options ? 'invalid_option' : 'invalid'];
        $text = self::clean((string)$raw, $type === 'textarea');
        if ($text === null) return [null, 'invalid'];
        if ($text === '') return [null, $field['required'] ? 'required' : null];
        if ($options) return in_array($text, array_column($field['options'], 'value'), true) ? [$text, null] : [null, 'invalid_option'];
        return match ($type) {
            'text', 'textarea' => self::text($field, $text),
            'email' => mb_strlen($text) <= 254 && filter_var($text, FILTER_VALIDATE_EMAIL) !== false ? [$text, null] : [null, 'invalid_email'],
            'phone' => preg_match('/^[0-9 +\-()]{5,32}$/D', $text) ? [$text, null] : [null, 'invalid_phone'],
            'number' => self::number($field, $text),
            'date', 'datetime', 'time' => self::date($field, $text),
        };
    }

    private static function text(array $field, string $text): array
    {
        $length = mb_strlen($text);
        $max = min(FormFieldCatalog::TEXT_LIMITS[$field['type']], $field['max'] ?? PHP_INT_MAX);
        if ($length > $max) return [null, 'too_long'];
        if ($length < ($field['min'] ?? 0)) return [null, 'too_short'];
        return [$text, null];
    }

    private static function number(array $field, string $text): array
    {
        if (!is_numeric($text)) return [null, 'invalid_number'];
        $number = $text + 0;
        if (!is_finite((float)$number)) return [null, 'invalid_number'];
        if ($field['integer'] && is_float($number)) {
            if (floor($number) !== $number || abs($number) >= PHP_INT_MAX) return [null, 'invalid_number'];
            $number = (int)$number;
        }
        if (isset($field['min']) && $number < $field['min'] || isset($field['max']) && $number > $field['max']) return [null, 'out_of_range'];
        return [$number, null];
    }

    private static function date(array $field, string $text): array
    {
        $value = self::temporal($field['type'], $text);
        if ($value === null) return [null, 'invalid_date'];
        if (isset($field['min']) && $value < $field['min'] || isset($field['max']) && $value > $field['max']) return [null, 'out_of_range'];
        return [$value, null];
    }

    private static function choices(array $field, mixed $raw): array
    {
        if ($raw === null || $raw === '') $raw = [];
        if (!is_array($raw) || !array_is_list($raw)) return [null, 'invalid_option'];
        $allowed = array_column($field['options'], 'value');
        $picked = [];
        foreach ($raw as $item) {
            if (!is_string($item) && !is_int($item) || !in_array((string)$item, $allowed, true)) return [null, 'invalid_option'];
            $picked[] = (string)$item;
        }
        $picked = array_values(array_intersect($allowed, $picked));
        if ($picked === []) return [[], $field['required'] ? 'required' : null];
        $count = count($picked);
        if (isset($field['min']) && $count < $field['min'] || isset($field['max']) && $count > $field['max']) return [null, 'out_of_range'];
        return [$picked, null];
    }

    private static function hidden(array $field, mixed $raw): array
    {
        $value = $field['value'];
        if ($field['from_query'] && is_scalar($raw)) {
            $query = self::clean((string)$raw, false);
            if ($query !== null && $query !== '') $value = mb_substr($query, 0, FormFieldCatalog::TEXT_LIMITS['hidden']);
        }
        if ($value === '') return [null, $field['required'] ? 'required' : null];
        return [$value, null];
    }

    /** Trims and strips control characters (keeping line breaks and tabs for multi-line text); null for invalid UTF-8. */
    private static function clean(string $value, bool $multiline): ?string
    {
        if (!mb_check_encoding($value, 'UTF-8')) return null;
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        if (!$multiline) $value = str_replace(["\n", "\t"], ' ', $value);
        return trim((string)preg_replace('/[\x00-\x08\x0B-\x1F\x7F\x{80}-\x{9F}]/u', '', $value));
    }
}
