<?php

declare(strict_types=1);

namespace app\console\controller\system;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\model\AdminMenu;
use app\console\model\CasbinRule;
use app\console\model\Permission;
use app\console\service\RoleScopeService;
use app\console\service\CasbinService;
use app\console\service\PermissionResource;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;
use think\facade\Cache;
use think\facade\Db;
use InvalidArgumentException;

/**
 * Admin Web 权限资源管理。
 */
#[Group('system/permission')]
class SystemPermission extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Get('tree')]
    public function tree(): Response
    {
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        $permissions = Permission::order('sort_order', 'asc')->order('id', 'asc')->select()->all();
        $rows = array_map(
            fn (Permission $permission): array => $this->permissionData($permission),
            $permissions
        );

        $name = trim((string) $this->request->get('name', ''));
        $resource = strtolower(trim((string) $this->request->get('resource', '')));
        $status = $this->request->get('status', null);
        if ($name !== '' || $resource !== '' || ($status !== null && $status !== '')) {
            $rows = $this->filterWithAncestors($rows, $name, $resource, $status);
        }

        return $this->ok(data: $this->buildTree($rows));
    }

    #[Get(':id')]
    #[Pattern('id', '\\d+')]
    public function detail(int $id): Response
    {
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        $permission = Permission::find($id);
        return $permission
            ? $this->ok(data: $this->permissionData($permission))
            : $this->fail(msg: '权限资源不存在', code: 404);
    }

    #[Post('')]
    public function create(): Response
    {
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        try {
            $data = $this->payload();
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        }
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($data['pid'] > 0 && !Permission::find($data['pid'])) {
            return $this->fail(msg: '上级权限资源不存在', code: 422);
        }
        if ($data['code'] !== null && Permission::where('code', $data['code'])->find()) {
            return $this->fail(msg: '权限标识已存在', code: 422);
        }

        $permission = Permission::create($data);
        Cache::clear();
        return $this->ok('创建成功', $this->permissionData($permission));
    }

    #[Put(':id')]
    #[Pattern('id', '\\d+')]
    public function update(int $id): Response
    {
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        $permission = Permission::find($id);
        if (!$permission) {
            return $this->fail(msg: '权限资源不存在', code: 404);
        }
        try {
            $data = $this->payload($permission);
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        }
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($data['pid'] > 0 && !Permission::find($data['pid'])) {
            return $this->fail(msg: '上级权限资源不存在', code: 422);
        }
        if ($data['pid'] === $id || in_array($data['pid'], Permission::childIds($id), true)) {
            return $this->fail(msg: '不能将权限资源移动到自身或下级节点', code: 422);
        }
        if ($data['code'] !== null && Permission::where('code', $data['code'])->where('id', '<>', $id)->find()) {
            return $this->fail(msg: '权限标识已存在', code: 422);
        }

        $oldObj = (string) $permission->obj;
        $oldAct = (string) $permission->act;
        $resourceChanged = $oldObj !== $data['obj'] || $oldAct !== $data['act'];

        Db::transaction(function () use ($permission, $data, $id, $oldObj, $oldAct, $resourceChanged): void {
            $permission->save($data);
            AdminMenu::where('permission_id', $id)->update([
                'app_name' => $data['app_name'],
                'name' => $data['name'],
                'status' => $data['status'],
                'sort_order' => $data['sort_order'],
            ]);
            if ($resourceChanged && $oldObj !== '' && $oldAct !== '') {
                CasbinRule::where('ptype', 'p')->where('v2', $oldObj)->where('v3', $oldAct)->delete();
            }
        });
        if ($resourceChanged) {
            CasbinService::instance()->reload();
        }
        Cache::clear();
        return $this->ok('保存成功', $this->permissionData($permission));
    }

    #[Delete(':id')]
    #[Pattern('id', '\\d+')]
    public function deleteById(int $id): Response
    {
        return $this->delete($id);
    }

    #[Delete('')]
    public function delete(int $id = 0): Response
    {
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的权限资源', code: 422);
        }

        $existingIds = array_map('intval', Permission::whereIn('id', $ids)->column('id'));
        sort($existingIds);
        $requestedIds = $ids;
        sort($requestedIds);
        if ($existingIds !== $requestedIds) {
            return $this->fail(msg: '部分权限资源不存在', code: 404);
        }
        if (Permission::whereIn('pid', $ids)->count() > 0) {
            return $this->fail(msg: '请先删除下级权限资源', code: 422);
        }
        if (AdminMenu::whereIn('permission_id', $ids)->count() > 0) {
            return $this->fail(msg: '权限资源已绑定菜单，不能删除', code: 422);
        }

        $resources = Permission::whereIn('id', $ids)->field('obj,act')->select()->toArray();
        Db::transaction(function () use ($ids, $resources): void {
            foreach ($resources as $resource) {
                if ($resource['obj'] !== '' && $resource['act'] !== '') {
                    CasbinRule::where('ptype', 'p')
                        ->where('v2', $resource['obj'])
                        ->where('v3', $resource['act'])
                        ->delete();
                }
            }
            Permission::whereIn('id', $ids)->delete();
        });
        CasbinService::instance()->reload();
        Cache::clear();
        return $this->ok('删除成功', ['removed' => count($ids)]);
    }

    private function requireSuperAdmin(): ?Response
    {
        return (new RoleScopeService())->isSuperAdmin()
            ? null
            : $this->fail(msg: '仅超级管理员可维护权限资源', code: 403);
    }

    private function payload(?Permission $permission = null): array
    {
        $current = $permission ? $this->permissionData($permission) : [];
        $appName = strtolower(trim((string) $this->request->post('appName', $current['appName'] ?? 'console')));
        $resourceType = strtolower(trim((string) $this->request->post('resourceType', $current['resourceType'] ?? Permission::TYPE_ROUTE)));
        $objInput = trim((string) $this->request->post('object', $current['object'] ?? ''));
        $appNamePrefixPattern = '/^' . preg_quote($appName, '/') . '[\/.]/i';
        $objInput = preg_replace($appNamePrefixPattern, '', $objInput) ?? $objInput;
        $actionInput = trim((string) $this->request->post('action', $current['action'] ?? ''));

        $obj = '';
        $act = '';
        $code = null;
        if ($resourceType === Permission::TYPE_ROUTE && $objInput !== '' && $actionInput !== '') {
            $resource = PermissionResource::fromParts($appName, $objInput, $actionInput);
            $obj = $resource['obj'];
            $act = $resource['act'];
            $code = $resource['code'];
        }

        return [
            'pid' => max(0, (int) $this->request->post('parentId', $current['parentId'] ?? 0)),
            'app_name' => $appName,
            'code' => $code,
            'obj' => $obj,
            'act' => $act,
            'name' => trim((string) $this->request->post('name', $current['name'] ?? '')),
            'resource_type' => $resourceType,
            'status' => $this->binaryStatus($this->request->post('status', $current['status'] ?? 1)),
            'is_public' => $this->binaryStatus($this->request->post('isPublic', $current['isPublic'] ?? 0)),
            'sort_order' => max(0, (int) $this->request->post('sort', $current['sort'] ?? 999)),
            'source_type' => (string) ($permission->source_type ?? 'manual'),
            'source_name' => (string) ($permission->source_name ?? ''),
        ];
    }

    private function validatePayload(array $data): ?string
    {
        if ($data['name'] === '') {
            return '权限资源名称不能为空';
        }
        if ($data['app_name'] === '' || !preg_match('/^[a-z][a-z0-9_]{0,49}$/', $data['app_name'])) {
            return '应用标识格式不正确';
        }
        if (!in_array($data['resource_type'], [Permission::TYPE_GROUP, Permission::TYPE_ROUTE], true)) {
            return '权限资源类型不正确';
        }
        if ($data['resource_type'] === Permission::TYPE_ROUTE && ($data['obj'] === '' || $data['act'] === '')) {
            return '路由资源必须填写控制器和动作';
        }
        if ($data['resource_type'] === Permission::TYPE_GROUP && ($data['obj'] !== '' || $data['act'] !== '' || $data['code'] !== null)) {
            return '目录资源不能包含控制器或动作';
        }
        return null;
    }

    private function filterWithAncestors(array $rows, string $name, string $resource, $status): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $keep = [];
        foreach ($rows as $row) {
            $matchesTitle = $name === '' || str_contains((string) $row['name'], $name);
            $resourceText = strtolower(implode(' ', [$row['code'], $row['object'], $row['action']]));
            $matchesResource = $resource === '' || str_contains($resourceText, $resource);
            $matchesStatus = ($status === null || $status === '') || (int) $row['status'] === (int) $status;
            if (!$matchesTitle || !$matchesResource || !$matchesStatus) {
                continue;
            }

            $currentId = (int) $row['id'];
            while ($currentId > 0 && isset($byId[$currentId]) && !isset($keep[$currentId])) {
                $keep[$currentId] = true;
                $currentId = (int) $byId[$currentId]['parentId'];
            }
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => isset($keep[(int) $row['id']])
        ));
    }

    private function permissionData(Permission $permission): array
    {
        $object = (string) $permission->obj;
        $appNamePrefix = strtolower((string) $permission->app_name) . '/';
        if ($object !== '' && str_starts_with(strtolower($object), $appNamePrefix)) {
            $object = substr($object, strlen($appNamePrefix));
        }
        return [
            'id' => (int) $permission->id,
            'parentId' => (int) $permission->pid,
            'appName' => (string) $permission->app_name,
            'code' => (string) ($permission->code ?? ''),
            'object' => $object,
            'action' => (string) $permission->act,
            'name' => (string) $permission->name,
            'resourceType' => (string) $permission->resource_type,
            'status' => (int) $permission->status,
            'isPublic' => (int) $permission->is_public,
            'sort' => (int) $permission->sort_order,
            'sourceType' => (string) $permission->source_type,
            'sourceName' => (string) $permission->source_name,
            'createdAt' => $this->formatTime($permission->created_at),
            'updatedAt' => $this->formatTime($permission->updated_at),
        ];
    }
}
