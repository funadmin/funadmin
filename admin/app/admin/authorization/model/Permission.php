<?php

namespace app\admin\authorization\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;
use app\common\traits\DataFormat;

class Permission extends BaseModel
{
    use LaravelSoftDelete;
    use DataFormat;

    protected $table = 'permission';

    public const TYPE_GROUP = 'group';
    public const TYPE_ROUTE = 'route';
    public const TYPE_CAPABILITY = 'capability';

    public static function childIds(int $id): array
    {
        $result = [];
        $queue = [$id];
        while ($queue) {
            $children = self::whereIn('pid', $queue)->pluck('id')->all();
            $queue = [];
            foreach ($children as $childId) {
                $childId = (int) $childId;
                if (!in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $result;
    }

    public static function validateAttributes(array $data): ?string
    {
        if ($data['name'] === '') {
            return '权限资源名称不能为空';
        }
        if ($data['app_name'] === '' || !preg_match('/^[a-z][a-z0-9_]{0,49}$/', $data['app_name'])) {
            return '应用标识格式不正确';
        }
        if (!in_array($data['resource_type'], [self::TYPE_GROUP, self::TYPE_ROUTE, self::TYPE_CAPABILITY], true)) {
            return '权限资源类型不正确';
        }
        if (in_array($data['resource_type'], [self::TYPE_ROUTE, self::TYPE_CAPABILITY], true)
            && ($data['obj'] === '' || $data['act'] === '')) {
            return '路由或能力资源必须填写资源对象和动作';
        }
        if ($data['resource_type'] === self::TYPE_GROUP && ($data['obj'] !== '' || $data['act'] !== '' || $data['code'] !== null)) {
            return '目录资源不能包含控制器或动作';
        }
        return null;
    }

    /**
     * 权限资源管理树行数据；generated/plugin 来源由后端强制只读。
     */
    public function toApiData(): array
    {
        $object = (string) $this->obj;
        $appNamePrefix = strtolower((string) $this->app_name) . '/';
        if ($object !== '' && str_starts_with(strtolower($object), $appNamePrefix)) {
            $object = substr($object, strlen($appNamePrefix));
        }
        return [
            'id' => (int) $this->id,
            'parentId' => (int) $this->pid,
            'appName' => (string) $this->app_name,
            'code' => (string) ($this->code ?? ''),
            'object' => $object,
            'action' => (string) $this->act,
            'name' => (string) $this->getAttribute('name'),
            'resourceType' => (string) $this->resource_type,
            'status' => (int) $this->status,
            'isPublic' => (int) $this->is_public,
            'sort' => (int) $this->sort_order,
            'sourceType' => (string) $this->source_type,
            'sourceName' => (string) $this->source_name,
            'readOnly' => in_array((string) $this->source_type, ['generated', 'plugin'], true),
            'createdAt' => $this->formatTime($this->created_at),
            'updatedAt' => $this->formatTime($this->updated_at),
        ];
    }

    /**
     * 菜单编辑器权限选项行数据：只暴露绑定所需的稳定字段。
     */
    public function toOptionData(): array
    {
        return [
            'id' => (int) $this->id,
            'parentId' => (int) $this->pid,
            'appName' => (string) $this->app_name,
            'code' => (string) $this->code,
            'object' => (string) $this->obj,
            'action' => (string) $this->act,
            'name' => (string) $this->getAttribute('name'),
            'resourceType' => (string) $this->resource_type,
            'status' => (int) $this->status,
            'isPublic' => (int) $this->is_public,
            'sort' => (int) $this->sort_order,
            'sourceType' => (string) $this->source_type,
            'sourceName' => (string) $this->source_name,
        ];
    }

    /**
     * 前端使用的权限码与后端完全相同（按控制器方法推导的接口权限码与能力权限码），不再做任何对照转换。
     */
    public static function webPermissions(array $permissionCodes, bool $isSuperAdmin): array
    {
        if ($isSuperAdmin) {
            return ['*'];
        }
        $result = [];
        foreach ($permissionCodes as $code) {
            $code = strtolower((string) $code);
            if ($code !== '') {
                $result[$code] = true;
            }
        }
        return array_keys($result);
    }
}
