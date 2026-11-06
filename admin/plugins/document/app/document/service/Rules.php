<?php

declare(strict_types=1);

namespace app\document\service;

use InvalidArgumentException;

/** 文档插件共享的输入校验与常量。 */
final class Rules
{
    public const SCOPES = ['framework', 'plugin_dev', 'official_plugin', 'third_party'];
    public const TYPES = ['guide', 'reference', 'api', 'architecture', 'deployment', 'adr', 'changelog'];
    public const STATUSES = ['draft', 'published', 'archived'];

    public static function scope(mixed $value): string
    {
        $scope = is_string($value) ? $value : '';
        if (!in_array($scope, self::SCOPES, true)) throw new InvalidArgumentException('文档范围无效', 422);
        return $scope;
    }

    public static function type(mixed $value): string
    {
        $type = is_string($value) ? $value : '';
        if (!in_array($type, self::TYPES, true)) throw new InvalidArgumentException('文档类型无效', 422);
        return $type;
    }

    public static function slug(mixed $value): string
    {
        $slug = is_string($value) ? trim($value) : '';
        if (!preg_match('/\A[a-z0-9][a-z0-9-]{0,118}[a-z0-9]?\z/D', $slug)) {
            throw new InvalidArgumentException('标识只能包含小写字母、数字和连字符', 422);
        }
        return $slug;
    }

    public static function text(mixed $value, int $max, string $label, bool $required = false): string
    {
        $text = is_string($value) ? trim(strip_tags($value)) : '';
        if ($required && $text === '') throw new InvalidArgumentException($label . '不能为空', 422);
        if (mb_strlen($text) > $max) throw new InvalidArgumentException($label . '不能超过 ' . $max . ' 个字符', 422);
        return $text;
    }

    public static function pluginCode(mixed $value): string
    {
        $code = is_string($value) ? trim($value) : '';
        if ($code !== '' && !preg_match('/\A[a-z][a-z0-9]{0,63}\z/D', $code)) throw new InvalidArgumentException('插件标识无效', 422);
        return $code;
    }

    public static function id(mixed $value): int
    {
        if (is_int($value) && $value > 0) return $value;
        if (is_string($value) && preg_match('/\A[1-9][0-9]{0,18}\z/D', $value)) return (int) $value;
        throw new InvalidArgumentException('ID 无效', 422);
    }

    /** @return list<int> */
    public static function ids(mixed $value): array
    {
        if ($value === null || $value === '') return [];
        if (!is_array($value)) throw new InvalidArgumentException('目录列表无效', 422);
        return array_values(array_unique(array_map(self::id(...), $value)));
    }

    /** LIKE 关键字按字面匹配，转义通配符。 */
    public static function like(string $keyword): string
    {
        return '%' . addcslashes($keyword, '\\%_') . '%';
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function actor(): int
    {
        return (int) (request()?->context['admin']['id'] ?? 0);
    }
}
