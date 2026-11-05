<?php

declare(strict_types=1);

namespace app\admin\controller\generated;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckAdminApiRole;
use app\admin\middleware\SystemLog;
use app\admin\model\generated\I18nDemo;
use app\admin\service\DataScopeService;
use app\admin\service\generated\I18nDemoService;
use app\admin\validate\generated\I18nDemoValidate;
use app\common\traits\Crud;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Model;
use think\Response;

#[Group('generated/i18n-demo')]
final class I18nDemoController extends AdminApiController
{
    use Crud {
        index as private crudIndex; index as private;
        detail as private crudDetail; detail as private;
        create as private crudCreate; create as private;
        update as private crudUpdate; update as private;
        status as private crudStatus; status as private;
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
    protected string $model = I18nDemo::class;

    private function listButtonService(): \app\admin\form\service\FormDataService
    {
        return new \app\admin\form\service\FormDataService(permissionChecker: fn (string $route): bool => (new \app\admin\authorization\service\AdminAuthorizationService())->nodeAccess($route), productionBinding: array (
  'formKey' => 'i18n_demo',
  'schemaHash' => '2f24886b74ff4ee2fbcc3070bb427de0d586d0e53ab53896608b8435c2a6cf01',
  'route' => 'admin/generated.i18ndemocontroller',
  'table' => 'fun_i18n_demo',
  'connection' => 'mysql',
));
    }
    #[Get('list-actions')]
    public function listActions(): Response
    {
        return $this->listButtonResponse(fn (): array => $this->listButtonService()->listActionCatalog('i18n_demo', (string) $this->request->get('location', 'row'), \app\common\form\registry\FormRegistryFactory::production()->actions()));
    }
    #[Post('list-action')]
    public function listAction(): Response
    {
        $payload = $this->request->post();
        $this->request->withPost(['buttonId' => $payload['buttonId'] ?? '', 'input' => '[REDACTED]']);
        return $this->listButtonResponse(fn (): array => $this->listButtonService()->executeListButton('i18n_demo', $payload));
    }
    private function listButtonResponse(callable $operation): Response
    {
        try { return $this->ok(data: $operation()); }
        catch (\Throwable $error) {
            return $this->listActionFailure($error);
        }
    }

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

    #[Post(':id/status')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function status(int|string $id): Response { return $this->crudStatus($id); }

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
    protected function importFields(): array { return array_combine(I18nDemoService::WRITABLE_FIELDS, I18nDemoService::WRITABLE_FIELDS); }
    protected function importPayload(array $row): array { return (new I18nDemoService())->prepareCreatePayload($this->mapImportRow($row)); }
    protected function exportFields(): array { return array (
  0 => 'id',
  1 => 'name',
  2 => 'status',
  3 => 'remark',
  4 => 'createdAt',
  5 => 'updatedAt',
  6 => 'deletedAt',
); }
    protected function importLimit(): int { return 10000; }
    protected function exportLimit(): int { return 10000; }
    protected function payload(?Model $model = null): array
    {
        $payload = array_intersect_key($this->request->post(), array_flip(I18nDemoService::WRITABLE_FIELDS));
        return $model === null ? (new I18nDemoService())->prepareCreatePayload($payload) : $payload;
    }
    protected function validatePayload(array &$data, ?Model $model = null): ?string
    {
        $validate = new I18nDemoValidate();
        if ($model !== null) $validate->forUpdate($model->id, $data);
        return $validate->check($data) ? null : $validate->getError();
    }
    protected function beforeDelete(iterable $models, bool $force): ?Response
    {
        $error = (new I18nDemoService())->assertNotReferenced($models, $force);
        return $error === null ? null : $this->fail(msg: $error, code: 422);
    }
    protected function transformData(Model $model): array
    {
        return [
            'id' => (int) $model->id,
            'name' => (string) ($model->name ?? ''),
            'status' => (int) $model->status,
            'remark' => (string) ($model->remark ?? ''),
            'createdAt' => (string) ($model->created_at ?? ''),
            'updatedAt' => (string) ($model->updated_at ?? ''),
            'deletedAt' => (string) ($model->deleted_at ?? ''),
        ];
    }
    protected function resourceName(): string { return 'i18n验收演示'; }
}

