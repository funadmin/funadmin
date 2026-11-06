<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionGrantMigration;
use app\admin\authorization\service\PermissionSyncService;
use support\Db;

/**
 * 插件后台权限按方法细分：登记推导出的新权限码，并把角色持有的旧粗粒度权限展开为新权限。
 * 升级前后每个角色能访问的插件接口集合不变；旧权限行停用保留以便追溯。
 */
return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('permission') || !$schema->hasTable('casbin_rule')) {
        return;
    }
    // 只登记插件方法的推导码；宿主方法在宿主迁移中处理。
    $sync = new PermissionSyncService();
    foreach (glob(app_path() . '/admin/plugins/*', GLOB_ONLYDIR) ?: [] as $directory) {
        $sync->sync(basename($directory));
    }
    $map = require base_path() . '/database/permission-maps/plugin-per-method.php';
    $present = Db::table('permission')->whereIn('code', array_keys($map))->pluck('code')->all();
    $keep = require base_path() . '/database/permission-maps/plugin-per-method-keep.php';
    $migration = new PermissionGrantMigration();
    $migration->apply(array_intersect_key($map, array_flip($present)), $keep);
    $migration->rebindMenus(require base_path() . '/database/permission-maps/plugin-per-method-menus.php');
};
