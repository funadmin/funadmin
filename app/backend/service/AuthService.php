<?php

declare(strict_types=1);

namespace app\backend\service;

use app\common\service\AbstractService;

/**
 * 后台认证兼容门面。
 */
class AuthService extends AbstractService
{
    public function isLogin(): bool
    {
        return (new AdminSessionService())->isLogin();
    }

    public function checkLogin(string $username, string $password, bool $rememberMe): bool
    {
        return (new AdminSessionService())->checkLogin($username, $password, $rememberMe);
    }

    public function logout(): bool
    {
        return (new AdminSessionService())->logout();
    }

    public function roleAccess(bool $authenticated = false): bool
    {
        return (new AdminAuthorizationService())->roleAccess($authenticated);
    }

    public function nodeAccess(string $url): bool
    {
        return (new AdminAuthorizationService())->nodeAccess($url);
    }

    public function permissionIdsForRoles(mixed $roles): array
    {
        return (new RoleScopeService())->permissionIdsForRoles($roles);
    }

    public function adminRoleIds(int $adminId): array
    {
        return (new RoleScopeService())->adminRoleIds($adminId);
    }

    public function rolePermissionIds(int $roleId): array
    {
        return (new RoleScopeService())->rolePermissionIds($roleId);
    }

    public function isSuperAdmin(): bool
    {
        return (new RoleScopeService())->isSuperAdmin();
    }

    public function currentRoleIds(): array
    {
        return (new RoleScopeService())->currentRoleIds();
    }

    public function manageableRoleIds(bool $includeOwn = false): array
    {
        return (new RoleScopeService())->manageableRoleIds($includeOwn);
    }

    public function canManageRole(int $roleId): bool
    {
        return (new RoleScopeService())->canManageRole($roleId);
    }

    public function canUseParentRole(int $roleId): bool
    {
        return (new RoleScopeService())->canUseParentRole($roleId);
    }

    public function canAssignRoles(mixed $roleIds): bool
    {
        return (new RoleScopeService())->canAssignRoles($roleIds);
    }

    public function canManageAdmin(mixed $admin, bool $allowSelf = false): bool
    {
        return (new RoleScopeService())->canManageAdmin($admin, $allowSelf);
    }

    public function canAssignPermissions(mixed $permissionIds): bool
    {
        return (new RoleScopeService())->canAssignPermissions($permissionIds);
    }
}
