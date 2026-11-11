<?php

declare(strict_types=1);

namespace app\common\crud;

/**
 * 生成表的软删除列规则（与 admin/README.md「软删除」一致），供建表 SQL、插件结构快照和模型模板共用：
 *
 * - 启用软删除时有 deleted_at（建索引 idx_<表>_deleted_at）；
 * - 只有存在唯一字段时才加 deleted_token，且每个唯一键以 deleted_token 结尾；
 * - deleted_token 只存数字：主键不是整数（UUID 或字符串）且需要令牌时，另加自增序号列 row_no 作为令牌来源。
 */
final class GeneratedSoftDeleteColumns
{
    public const TOKEN = 'deleted_token';
    public const ROW_NO = 'row_no';
    public const TOKEN_DEFINITION = 'bigint unsigned NOT NULL DEFAULT 0';
    public const ROW_NO_DEFINITION = 'bigint unsigned NOT NULL AUTO_INCREMENT';

    /**
     * @return array{softDeletes: bool, token: bool, rowNo: bool}
     */
    public static function plan(array $data): array
    {
        $softDeletes = (bool) ($data['softDeletes'] ?? false);
        $unique = false;
        $integerPrimary = true;
        foreach ((array) ($data['fields'] ?? []) as $field) {
            if (($field['unique'] ?? false) === true && ($field['primary'] ?? false) !== true) {
                $unique = true;
            }
            if (($field['primary'] ?? false) === true) {
                $integerPrimary = ($field['format'] ?? '') !== 'uuid'
                    && preg_match('/(?:tinyint|smallint|mediumint|bigint|int)/', strtolower((string) ($field['dbType'] ?? ''))) === 1;
            }
        }
        $token = $softDeletes && $unique;
        return ['softDeletes' => $softDeletes, 'token' => $token, 'rowNo' => $token && !$integerPrimary];
    }

    /** 唯一键列：需要令牌时以 deleted_token 结尾。 */
    public static function uniqueColumns(string $column, bool $token): array
    {
        return $token ? [$column, self::TOKEN] : [$column];
    }

    public static function columnList(array $columns): string
    {
        return implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
    }
}
