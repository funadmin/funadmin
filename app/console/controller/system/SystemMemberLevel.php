<?php

declare(strict_types=1);

namespace app\console\controller\system;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\model\Member;
use app\console\model\MemberLevel;
use app\common\traits\Crud;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Model;
use think\Response;

/**
 * Admin Web 会员等级管理。
 */
#[Group('system/member-level')]
class SystemMemberLevel extends AdminApiController
{
    use Crud {
        index as private crudIndex;
        detail as private crudDetail;
        create as private crudCreate;
        update as private crudUpdate;
        status as private crudStatus;
        recycle as private crudRecycle;
        restore as private crudRestore;
        destroy as private crudDestroy;
        import as private crudImport;
        export as private crudExport;
    }

    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];
    protected string $model = MemberLevel::class;

    #[Get('export')]
    public function export(): Response
    {
        return $this->crudExport();
    }

    #[Post('import')]
    public function import(): Response
    {
        return $this->crudImport();
    }

    #[Get('')]
    public function index(): Response
    {
        return $this->crudIndex();
    }

    #[Get(':id')]
    #[Pattern('id', '\\d+')]
    public function detail(int $id): Response
    {
        return $this->crudDetail($id);
    }

    #[Post('')]
    public function create(): Response
    {
        return $this->crudCreate();
    }

    #[Put(':id')]
    #[Pattern('id', '\\d+')]
    public function update(int $id): Response
    {
        return $this->crudUpdate($id);
    }

    #[Post(':id/status')]
    #[Pattern('id', '\\d+')]
    public function status(int $id): Response
    {
        return $this->crudStatus($id);
    }

    #[Delete('')]
    public function recycle(): Response
    {
        return $this->crudRecycle();
    }

    #[Post('restore')]
    public function restore(): Response
    {
        return $this->crudRestore();
    }

    #[Delete('destroy')]
    public function destroy(): Response
    {
        return $this->crudDestroy();
    }

    protected function searchFields(): array
    {
        return ['name' => 'name'];
    }

    protected function order(): array
    {
        return ['sort_order' => 'asc', 'id' => 'asc'];
    }

    protected function sortFields(): array
    {
        return ['id' => 'id', 'name' => 'name', 'amount' => 'amount', 'discount' => 'discount', 'sort' => 'sort_order', 'status' => 'status', 'createdAt' => 'created_at'];
    }

    protected function rangeFilters(): array
    {
        return ['amountRange' => 'amount', 'discountRange' => 'discount', 'createdAtRange' => 'created_at'];
    }

    protected function importFields(): array
    {
        return [
            'name' => 'name',
            'amount' => 'amount',
            'discount' => 'discount',
            'thumb' => 'thumb',
            'status' => 'status',
            'sort' => 'sort_order',
            'description' => 'description',
        ];
    }

    protected function exportFields(): array
    {
        return ['id', 'name', 'amount', 'discount', 'thumb', 'status', 'sort', 'description', 'createdAt', 'deletedAt'];
    }

    protected function importPayload(array $row): array
    {
        $data = $this->mapImportRow($row);
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['amount'] = trim((string) ($data['amount'] ?? '0'));
        $data['discount'] = (int) ($data['discount'] ?? 100);
        $data['thumb'] = trim((string) ($data['thumb'] ?? ''));
        $data['status'] = $this->binaryStatus($data['status'] ?? 1);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['description'] = trim((string) ($data['description'] ?? ''));
        return $data;
    }

    protected function payload(?Model $model = null): array
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

    protected function validatePayload(array &$data, ?Model $model = null): ?string
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

    protected function beforeDelete(iterable $models, bool $force): ?\think\Response
    {
        $ids = [];
        foreach ($models as $model) {
            $ids[] = (int) $model->id;
        }
        return Member::withTrashed()->whereIn('level_id', $ids)->count() > 0
            ? $this->fail(msg: '会员等级仍被会员引用，不能删除', code: 422)
            : null;
    }

    protected function transformData(Model $model): array
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

    protected function resourceName(): string
    {
        return '会员等级';
    }
}
