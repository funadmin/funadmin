<?php

declare(strict_types=1);

use app\admin\authorization\service\HostPermissionMigration;
use app\admin\authorization\service\PermissionSyncService;
use support\Db;

/**
 * 宿主后台权限按方法细分：旧权限行原地改名为按方法推导的权限码，持有旧权限的角色获得它守卫的全部方法；
 * 迁移前未登记的接口只登记、不授予；核心菜单绑定页面列表方法的推导码；目录行不带权限码，空目录删除。
 * 升级前后每个角色能访问的宿主接口集合不变。
 */
return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('permission') || !$schema->hasTable('casbin_rule') || !$schema->hasTable('admin_menu')) {
        return;
    }
    $migration = new HostPermissionMigration();
    $migration->apply(require base_path() . '/database/permission-maps/host-per-method.php');
    // 用户此前生成的宿主业务模块：按其控制器文件现场换算旧资源。
    $migration->apply(HostPermissionMigration::generatedLegacyMap());
    (new PermissionSyncService())->sync();
    $migration->rebindCoreMenus(require base_path() . '/database/permission-maps/host-menus.php');
    $migration->cleanGroups();
};
