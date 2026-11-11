<?php

declare(strict_types=1);

namespace app\cms\service;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\{Blueprint, Builder};
use support\Db;

/**
 * 1.6.0：统一软删除（迁移 007_soft_delete.sql）。
 *
 * 007 是按 information_schema 守卫的手写 PREPARE 迁移，无法由蓝图导出；本类是它的等价实现，
 * 与其他各段 Schema 一样按迁移顺序在最后执行（供 SQLite 测试建表）。MySQL 上 SQL 001–007 与各段
 * install() 的结构一致由 tests/Console/MySqlCmsSoftDeleteUpgradeTest.php 核对。
 *
 * - 除只追加的历史表外，每张表有自增 id 主键与 deleted_at DATETIME（建以它开头的索引）；
 * - 有唯一索引的表才加 deleted_token，唯一索引保持原名、改为 原列 + deleted_token；
 * - 原本没有主键的 4 张关系表新增自增 id（只在空表上重建，存量数据由 007 升级）。
 */
final class SoftDeleteSchema
{
    /** 只追加、没有删除功能的历史表，不加软删除列，唯一索引保持原样。 */
    public const EXEMPT = ['cms_form_event', 'cms_site_auth_audit', 'cms_model_version', 'cms_content_revision', 'cms_template_tag_version'];

    /** 原本没有主键的关系表：新增自增 id 主键，原唯一索引保持原名。 */
    public const SURROGATE = ['cms_site_admin', 'cms_site_model', 'cms_site_role_permission', 'cms_site_role_member'];

    /** 每张表的唯一索引（名称 => 原列）；007 之后为 原列 + deleted_token。 */
    public const UNIQUE_KEYS = [
        'cms_site' => ['cms_site_code_unique' => ['code']],
        'cms_site_locale' => ['cms_site_locale_site_id_locale_unique' => ['site_id', 'locale'], 'cms_site_locale_site_id_url_prefix_unique' => ['site_id', 'url_prefix']],
        'cms_domain' => ['cms_domain_host_unique' => ['host']],
        'cms_site_admin' => ['cms_site_admin_site_id_admin_id_unique' => ['site_id', 'admin_id']],
        'cms_content_model' => ['cms_content_model_code_unique' => ['code']],
        'cms_site_model' => ['cms_site_model_site_id_model_id_unique' => ['site_id', 'model_id']],
        'cms_category_locale' => ['cms_category_locale_category_id_locale_unique' => ['category_id', 'locale']],
        'cms_content_locale' => ['cms_content_locale_content_id_locale_unique' => ['content_id', 'locale']],
        'cms_route' => ['cms_route_site_id_path_unique' => ['site_id', 'path']],
        'cms_menu' => ['cms_menu_site_id_locale_code_unique' => ['site_id', 'locale', 'code']],
        'cms_template_tag' => ['cms_template_tag_site_id_locale_name_unique' => ['site_id', 'locale', 'name']],
        'cms_site_role' => ['cms_site_role_site_id_code_unique' => ['site_id', 'code']],
        'cms_site_role_permission' => ['cms_role_permission_unique' => ['site_id', 'role_id', 'permission_code']],
        'cms_site_role_member' => ['cms_role_member_unique' => ['site_id', 'role_id', 'admin_id']],
        'cms_member_submission' => ['cms_submission_request_unique' => ['site_id', 'member_id', 'request_key']],
        'cms_form' => ['cms_form_site_code_unique' => ['site_id', 'code']],
    ];

    /** @return list<string> 受软删除规则约束的表（与 007 顺序一致）。 */
    public static function tables(): array
    {
        $all = array_merge(Schema::tables(), SiteRoleSchema::tables(), ContributionSchema::tables(), FormSchema::tables());
        return array_values(array_diff($all, self::EXEMPT));
    }

    /** 对已存在的 CMS 表补齐软删除结构（缺哪张跳过哪张），可重复执行。 */
    public static function install(): void
    {
        $schema = Db::schema();
        foreach (self::tables() as $table) {
            if ($schema->hasTable($table)) self::table($schema, $table);
        }
    }

    private static function table(Builder $schema, string $table): void
    {
        if (in_array($table, self::SURROGATE, true) && !$schema->hasColumn($table, 'id')) {
            self::rebuild($schema, $table);
            return;
        }
        $hasDeletedAt = $schema->hasColumn($table, 'deleted_at');
        $retype = $hasDeletedAt && strtolower($schema->getColumnType($table, 'deleted_at')) === 'timestamp';
        $indexes = $schema->getIndexes($table);
        $hasIndex = array_filter($indexes, static fn(array $i): bool => ($i['columns'][0] ?? null) === 'deleted_at') !== [];
        $unique = self::UNIQUE_KEYS[$table] ?? [];
        $hasToken = $schema->hasColumn($table, 'deleted_token');
        $current = array_column($indexes, 'columns', 'name');
        $rebuild = array_filter($unique, static fn(array $columns, string $name): bool => isset($current[$name]) && end($current[$name]) !== 'deleted_token', ARRAY_FILTER_USE_BOTH);
        if ($hasDeletedAt && !$retype && $hasIndex && ($unique === [] || $hasToken) && $rebuild === []) return;
        $schema->table($table, static function (Blueprint $t) use ($table, $hasDeletedAt, $retype, $hasIndex, $unique, $hasToken, $rebuild): void {
            if ($retype) $t->dateTime('deleted_at')->nullable()->change();
            if (!$hasDeletedAt) $t->dateTime('deleted_at')->nullable();
            if (!$hasIndex) $t->index('deleted_at', 'idx_' . $table . '_deleted_at');
            if ($unique !== [] && !$hasToken) $t->unsignedBigInteger('deleted_token')->default(0);
            foreach ($rebuild as $name => $columns) {
                $t->dropUnique($name);
                $t->unique([...$columns, 'deleted_token'], $name);
            }
        });
        if ($unique !== []) {
            $connection = $schema->getConnection();
            $connection->table($table)->whereNotNull('deleted_at')->where('deleted_token', 0)
                ->update(['deleted_token' => new Expression($connection->getQueryGrammar()->wrap('id'))]);
        }
    }

    /** 关系表在空表上按最终结构重建（SQLite 不能给已有表加主键列）；有数据的库只能走 007 升级。 */
    private static function rebuild(Builder $schema, string $table): void
    {
        if ($schema->getConnection()->table($table)->exists()) {
            throw new \RuntimeException($table . ' 已有数据，请执行 CMS 迁移 007_soft_delete 升级');
        }
        $schema->drop($table);
        $schema->create($table, static function (Blueprint $t) use ($table): void {
            $t->id();
            $t->unsignedBigInteger('site_id');
            if ($table === 'cms_site_admin') {
                $t->unsignedBigInteger('admin_id');
            } elseif ($table === 'cms_site_model') {
                $t->unsignedBigInteger('model_id');
                $t->string('default_template', 128)->default('detail');
                $t->boolean('status')->default(true);
            } elseif ($table === 'cms_site_role_permission') {
                $t->unsignedBigInteger('role_id');
                $t->string('permission_code', 100);
            } else {
                $t->unsignedBigInteger('role_id');
                $t->unsignedBigInteger('admin_id');
                $t->index(['site_id', 'admin_id']);
            }
            $t->dateTime('deleted_at')->nullable();
            $t->unsignedBigInteger('deleted_token')->default(0);
            foreach (self::UNIQUE_KEYS[$table] as $name => $columns) $t->unique([...$columns, 'deleted_token'], $name);
            $t->index('deleted_at', 'idx_' . $table . '_deleted_at');
        });
    }
}
