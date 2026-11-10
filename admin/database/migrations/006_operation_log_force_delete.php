<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionCode;
use support\Db;

/**
 * 操作日志彻底删除（与核心 SQL 006_operation_log_force_delete.sql 等价，可重复执行）：
 * 登记 system:operation-log:force-delete（彻底删除）与 system:operation-log:clear-force（彻底清空）两个接口权限，
 * 与软删除权限 system:operation-log:delete 同一父级与来源。只登记，不授予任何角色：真实删除不可恢复，
 * 由管理员按需授权；超级管理员按管理员 ID 放行。已由代码同步提前登记（pid=0、system）的未删除行对齐为同一终态，插件归属的同名行不动。
 */
return static function (): void {
    if (!Db::schema()->hasTable('permission')) {
        return;
    }
    Db::transaction(static function (): void {
        $now = date('Y-m-d H:i:s');
        $sibling = Db::table('permission')->where('code', 'system:operation-log:delete')->whereNull('deleted_at')->first();
        foreach ([
            'system:operation-log:force-delete' => ['彻底删除', 30],
            'system:operation-log:clear-force' => ['彻底清空', 40],
        ] as $code => [$name, $sort]) {
            $values = PermissionCode::resource($code) + [
                'app_name' => 'admin', 'name' => $name, 'resource_type' => 'route', 'status' => 1, 'sort_order' => $sort,
                'updated_at' => $now,
                'pid' => (int) ($sibling->pid ?? 0), 'source_type' => (string) ($sibling->source_type ?? 'system'), 'source_name' => (string) ($sibling->source_name ?? ''),
            ];
            $existing = Db::table('permission')->where('code', $code)->whereNull('deleted_at')->first();
            if ($existing === null) {
                Db::table('permission')->insert($values + ['code' => $code, 'is_public' => 0, 'created_at' => $now]);
            } elseif ($sibling !== null && (string) $existing->source_type !== 'plugin') {
                Db::table('permission')->where('id', $existing->id)->update($values);
            }
        }
    });
};
