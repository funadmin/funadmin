<?php

declare(strict_types=1);

namespace app\backend\controller;

use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\AdminMenu;
use app\backend\model\CasbinRule;
use app\backend\model\Permission;
use app\backend\service\AuthService;
use app\backend\service\CasbinService;
use app\backend\service\PermissionResource;
use think\Response;
use think\facade\Cache;
use think\facade\Db;
use InvalidArgumentException;

/**
 * Admin Web 权限资源管理。
 */
class SystemPermission extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function tree(): Response
    {
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        $permissions = Permission::order('sort', 'asc')->order('id', 'asc')->select()->all();
        $rows = array_map(
            fn (Permission $permission): array => $this->permissionData($permission),
            $permissions
        );

        $title = trim((string) $this->request->get('title', ''));
        $resource = strtolower(trim((string) $this->request->get('resource', '')));
        $status = $this->request->get('status', null);
        if ($title !== '' || $resource !== '' || ($status !== null && $status !== '')) {
            $rows = $this->filterWithAncestors($rows, $title, $resource, $status);
        }

        return $this->ok($this->buildTree($rows));
    }

    public function detail(int $id): Response
    {
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        $permission = Permission::find($id);
        return $permission
            ? $this->ok($this->permissionData($permission))
            : $this->fail('权限资源不存在', 404);
    }

    public function create(): Response
    {
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        try {
            $data = $this->payload();
        } catch (InvalidArgumentException $exception) {
            return $this->fail($exception->getMessage(), 422);
        }
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        if ($data['pid'] > 0 && !Permission::find($data['pid'])) {
            return $this->fail('上级权限资源不存在', 422);
        }
        if ($data['code'] !== null && Permission::where('code', $data['code'])->find()) {
            return $this->fail('权限标识已存在', 422);
        }

        $permission = Permission::create($data);
        Cache::clear();
        return $this->ok($this->permissionData($permission), '创建成功');
    }

    public function update(int $id): Response
    {
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        $permission = Permission::find($id);
        if (!$permission) {
            return $this->fail('权限资源不存在', 404);
        }
        try {
            $data = $this->payload($permission);
        } catch (InvalidArgumentException $exception) {
            return $this->fail($exception->getMessage(), 422);
        }
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        if ($data['pid'] > 0 && !Permission::find($data['pid'])) {
            return $this->fail('上级权限资源不存在', 422);
        }
        if ($data['pid'] === $id || in_array($data['pid'], Permission::childIds($id), true)) {
            return $this->fail('不能将权限资源移动到自身或下级节点', 422);
        }
        if ($data['code'] !== null && Permission::where('code', $data['code'])->where('id', '<>', $id)->find()) {
            return $this->fail('权限标识已存在', 422);
        }

        $oldObj = (string) $permission->obj;
        $oldAct = (string) $permission->act;
        $resourceChanged = $oldObj !== $data['obj'] || $oldAct !== $data['act'];

        Db::transaction(function () use ($permission, $data, $id, $oldObj, $oldAct, $resourceChanged): void {
            $permission->save($data);
            AdminMenu::where('permission_id', $id)->update([
                'module' => $data['module'],
                'title' => $data['title'],
                'status' => $data['status'],
                'sort' => $data['sort'],
            ]);
            if ($resourceChanged && $oldObj !== '' && $oldAct !== '') {
                CasbinRule::where('ptype', 'p')->where('v2', $oldObj)->where('v3', $oldAct)->delete();
            }
        });
        if ($resourceChanged) {
            CasbinService::instance()->reload();
        }
        Cache::clear();
        return $this->ok($this->permissionData($permission), '保存成功');
    }

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
            return $this->fail('请选择要删除的权限资源', 422);
        }

        $existingIds = array_map('intval', Permission::whereIn('id', $ids)->column('id'));
        sort($existingIds);
        $requestedIds = $ids;
        sort($requestedIds);
        if ($existingIds !== $requestedIds) {
            return $this->fail('部分权限资源不存在', 404);
        }
        if (Permission::whereIn('pid', $ids)->count() > 0) {
            return $this->fail('请先删除下级权限资源', 422);
        }
        if (AdminMenu::whereIn('permission_id', $ids)->count() > 0) {
            return $this->fail('权限资源已绑定菜单，不能删除', 422);
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
        return $this->ok(['removed' => count($ids)], '删除成功');
    }

    private function requireSuperAdmin(): ?Response
    {
        return AuthService::instance()->isSuperAdmin()
            ? null
            : $this->fail('仅超级管理员可维护权限资源', 403);
    }

    private function payload(?Permission $permission = null): array
    {
        $current = $permission ? $this->permissionData($permission) : [];
        $module = strtolower(trim((string) $this->request->post('module', $current['module'] ?? 'backend')));
        $resourceType = strtolower(trim((string) $this->request->post('resourceType', $current['resourceType'] ?? Permission::TYPE_ROUTE)));
        $objInput = trim((string) $this->request->post('object', $current['object'] ?? ''));
        $modulePrefixPattern = '/^' . preg_quote($module, '/') . '[\/.]/i';
        $objInput = preg_replace($modulePrefixPattern, '', $objInput) ?? $objInput;
        $actionInput = trim((string) $this->request->post('action', $current['action'] ?? ''));

        $obj = '';
        $act = '';
        $code = null;
        if ($resourceType === Permission::TYPE_ROUTE && $objInput !== '' && $actionInput !== '') {
            $resource = PermissionResource::fromParts($module, $objInput, $actionInput);
            $obj = $resource['obj'];
            $act = $resource['act'];
            $code = $resource['code'];
        }

        return [
            'pid' => max(0, (int) $this->request->post('parentId', $current['parentId'] ?? 0)),
            'module' => $module,
            'code' => $code,
            'obj' => $obj,
            'act' => $act,
            'title' => trim((string) $this->request->post('title', $current['title'] ?? '')),
            'resource_type' => $resourceType,
            'status' => (int) $this->request->post('status', $current['status'] ?? 1) === 1 ? 1 : 0,
            'is_public' => (int) $this->request->post('isPublic', $current['isPublic'] ?? 0) === 1 ? 1 : 0,
            'sort' => max(0, (int) $this->request->post('sort', $current['sort'] ?? 999)),
            'source_type' => (string) ($permission->source_type ?? 'manual'),
            'source_name' => (string) ($permission->source_name ?? ''),
        ];
    }

    private function validatePayload(array $data): ?string
    {
        if ($data['title'] === '') {
            return '权限资源名称不能为空';
        }
        if ($data['module'] === '' || !preg_match('/^[a-z][a-z0-9_]{0,49}$/', $data['module'])) {
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

    private function filterWithAncestors(array $rows, string $title, string $resource, $status): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $keep = [];
        foreach ($rows as $row) {
            $matchesTitle = $title === '' || str_contains((string) $row['title'], $title);
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
        $modulePrefix = strtolower((string) $permission->module) . '/';
        if ($object !== '' && str_starts_with(strtolower($object), $modulePrefix)) {
            $object = substr($object, strlen($modulePrefix));
        }
        return [
            'id' => (int) $permission->id,
            'parentId' => (int) $permission->pid,
            'module' => (string) $permission->module,
            'code' => (string) ($permission->code ?? ''),
            'object' => $object,
            'action' => (string) $permission->act,
            'title' => (string) $permission->title,
            'resourceType' => (string) $permission->resource_type,
            'status' => (int) $permission->status,
            'isPublic' => (int) $permission->is_public,
            'sort' => (int) $permission->sort,
            'sourceType' => (string) $permission->source_type,
            'sourceName' => (string) $permission->source_name,
            'createdAt' => $this->formatTime($permission->create_time),
            'updatedAt' => $this->formatTime($permission->update_time),
        ];
    }
}
