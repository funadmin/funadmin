<?php

namespace app\admin\authorization\model;

use app\admin\development\model\BusinessModule;
use app\admin\development\model\CrudGeneration;
use app\admin\form\model\FormSchemaVersion;
use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;
use app\common\traits\DataFormat;

/**
 * 后台菜单只负责导航展示，授权资源由 Permission 独立维护。
 */
class AdminMenu extends BaseModel
{
    use LaravelSoftDelete;
    use DataFormat;

    protected $table = 'admin_menu';

    /**
     * 运行时下发与菜单管理共用查询边界：仅 Admin Web 应用的三种菜单来源。
     */
    public static function managedQuery()
    {
        return self::whereIn('source_type', ['admin_web', 'generated', 'plugin'])->where('app_name', 'admin');
    }

    public static function findManaged(int $id): ?AdminMenu
    {
        return self::managedQuery()->where('id', $id)->first();
    }

    public static function descendantIds(int $id): array
    {
        $result = [];
        $queue = [$id];
        while ($queue) {
            $children = self::managedQuery()->whereIn('pid', $queue)->pluck('id')->all();
            $queue = [];
            foreach (array_map('intval', $children) as $childId) {
                if (!in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $result;
    }

    /**
     * 生成来源只有业务模块或发布快照在运行时已失效、且无生成或恢复中事务时，才视为可安全清理的孤儿。
     */
    public static function isOrphanedGeneratedSource(string $sourceType, string $sourceName): bool
    {
        if ($sourceType !== 'generated' || $sourceName === '') return false;
        $code = str_replace('-', '_', $sourceName);
        $module = BusinessModule::withTrashed()->where('code', $code)->first();
        if (!$module) return true;
        if (!$module->trashed()) {
            $publishedVersion = (int) ($module->published_schema_version ?? 0);
            $publishedHash = trim((string) ($module->published_schema_hash ?? ''));
            if (in_array((string) $module->lifecycle_status, ['published', 'dynamic_published'], true) && $publishedVersion > 0 && $publishedHash !== '') {
                return !self::hasPendingGeneration((int) $module->id)
                    && FormSchemaVersion::where('form_id', (int) $module->form_id)
                        ->where('version', $publishedVersion)
                        ->where('schema_hash', $publishedHash)
                        ->first() === null;
            }
            return false;
        }
        return !self::hasPendingGeneration((int) $module->id);
    }

    private static function hasPendingGeneration(int $moduleId): bool
    {
        return CrudGeneration::where('business_module_id', $moduleId)
            ->where(function ($query): void {
                $query->where('status', 'running')->orWhereIn('recovery_status', ['recovering', 'recovery_required']);
            })
            ->first() !== null;
    }

    public static function validateAttributes(array $data): ?string
    {
        parse_str((string) $data['query'], $meta);
        $path = rtrim(strtolower((string) $data['href']), '/');
        $reservedPaths = ['/admin', '/admin/login', '/admin/install', '/admin/dashboard', '/admin/profile', '/admin/redirect', '/admin/403', '/admin/404', '/admin/500', '/login', '/install'];
        $reservedNames = ['login', 'install', 'dashboard', 'profile', 'redirect', 'forbidden', 'servererror', 'notfound', 'bootstrapnotfound', 'publichome'];
        if (in_array($path, $reservedPaths, true) || in_array(strtolower((string) ($meta['name'] ?? '')), $reservedNames, true)) {
            return '不能覆盖系统保留路由或路由名称';
        }
        if (($data['name'] ?? '') === '' || mb_strlen((string) $data['name']) > 100) {
            return '菜单名称不能为空且不能超过 100 个字符';
        }
        if (!in_array($meta['type'] ?? '', ['M', 'C'], true)) {
            return '菜单类型只允许目录或页面';
        }
        if (!preg_match('#^(?:/[A-Za-z0-9_/-]+|[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*)$#', (string) $data['href'])) {
            return '一级菜单路由必须使用站内绝对路径，子菜单可使用相对路径';
        }
        if ((int) $data['pid'] === 0 && !str_starts_with((string) $data['href'], '/')) {
            return '一级菜单路由必须使用站内绝对路径';
        }
        if (($meta['type'] ?? '') === 'C') {
            if (!preg_match('#^[A-Za-z0-9_/-]+$#', (string) ($meta['component'] ?? ''))) {
                return '页面组件路径不合法';
            }
            if ((int) $data['permission_id'] <= 0) {
                return '页面菜单必须绑定有效权限资源';
            }
        }
        return null;
    }

    /**
     * permission_id 是关系事实源，query.permission 是供前端路由消费的同步投影。
     */
    public static function buildPermissionQuery(string $query, string $permissionCode): string
    {
        parse_str($query, $parameters);
        if ($permissionCode === '') {
            unset($parameters['permission']);
        } else {
            $parameters['permission'] = $permissionCode;
        }
        return http_build_query($parameters);
    }

    /**
     * 菜单管理树行数据：带受管与孤儿标记，供前端决定清理入口。
     */
    public function toManagementData(): array
    {
        parse_str((string) $this->query, $meta);
        $permission = $this->permission_id > 0 ? Permission::find((int) $this->permission_id) : null;
        $orphaned = self::isOrphanedGeneratedSource((string) $this->source_type, (string) $this->source_name);
        return [
            'id' => (int) $this->id,
            'sourceType' => (string) $this->source_type,
            'sourceName' => (string) $this->source_name,
            'readOnly' => in_array((string) $this->source_type, ['generated', 'plugin'], true),
            'orphaned' => $orphaned,
            'removable' => (string) $this->source_type === 'admin_web' || $orphaned,
            'parentId' => (int) $this->pid,
            'routeName' => (string) ($meta['name'] ?? ('Menu_' . (int) $this->id)),
            'path' => (string) $this->href,
            'component' => (string) ($meta['component'] ?? ''),
            'redirect' => (string) ($meta['redirect'] ?? ''),
            'type' => in_array(($meta['type'] ?? ''), ['M', 'C'], true) ? (string) $meta['type'] : 'C',
            'icon' => (string) $this->icon,
            'name' => (string) $this->getAttribute('name'),
            'sort' => (int) $this->sort_order,
            'hidden' => $this->booleanValue($meta['hidden'] ?? false),
            'keepAlive' => $this->booleanValue($meta['keepAlive'] ?? false),
            'affix' => $this->booleanValue($meta['affix'] ?? false),
            'permissionId' => (int) ($permission->id ?? 0),
            'permission' => (string) ($permission->code ?? ''),
        ];
    }

    /**
     * 登录下发菜单路由元数据；未授权菜单保留元数据并标记 hidden，交由前端守卫返回 403。
     */
    public function toWebMenuData(bool $isAllowed = true): array
    {
        parse_str((string) $this->query, $meta);
        $permission = $this->permission_id > 0 ? Permission::find((int) $this->permission_id) : null;
        return [
            'id' => (int) $this->id,
            'parentId' => (int) $this->pid,
            'routeName' => (string) ($meta['name'] ?? ('Menu_' . (int) $this->id)),
            'path' => $this->webPath(),
            'component' => (string) ($meta['component'] ?? ''),
            'redirect' => (string) ($meta['redirect'] ?? ''),
            'type' => in_array(($meta['type'] ?? ''), ['M', 'C'], true) ? (string) $meta['type'] : 'C',
            'icon' => (string) $this->icon,
            'name' => (string) $this->getAttribute('name'),
            'sort' => (int) $this->sort_order,
            'hidden' => !$isAllowed || filter_var($meta['hidden'] ?? false, FILTER_VALIDATE_BOOL),
            'keepAlive' => filter_var($meta['keepAlive'] ?? false, FILTER_VALIDATE_BOOL),
            'affix' => filter_var($meta['affix'] ?? false, FILTER_VALIDATE_BOOL),
            'permission' => (string) ($permission->code ?? ''),
            'formKey' => (string) ($meta['formKey'] ?? ''),
        ];
    }

    public function webPath(): string
    {
        $href = (string) $this->href;
        return (int) $this->pid === 0 ? '/' . ltrim($href, '/') : $href;
    }
}
