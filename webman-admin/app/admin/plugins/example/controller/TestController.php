<?php

declare(strict_types=1);

namespace app\admin\plugins\example\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\plugins\example\model\Test;
use app\admin\authorization\service\DataScopeService;
use app\admin\plugins\example\service\TestService;
use app\admin\plugins\example\validate\TestValidate;
use app\common\traits\Crud;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup as Group;
use support\annotation\route\Post;
use support\annotation\route\Put;
use Illuminate\Database\Eloquent\Model;
use support\Response;
use app\common\plugin\sdk\PluginPermission;

#[Group('/admin/plugin/example/test')]
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
    protected string $model = Test::class;

    #[Get('')]
    #[PluginPermission('example:test:list')]
    public function index(): Response { return $this->crudIndex(); }

    #[Get('/{id:\d+}')]
    #[PluginPermission('example:test:detail')]
    public function detail(int|string $id): Response { return $this->crudDetail($id); }

    #[Post('')]
    #[PluginPermission('example:test:create')]
    public function create(): Response { return $this->crudCreate(); }

    #[Put('/{id:\d+}')]
    #[PluginPermission('example:test:update')]
    public function update(int|string $id): Response { return $this->crudUpdate($id); }

    #[Delete('/{id:\d+}')]
    #[PluginPermission('example:test:delete')]
    public function remove(int|string $id): Response { return $this->crudRemove($id); }

    #[Post('/{id:\d+}/restore')]
    #[PluginPermission('example:test:restore')]
    public function restore(int|string $id): Response { return $this->crudRestoreOne($id); }

    #[Delete('/{id:\d+}/destroy')]
    #[PluginPermission('example:test:destroy')]
    public function destroy(int|string $id): Response { return $this->crudDestroyOne($id); }

    #[Delete('')]
    #[PluginPermission('example:test:batch-delete')]
    public function recycle(): Response { return $this->crudRecycle(); }

    #[Post('/restore')]
    #[PluginPermission('example:test:batch-restore')]
    public function restoreMany(): Response { return $this->crudRestoreMany(); }

    #[Delete('/destroy')]
    #[PluginPermission('example:test:batch-destroy')]
    public function destroyMany(): Response { return $this->crudDestroyMany(); }

    #[Post('/import')]
    #[PluginPermission('example:test:import')]
    public function import(): Response { return $this->crudImport(); }

    #[Get('/export')]
    #[PluginPermission('example:test:export')]
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
  4 => 'createdAt',
  5 => 'updatedAt',
  6 => 'deletedAt',
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
        if ($model !== null) $validate->forUpdate($model->id, $data);
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
            'field3' => (string) ($model->field_3 ?? ''),
            'createdAt' => (string) ($model->created_at ?? ''),
            'updatedAt' => (string) ($model->updated_at ?? ''),
            'deletedAt' => (string) ($model->deleted_at ?? ''),
        ];
    }
    protected function resourceName(): string { return 'test'; }
}
