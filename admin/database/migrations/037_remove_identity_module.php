<?php

declare(strict_types=1);

use app\common\service\database\MigrationService;
use support\Db;

/**
 * 移除企业身份中心（identity）模块。与核心 SQL 028_remove_identity_module.sql 等价，可重复执行。
 * 撤销授权并软删除菜单与权限（保留行以便追溯），再删除只属于身份中心的数据表。
 */
return static function (): void {
    $schema = Db::schema();
    $now = date('Y-m-d H:i:s');
    $identityPermissions = static function ($query): void {
        $query->where('obj', 'like', 'admin/identity.%')->orWhere('obj', 'like', 'identity/%')->orWhere('code', 'like', 'identity:%');
    };
    if ($schema->hasTable('permission')) {
        if ($schema->hasTable('casbin_rule')) {
            foreach (Db::table('permission')->where('app_name', 'admin')->where($identityPermissions)->get(['obj', 'act']) as $permission) {
                Db::table('casbin_rule')->where('ptype', 'p')->where('v2', $permission->obj)->where('v3', $permission->act)->delete();
            }
        }
        Db::table('permission')->where('app_name', 'admin')->where($identityPermissions)->whereNull('deleted_at')
            ->update(['status' => 0, 'deleted_at' => $now, 'updated_at' => $now]);
    }
    if ($schema->hasTable('admin_menu')) {
        Db::table('admin_menu')->where('app_name', 'admin')->where(static function ($query): void {
            $query->where('href', '/applications')->orWhere('href', 'like', '/applications/%')
                ->orWhereIn('source_name', ['enterprise_application_center', 'oauth_client_console', 'sso_phase8_application_center']);
        })->whereNull('deleted_at')->update(['status' => 0, 'deleted_at' => $now, 'updated_at' => $now]);
    }
    $schema->disableForeignKeyConstraints();
    try {
        foreach (MigrationService::RETIRED_CORE_TABLES as $table) $schema->dropIfExists($table);
    } finally {
        $schema->enableForeignKeyConstraints();
    }
};
