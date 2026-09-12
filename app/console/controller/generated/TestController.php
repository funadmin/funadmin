<?php

declare(strict_types=1);

namespace app\console\controller\generated;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\model\Test;
use app\console\authorization\service\DataScopeService;
use app\console\service\TestService;
use app\console\validate\TestValidate;
use app\common\traits\Crud;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Model;
use think\Response;

#[Group('generated/test')]
final class TestController extends AdminApiController
{
    use Crud {
        index as private crudIndex; index as private;
        detail as private crudDetail; detail as private;
        create as private crudCreate; create as private;
        update as private crudUpdate; update as private;
        remove as private crudRemove; remove as private;
        restoreOne as private crudRestoreOne; restoreOne as private;
        destroyOne as private crudDestroyOne; destroyOne as private;
        recycle as private crudRecycle; recycle as private;
        restore as private crudRestoreMany; restore as private;
        destroy as private crudDestroyMany; destroy as private;
        import as private crudImport; import as private;
        export as private crudExport; export as private;
        baseQuery as private crudUnscopedBaseQuery;
    }
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];
    protected string $model = Test::class;

    #[Get('')]
    public function index(): Response { return $this->crudIndex(); }

    #[Get(':id')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function detail(int|string $id): Response { return $this->crudDetail($id); }

    #[Post('')]
    public function create(): Response { return $this->crudCreate(); }

    #[Put(':id')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function update(int|string $id): Response { return $this->crudUpdate($id); }

    #[Delete(':id')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function remove(int|string $id): Response { return $this->crudRemove($id); }

    #[Post(':id/restore')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function restore(int|string $id): Response { return $this->crudRestoreOne($id); }

    #[Delete(':id/destroy')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function destroy(int|string $id): Response { return $this->crudDestroyOne($id); }

    #[Delete('')]
    public function recycle(): Response { return $this->crudRecycle(); }

    #[Post('restore')]
    public function restoreMany(): Response { return $this->crudRestoreMany(); }

    #[Delete('destroy')]
    public function destroyMany(): Response { return $this->crudDestroyMany(); }

    #[Post('import')]
    public function import(): Response { return $this->crudImport(); }

    #[Get('export')]
    public function export(): Response { return $this->crudExport(); }

    protected function searchFields(): array { return array (
); }
    protected function exactFilters(): array { return array (
); }
    protected function rangeFilters(): array { return array (
); }
    protected function operatorFilters(): array { return array (
); }
    protected function sortFields(): array { return array (
  'id' => 'id',
); }
    protected function primaryKey(): string { return 'id'; }
    protected function primaryKeyType(): string { return 'integer'; }
    protected function primaryKeyPattern(): ?string { return NULL; }
    protected function usesSoftDeletes(): bool { return true; }
    protected function importFields(): array { return array_combine(TestService::WRITABLE_FIELDS, TestService::WRITABLE_FIELDS); }
    protected function importPayload(array $row): array { return (new TestService())->prepareCreatePayload($this->mapImportRow($row)); }
    protected function exportFields(): array { return array (
  0 => 'id',
  1 => 'field1',
  2 => 'field2',
  3 => 'field3',
  4 => 'field4',
  5 => 'field5',
  6 => 'field6',
  7 => 'field7',
  8 => 'field8',
  9 => 'createdAt',
  10 => 'updatedAt',
  11 => 'deletedAt',
); }
    protected function importLimit(): int { return 10000; }
    protected function exportLimit(): int { return 10000; }
    protected function payload(?Model $model = null): array
    {
        $payload = array_intersect_key($this->request->post(), array_flip(TestService::WRITABLE_FIELDS));
        return $model === null ? (new TestService())->prepareCreatePayload($payload) : $payload;
    }
    protected function validatePayload(array &$data, ?Model $model = null): ?string
    {
        $validate = new TestValidate();
        if ($model !== null) $validate->forUpdate($model->id);
        return $validate->check($data) ? null : $validate->getError();
    }
    protected function beforeDelete(iterable $models, bool $force): ?Response
    {
        $error = (new TestService())->assertNotReferenced($models, $force);
        return $error === null ? null : $this->fail(msg: $error, code: 422);
    }
    protected function transformData(Model $model): array
    {
        return [
            'id' => (int) $model->id,
            'field1' => (string) ($model->field_1 ?? ''),
            'field2' => (string) ($model->field_2 ?? ''),
            'field3' => (int) $model->field_3,
            'field4' => (int) $model->field_4,
            'field5' => (string) ($model->field_5 ?? ''),
            'field6' => (int) $model->field_6,
            'field7' => (string) ($model->field_7 ?? ''),
            'field8' => (int) $model->field_8,
            'createdAt' => (string) ($model->created_at ?? ''),
            'updatedAt' => (string) ($model->updated_at ?? ''),
            'deletedAt' => (string) ($model->deleted_at ?? ''),
        ];
    }
    protected function resourceName(): string { return 'test'; }
}

