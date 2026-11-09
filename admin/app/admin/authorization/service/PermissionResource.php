<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com/
 * ----------------------------------------------------------------------------
 * 采用最新webman实现
 * ============================================================================
 */

namespace app\admin\authorization\service;

/**
 * Casbin 主体、角色与域的标识。权限码推导见 PermissionCode。
 */
class PermissionResource
{
    public static function subject(int $adminId): string
    {
        return 'admin:' . $adminId;
    }

    public static function role(int $groupId): string
    {
        return 'role:' . $groupId;
    }

    public static function domain(?string $domain = null): string
    {
        $domain = trim((string) ($domain ?: config('funadmin.auth_domain', 'default')));
        return $domain !== '' ? strtolower($domain) : 'default';
    }
}
