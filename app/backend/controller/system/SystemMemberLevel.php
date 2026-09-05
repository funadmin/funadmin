<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\Member;
use app\backend\model\MemberLevel;
use app\common\traits\Curd;
use think\Model;

/**
 * Admin Web 会员等级管理。
 */
class SystemMemberLevel extends AdminApiController
{
    use Curd;

    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    protected function crudModelClass(): string
    {
        return MemberLevel::class;
    }

    protected function crudSearchFields(): array
    {
        return ['name' => 'name'];
    }

    protected function crudOrder(): array
    {
        return ['sort_order' => 'asc', 'id' => 'asc'];
    }

    protected function crudPayload(?Model $model = null): array
    {
        return [
            'name' => trim((string) $this->request->post('name', $model?->name ?? '')),
            'amount' => trim((string) $this->request->post('amount', $model?->amount ?? '0')),
            'discount' => (int) $this->request->post('discount', $model?->discount ?? 100),
            'thumb' => trim((string) $this->request->post('thumb', $model?->thumb ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $model?->status ?? 1)),
            'sort_order' => (int) $this->request->post('sort', $model?->sort_order ?? 0),
            'description' => trim((string) $this->request->post('description', $model?->description ?? '')),
        ];
    }

    protected function crudValidate(array &$data, ?Model $model = null): ?string
    {
        $nameLength = function_exists('mb_strlen') ? mb_strlen($data['name']) : strlen($data['name']);
        $descriptionLength = function_exists('mb_strlen') ? mb_strlen($data['description']) : strlen($data['description']);
        if ($data['name'] === '') {
            return '会员等级名称不能为空';
        }
        if ($nameLength > 30) {
            return '会员等级名称不能超过 30 个字符';
        }
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $data['amount'])) {
            return '等级金额必须是 0 至 99999999.99 的数字，最多两位小数';
        }
        $data['amount'] = number_format((float) $data['amount'], 2, '.', '');
        if ($data['discount'] < 0 || $data['discount'] > 100) {
            return '等级折扣必须在 0 至 100 之间';
        }
        if ($data['sort_order'] < 0) {
            return '排序不能小于 0';
        }
        if ($descriptionLength > 200) {
            return '等级描述不能超过 200 个字符';
        }
        if (strlen($data['thumb']) > 255) {
            return '缩略图地址不能超过 255 个字符';
        }
        $duplicate = MemberLevel::withTrashed()->where('name', $data['name']);
        if ($model !== null) {
            $duplicate->where('id', '<>', (int) $model->id);
        }
        return $duplicate->find() ? '会员等级名称已存在' : null;
    }

    protected function crudBeforeDelete(iterable $models, bool $force): ?\think\Response
    {
        $ids = [];
        foreach ($models as $model) {
            $ids[] = (int) $model->id;
        }
        return Member::withTrashed()->whereIn('level_id', $ids)->count() > 0
            ? $this->fail(msg: '会员等级仍被会员引用，不能删除', code: 422)
            : null;
    }

    protected function crudData(Model $model): array
    {
        return [
            'id' => (int) $model->id,
            'name' => (string) $model->name,
            'amount' => number_format((float) $model->amount, 2, '.', ''),
            'discount' => (int) $model->discount,
            'thumb' => (string) ($model->thumb ?? ''),
            'status' => (int) $model->status,
            'sort' => (int) $model->sort_order,
            'description' => (string) ($model->description ?? ''),
            'createdAt' => $this->formatTime($model->created_at),
            'updatedAt' => $this->formatTime($model->updated_at),
            'deletedAt' => $this->formatTime($model->deleted_at),
        ];
    }

    protected function crudResourceName(): string
    {
        return '会员等级';
    }
}
