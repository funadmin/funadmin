<?php

declare(strict_types=1);

namespace app\console\controller\system;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\model\MemberGroup;
use app\console\model\MemberGroupRelation;
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
 * Admin Web 会员组管理。
 */
#[Group('system/member-group')]
class SystemMemberGroup extends AdminApiController
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

    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];
    protected string $model = MemberGroup::class;

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

    protected function sortFields(): array
    {
        return ['id' => 'id', 'name' => 'name', 'status' => 'status', 'createdAt' => 'created_at'];
    }

    protected function importFields(): array
    {
        return ['name' => 'name', 'icon' => 'icon', 'status' => 'status'];
    }

    protected function exportFields(): array
    {
        return ['id', 'name', 'icon', 'status', 'isDefault', 'createdAt', 'deletedAt'];
    }

    protected function importPayload(array $row): array
    {
        $data = $this->mapImportRow($row);
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['icon'] = trim((string) ($data['icon'] ?? ''));
        $data['status'] = $this->binaryStatus($data['status'] ?? 1);
        return $data;
    }

    protected function payload(?Model $model = null): array
    {
        return [
            'name' => trim((string) $this->request->post('name', $model?->name ?? '')),
            'icon' => trim((string) $this->request->post('icon', $model?->icon ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $model?->status ?? 1)),
        ];
    }

    protected function validatePayload(array &$data, ?Model $model = null): ?string
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

    protected function beforeDelete(iterable $models, bool $force): ?\think\Response
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

    protected function transformData(Model $model): array
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

    protected function resourceName(): string
    {
        return '会员组';
    }
}
