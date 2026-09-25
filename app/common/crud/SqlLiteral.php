<?php

declare(strict_types=1);

namespace app\common\crud;

/**
 * 生成 SQL 制品中的字符串字面量。
 *
 * MySQL 默认（未启用 NO_BACKSLASH_ESCAPES）会把反斜杠当作转义符，只替换单引号无法阻止字面量逃逸；
 * 换行同样转义，保证字面量内不出现以 `--` 开头的物理行，避免被迁移切分器当作注释剥离。
 */
final class SqlLiteral
{
    public static function quote(mixed $value): string
    {
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_int($value) || is_float($value)) return (string) $value;
        return "'" . strtr((string) $value, [
            '\\' => '\\\\',
            "'" => "''",
            "\0" => '\\0',
            "\n" => '\\n',
            "\r" => '\\r',
            "\x1a" => '\\Z',
        ]) . "'";
    }
}
