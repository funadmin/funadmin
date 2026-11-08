<?php

declare(strict_types=1);

namespace app\admin\business\order\controller;

use app\common\controller\AdminController;
use app\admin\business\order\model\Order;
use app\admin\authorization\service\DataScopeService;
use app\admin\business\order\service\OrderService;
use app\admin\business\order\validate\OrderValidate;
use app\common\traits\Crud;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup as Group;
use support\annotation\route\Post;
use support\annotation\route\Put;
use Illuminate\Database\Eloquent\Model;
use support\Response;

#[Group('/admin/business/order')]
final class OrderController extends AdminController
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
    protected string $model = Order::class;

    private function listButtonService(): \app\admin\form\service\FormDataService
    {
        return new \app\admin\form\service\FormDataService(permissionChecker: fn (string $route): bool => (new \app\admin\authorization\service\AdminAuthorizationService())->nodeAccess($route), productionBinding: array (
  'formKey' => 'order',
  'schemaHash' => 'a89864e189620fc8f917c94a0475460931ae96469ae931229e4c4dadb167fc06',
  'route' => 'admin/business.ordercontroller',
  'table' => 'fun_order',
  'connection' => 'mysql',
));
    }
    #[Get('/list-actions')]
    public function listActions(): Response
    {
        return $this->listButtonResponse(fn (): array => $this->listButtonService()->listActionCatalog('order', (string) $this->request->get('location', 'row'), \app\common\form\registry\FormRegistryFactory::production()->actions()));
    }
    #[Post('/list-action')]
    public function listAction(): Response
    {
        $payload = $this->request->post();
        $this->request->setPost(['buttonId' => $payload['buttonId'] ?? '', 'input' => '[REDACTED]']);
        return $this->listButtonResponse(fn (): array => $this->listButtonService()->executeListButton('order', $payload));
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

    #[Post('')]
    public function create(): Response { return $this->crudCreate(); }

    #[Delete('')]
    public function recycle(): Response { return $this->crudRecycle(); }

    #[Post('/restore')]
    public function restoreMany(): Response { return $this->crudRestoreMany(); }

    #[Delete('/destroy')]
    public function destroyMany(): Response { return $this->crudDestroyMany(); }

    #[Post('/import')]
    public function import(): Response { return $this->crudImport(); }

    #[Get('/export')]
    public function export(): Response { return $this->crudExport(); }

    #[Get('/{id:[A-Za-z0-9_-]+}')]
    public function detail(mixed $id): Response { return $this->crudDetail($id); }

    #[Put('/{id:[A-Za-z0-9_-]+}')]
    public function update(mixed $id): Response { return $this->crudUpdate($id); }

    #[Delete('/{id:[A-Za-z0-9_-]+}')]
    public function remove(mixed $id): Response { return $this->crudRemove($id); }

    #[Post('/{id:[A-Za-z0-9_-]+}/restore')]
    public function restore(mixed $id): Response { return $this->crudRestoreOne($id); }

    #[Delete('/{id:[A-Za-z0-9_-]+}/destroy')]
    public function destroy(mixed $id): Response { return $this->crudDestroyOne($id); }

    protected function searchFields(): array { return array (
); }
    protected function exactFilters(): array { return array (
  'field1' => 'field_1',
); }
    protected function rangeFilters(): array { return array (
); }
    protected function operatorFilters(): array { return array (
); }
    protected function sortFields(): array { return array (
  'id' => 'id',
  'field1' => 'field_1',
); }
    protected function primaryKey(): string { return 'id'; }
    protected function primaryKeyType(): string { return 'integer'; }
    protected function primaryKeyPattern(): ?string { return NULL; }
    protected function usesSoftDeletes(): bool { return true; }
    protected function importFields(): array { return array_combine(OrderService::WRITABLE_FIELDS, OrderService::WRITABLE_FIELDS); }
    protected function importPayload(array $row): array { return (new OrderService())->prepareCreatePayload($this->mapImportRow($row)); }
    protected function exportFields(): array { return array (
  0 => 'id',
  1 => 'field1',
  2 => 'field2',
  3 => 'field3',
  4 => 'field5',
  5 => 'field5Copy',
  6 => 'createdAt',
  7 => 'updatedAt',
  8 => 'deletedAt',
); }
    protected function importLimit(): int { return 10000; }
    protected function exportLimit(): int { return 10000; }
    protected function payload(?Model $model = null): array
    {
        $payload = (new OrderService())->normalizePayload($this->request->post());
        return $model === null ? (new OrderService())->prepareCreatePayload($payload) : $payload;
    }
    protected function validatePayload(array &$data, ?Model $model = null): ?string
    {
        $validate = new OrderValidate();
        if ($model !== null) $validate->forUpdate($model->id, $data);
        return $validate->check($data) ? null : $validate->getError();
    }
    protected function beforeDelete(iterable $models, bool $force): ?Response
    {
        $error = (new OrderService())->assertNotReferenced($models, $force);
        return $error === null ? null : $this->fail(msg: $error, code: 422);
    }
    protected function transformData(Model $model): array
    {
        return [
            'id' => (int) $model->id,
            'field1' => (string) ($model->field_1 ?? ''),
            'field2' => (string) ($model->field_2 ?? ''),
            'field3' => (string) ($model->field_3 ?? ''),
            'field5' => $model->field_5 === null ? null : (int) $model->field_5,
            'field5Copy' => $model->field_5_copy === null ? null : (int) $model->field_5_copy,
            'createdAt' => (string) ($model->created_at ?? ''),
            'updatedAt' => (string) ($model->updated_at ?? ''),
            'deletedAt' => (string) ($model->deleted_at ?? ''),
        ];
    }
    protected function resourceName(): string { return 'order'; }
}
