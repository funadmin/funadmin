<?php

declare(strict_types=1);

namespace app\backend\service;

use app\backend\model\Permission;

/**
 * 使用插件 manifest 注册的权限资源执行后台授权，禁止根据请求路由推导权限。
 */
class PluginPermissionAuthorizationService
{
    public function __construct(
        private readonly ?CasbinService $casbin = null
    ) {
    }

    public function authorize(int $adminId, string $plugin, string $permission): bool
    {
        $plugin = strtolower(trim($plugin));
        $permission = strtolower(trim($permission));
        if ($adminId <= 0
            || preg_match('/^[a-z][a-z0-9]*$/', $plugin) !== 1
            || preg_match('/^' . preg_quote($plugin, '/') . ':[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/', $permission) !== 1) {
            return false;
        }

        $resource = Permission::where('source_type', 'plugin')
            ->where('source_name', $plugin)
            ->where('code', $permission)
            ->where('status', 1)
            ->where('resource_type', Permission::TYPE_ROUTE)
            ->where('obj', '<>', '')
            ->where('act', '<>', '')
            ->field('obj,act')
            ->find();
        if (!$resource) {
            return false;
        }
        if ($adminId === (int) config('funadmin.superAdminId')) {
            return true;
        }

        return ($this->casbin ?? CasbinService::instance())->enforceAdmin(
            $adminId,
            (string) $resource->obj,
            (string) $resource->act
        );
    }
}
