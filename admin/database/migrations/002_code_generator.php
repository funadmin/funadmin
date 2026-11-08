<?php

declare(strict_types=1);

use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\PermissionCode;
use app\admin\authorization\service\PermissionSyncService;
use support\Db;

/**
 * 代码生成器（与核心 SQL 002_code_generator.sql 等价，可重复执行）：
 * 核心菜单「业务开发」改名「代码生成」（管理员改过名称的不动）；登记表结构同步与生成代码下载两个接口权限，
 * 与 preview-formal-generation 同一父级与来源；持有 inspect-database 的角色获得 database-sync，
 * 持有 generate 能力的角色获得 formal-generation-archive，其他角色不变。超级管理员按管理员 ID 放行，无需授权行。
 */
return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('permission') || !$schema->hasTable('casbin_rule') || !$schema->hasTable('admin_menu')) {
        return;
    }
    // 先按代码登记（新接口出现在权限表中），再对齐父级、来源、名称与排序。
    (new PermissionSyncService())->sync();

    Db::transaction(static function (): void {
        $now = date('Y-m-d H:i:s');
        Db::table('admin_menu')->where('app_name', 'admin')->where('source_type', 'admin_web')->where('source_name', 'business_development')
            ->where('href', '/development/business/mine')->where('name', '业务开发')
            ->update(['name' => '代码生成', 'updated_at' => $now]);

        $sibling = Db::table('permission')->where('code', 'development:business:preview-formal-generation')->first();
        foreach ([
            'development:business:database-sync' => ['同步表结构', 33],
            'development:business:formal-generation-archive' => ['下载生成代码', 52],
        ] as $code => [$name, $sort]) {
            $values = PermissionCode::resource($code) + [
                'app_name' => 'admin', 'name' => $name, 'resource_type' => 'route', 'status' => 1, 'sort_order' => $sort,
                'deleted_at' => null, 'updated_at' => $now,
                'pid' => (int) ($sibling->pid ?? 0), 'source_type' => (string) ($sibling->source_type ?? 'system'), 'source_name' => (string) ($sibling->source_name ?? ''),
            ];
            $existing = Db::table('permission')->where('code', $code)->first();
            if ($existing === null) {
                Db::table('permission')->insert($values + ['code' => $code, 'is_public' => 0, 'created_at' => $now]);
            } elseif ($sibling !== null && (string) $existing->source_type !== 'plugin') {
                Db::table('permission')->where('id', $existing->id)->update($values);
            }
        }

        foreach (['inspect-database' => 'database-sync', 'generate' => 'formal-generation-archive'] as $held => $granted) {
            $holders = Db::table('casbin_rule')->where('ptype', 'p')->where('v2', 'development/business')->where('v3', $held)->get(['v0', 'v1']);
            foreach ($holders as $holder) {
                $rule = CasbinService::ruleRow('p', [(string) $holder->v0, (string) $holder->v1, 'development/business', $granted]);
                if (!Db::table('casbin_rule')->where('rule_hash', $rule['rule_hash'])->exists()) {
                    Db::table('casbin_rule')->insert($rule);
                }
            }
        }
    });
    CasbinService::instance()->reload();
};
