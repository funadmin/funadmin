<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\MemberGroup;
use app\backend\model\MemberGroupRelation;
use app\common\traits\Crud;
use think\Model;

/**
 * Admin Web 会员组管理。
 */
class SystemMemberGroup extends AdminApiController
{
    use Crud;

    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];
    protected string $model = MemberGroup::class;

    protected function crudSearchFields(): array
    {
        return ['name' => 'name'];
    }

    protected function crudPayload(?Model $model = null): array
    {
        return [
            'name' => trim((string) $this->request->post('name', $model?->name ?? '')),
            'icon' => trim((string) $this->request->post('icon', $model?->icon ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $model?->status ?? 1)),
        ];
    }

    protected function crudValidate(array &$data, ?Model $model = null): ?string
    {
        $nameLength = function_exists('mb_strlen') ? mb_strlen($data['name']) : strlen($data['name']);
        if ($data['name'] === '') {
            return '会员组名称不能为空';
        }
        if ($nameLength > 50) {
            return '会员组名称不能超过 50 个字符';
        }
        $iconLength = function_exists('mb_strlen') ? mb_strlen($data['icon']) : strlen($data['icon']);
        if ($iconLength > 50) {
            return '会员组图标不能超过 50 个字符';
        }
        $duplicate = MemberGroup::withTrashed()->where('name', $data['name']);
        if ($model !== null) {
            $duplicate->where('id', '<>', (int) $model->id);
        }
        return $duplicate->find() ? '会员组名称已存在' : null;
    }

    protected function crudBeforeDelete(iterable $models, bool $force): ?\think\Response
    {
        $ids = [];
        foreach ($models as $model) {
            $ids[] = (int) $model->id;
        }
        if (in_array(1, $ids, true)) {
            return $this->fail(msg: '默认会员组不能删除', code: 422);
        }
        if (MemberGroupRelation::whereIn('group_id', $ids)->count() > 0) {
            return $this->fail(msg: '会员组仍被会员引用，不能删除', code: 422);
        }
        return null;
    }

    protected function crudData(Model $model): array
    {
        return [
            'id' => (int) $model->id,
            'name' => (string) $model->name,
            'icon' => (string) ($model->icon ?? ''),
            'status' => (int) $model->status,
            'isDefault' => (int) $model->id === 1 ? 1 : 0,
            'createdAt' => $this->formatTime($model->created_at),
            'updatedAt' => $this->formatTime($model->updated_at),
            'deletedAt' => $this->formatTime($model->deleted_at),
        ];
    }

    protected function crudResourceName(): string
    {
        return '会员组';
    }
}
