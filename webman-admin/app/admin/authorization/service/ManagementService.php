<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use app\admin\authorization\service\AuthorizationService;
use DomainException;
use support\Db;

final class ManagementService
{
    private const TABLES = [
        'users' => 'admin',
        'roles' => 'auth_group',
        'permissions' => 'permission',
        'menus' => 'admin_menu',
    ];

    public static function tableFor(string $resource): string
    {
        return self::TABLES[$resource] ?? throw new DomainException('资源不存在', 404);
    }

    public function save(int $actor, string $resource, ?int $id, array $input): array
    {
        $this->assertWrite($actor, $resource, $id === null ? 'add' : 'edit');
        $this->assertReservedRecord($resource, $id);
        if (array_key_exists('status', $input) && !in_array($input['status'], [0, 1, '0', '1', false, true], true)) {
            throw new DomainException('状态参数不合法', 422);
        }
        if ($resource === 'roles' && array_key_exists('status', $input) && !in_array($input['status'], [0, 1, false, true], true)) {
            throw new DomainException('状态参数不合法', 422);
        }
        if ($resource === 'users' && array_key_exists('password', $input)
            && (!is_string($input['password']) || strlen($input['password']) < 12 || strlen($input['password']) > 72 || str_contains($input['password'], "\0"))) {
            throw new DomainException('密码需为 12～72 字节', 422);
        }
        return Db::transaction(function () use ($resource, $id, $input): array {
            $isUpdate = $id !== null;
            $table = self::tableFor($resource);
            if ($resource === 'menus' && array_key_exists('permission', $input) && !array_key_exists('permissionId', $input)) {
                $input['permissionId'] = Db::table('permission')->where('code', $input['permission'])->where('status', 1)->whereNull('deleted_at')->value('id');
            }
            if ($isUpdate) {
                $existing = Db::table($table)->where('id', $id)->whereNull('deleted_at')->first();
                if (!$existing) throw new DomainException('记录不存在', 404);
                $input = array_merge($this->serialize($resource, $existing), $input);
            }
            $now = date('Y-m-d H:i:s');
            $data = $this->dataFor($resource, $input, $now);
            if ($resource === 'menus') {
                if (!Db::table('permission')->where('id', $data['permission_id'])->where('status', 1)->whereNull('deleted_at')->whereIn('resource_type', ['route', 'capability'])->exists()) {
                    throw new DomainException('页面菜单必须绑定启用的路由或能力权限', 422);
                }
                $menu = $id === null ? [] : (array) Db::table($table)->where('id', $id)->first();
                $error = \app\admin\authorization\model\AdminMenu::validateAttributes(array_merge(['pid' => 0], $menu, $data));
                if ($error !== null) throw new DomainException($error, 422);
            }
            if ($id === null) {
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $id = (int) Db::table($table)->insertGetId($data);
            } else {
                if (!Db::table($table)->where('id', $id)->exists()) {
                    throw new DomainException('记录不存在', 404);
                }
                Db::table($table)->where('id', $id)->update($data);
            }
            if ($resource === 'users' && array_key_exists('roleIds', $input)) {
                $this->syncAdminRoles($id, $input['roleIds']);
            }
            if ($resource === 'roles' && array_key_exists('permissionIds', $input)) {
                $this->syncRolePermissions($id, $input['permissionIds']);
            }
            if ($resource === 'users' && $isUpdate && (array_key_exists('password', $data) || !(bool) $data['status'])) {
                (new \app\admin\authentication\service\AdminSessionService())->revokeAll($id);
            }
            return $this->serialize($resource, Db::table($table)->where('id', $id)->first());
        });
    }

    public function remove(int $actor, string $resource, int $id): void
    {
        $this->assertWrite($actor, $resource, 'delete');
        $this->assertReservedRecord($resource, $id);
        $table = self::tableFor($resource);
        if (!Db::table($table)->where('id', $id)->exists()) {
            throw new DomainException('记录不存在', 404);
        }
        Db::table($table)->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        if ($resource === 'users') {
            (new \app\admin\authentication\service\AdminSessionService())->revokeAll($id);
        }
    }

    private function assertReservedRecord(string $resource, ?int $id): void
    {
        if (($resource === 'users' && $id === (int) config('auth.super_admin_id', 1))
            || ($resource === 'roles' && $id === (int) config('auth.super_role_id', 1))) {
            throw new DomainException('系统超级管理员或角色不可操作', 403);
        }
    }

    private function assertWrite(int $actor, string $resource, string $action): void
    {
        $permission = ['users' => 'user', 'roles' => 'role', 'permissions' => 'permission', 'menus' => 'menu'][$resource] ?? null;
        if ($permission === null) {
            throw new DomainException('资源不存在', 404);
        }
        (new AuthorizationService())->requirePermission($actor, "system:$permission:$action", true);
    }

    private function dataFor(string $resource, array $input, string $now): array
    {
        if ($resource === 'menus' && !isset($input['permissionId']) && isset($input['permission'])) {
            $input['permissionId'] = Db::table('permission')->where('code', $input['permission'])->where('status', 1)->whereNull('deleted_at')->value('id');
        }
        return match ($resource) {
            'users' => array_filter([
                'username' => $input['username'] ?? null,
                'password' => isset($input['password']) ? password_hash((string) $input['password'], PASSWORD_DEFAULT) : null,
                'real_name' => $input['nickname'] ?? null,
                'email' => $input['email'] ?? null,
                'mobile' => $input['mobile'] ?? null,
                'status' => $input['status'] ?? 1,
                'updated_at' => $now,
            ], static fn ($value): bool => $value !== null),
            'roles' => array_filter([
                'name' => $input['name'] ?? null,
                'code' => $input['code'] ?? null,
                'status' => $input['status'] ?? 1,
                'remark' => $input['remark'] ?? '',
                'updated_at' => $now,
            ], static fn ($value): bool => $value !== null),
            'permissions' => array_filter([
                'app_name' => $input['appName'] ?? 'admin',
                'code' => $input['code'] ?? null,
                'obj' => $input['object'] ?? '',
                'act' => $input['action'] ?? '',
                'name' => $input['name'] ?? '',
                'resource_type' => $input['resourceType'] ?? 'route',
                'status' => $input['status'] ?? 1,
                'updated_at' => $now,
            ], static fn ($value): bool => $value !== null),
            'menus' => array_filter([
                'name' => $input['name'] ?? '',
                'href' => $input['path'] ?? '',
                'query' => http_build_query(['name' => (string) ($input['routeName'] ?? ''), 'type' => 'C', 'component' => (string) ($input['component'] ?? '')]),
                'permission_id' => (int) ($input['permissionId'] ?? 0),
                'status' => $input['status'] ?? 1,
                'updated_at' => $now,
            ], static fn ($value): bool => $value !== null),
        };
    }

    private function syncAdminRoles(int $adminId, mixed $roleIds): void
    {
        if (!is_array($roleIds)) {
            throw new DomainException('角色 ID 列表不合法', 422);
        }
        Db::table('casbin_rule')->where('ptype', 'g')->where('v0', 'admin:' . $adminId)->delete();
        foreach (array_unique(array_map('intval', $roleIds)) as $roleId) {
            if ($roleId <= 0 || !Db::table('auth_group')->where('id', $roleId)->exists()) {
                throw new DomainException('角色不存在', 422);
            }
            $this->insertRule('g', ['admin:' . $adminId, 'role:' . $roleId, 'default']);
        }
    }

    private function syncRolePermissions(int $roleId, mixed $permissionIds): void
    {
        if (!is_array($permissionIds)) {
            throw new DomainException('权限 ID 列表不合法', 422);
        }
        Db::table('casbin_rule')->where('ptype', 'p')->where('v0', 'role:' . $roleId)->delete();
        foreach (array_unique(array_map('intval', $permissionIds)) as $permissionId) {
            $permission = Db::table('permission')->where('id', $permissionId)->where('status', 1)->first();
            if (!$permission) {
                throw new DomainException('权限不存在', 422);
            }
            $this->insertRule('p', ['role:' . $roleId, 'default', $permission->obj, $permission->act]);
        }
    }

    private function insertRule(string $ptype, array $values): void
    {
        $row = ['ptype' => $ptype, 'rule_hash' => hash('sha256', implode("\x1f", array_merge([$ptype], $values)))];
        foreach (range(0, 5) as $index) {
            $row['v' . $index] = (string) ($values[$index] ?? '');
        }
        Db::table('casbin_rule')->insert($row);
    }

    private function serialize(string $resource, object $row): array
    {
        $result = (array) $row;
        unset($result['password'], $result['token']);
        if ($resource === 'users') {
            $result['nickname'] = (string) ($row->real_name ?? '');
            $result['roleIds'] = (new AuthorizationService())->roleIds((int) $row->id);
        } elseif ($resource === 'roles') {
            $result['permissionIds'] = (new AuthorizationService())->permissionIds((int) $row->id);
        } elseif ($resource === 'permissions') {
            $result['object'] = (string) $row->obj;
            $result['action'] = (string) $row->act;
            $result['appName'] = (string) $row->app_name;
            $result['resourceType'] = (string) $row->resource_type;
        } elseif ($resource === 'menus') {
            parse_str((string) $row->query, $meta);
            $result['path'] = (string) $row->href;
            $result['routeName'] = (string) ($meta['name'] ?? '');
            $result['component'] = (string) ($meta['component'] ?? '');
            $result['permissionId'] = (int) $row->permission_id;
            $result['permission'] = (string) (Db::table('permission')->where('id', $row->permission_id)->value('code') ?? '');
        }
        return $result;
    }

    public function listing(int $actor, string $resource, array $query): array
    {
        $permission = ['users' => 'user', 'roles' => 'role', 'permissions' => 'permission', 'menus' => 'menu'][$resource] ?? throw new DomainException('资源不存在', 404);
        (new AuthorizationService())->requirePermission($actor, "system:$permission:list");
        $table = self::tableFor($resource);
        foreach (['page', 'pageSize'] as $key) {
            if (array_key_exists($key, $query) && filter_var($query[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new DomainException('分页参数不合法', 422);
            }
        }
        $builder = Db::table($table)->whereNull('deleted_at');
        $page = max(1, min(100000, (int) ($query['page'] ?? 1)));
        $pageSize = max(1, min(100, (int) ($query['pageSize'] ?? 20)));
        $total = $builder->count();
        $rows = $builder->orderBy('id')->offset(($page - 1) * $pageSize)->limit($pageSize)->get();
        return ['list' => $rows->map(fn ($row): array => $this->serialize($resource, $row))->all(), 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }
}
