<?php

declare(strict_types=1);

namespace app\console\controller\plugin\shop;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\model\plugin\shop\Product;
use app\console\service\DataScopeService;
use app\console\service\plugin\shop\ProductService;
use app\console\validate\plugin\shop\ProductValidate;
use app\common\traits\Crud;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Model;
use think\Response;

#[Group('plugin/shop/product')]
final class ProductController extends AdminApiController
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
    protected string $model = Product::class;

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

    protected function searchFields(): array { return array (
  'name' => 'name',
); }
    protected function exactFilters(): array { return array (
); }
    protected function rangeFilters(): array { return array (
); }
    protected function operatorFilters(): array { return array (
); }
    protected function sortFields(): array { return array (
  'price' => 'price',
); }
    protected function primaryKey(): string { return 'id'; }
    protected function primaryKeyType(): string { return 'integer'; }
    protected function primaryKeyPattern(): ?string { return NULL; }
    protected function usesSoftDeletes(): bool { return true; }
    protected function importFields(): array { return array_combine(ProductService::WRITABLE_FIELDS, ProductService::WRITABLE_FIELDS); }
    protected function importPayload(array $row): array { return (new ProductService())->prepareCreatePayload($this->mapImportRow($row)); }
    protected function exportFields(): array { return array (
  0 => 'id',
  1 => 'name',
  2 => 'price',
  3 => 'status',
  4 => 'createdAt',
  5 => 'updatedAt',
); }
    protected function importLimit(): int { return 100; }
    protected function exportLimit(): int { return 100; }
    protected function payload(?Model $model = null): array
    {
        $payload = array_intersect_key($this->request->post(), array_flip(ProductService::WRITABLE_FIELDS));
        return $model === null ? (new ProductService())->prepareCreatePayload($payload) : $payload;
    }
    protected function validatePayload(array &$data, ?Model $model = null): ?string
    {
        $validate = new ProductValidate();
        if ($model !== null) $validate->forUpdate($model->id);
        return $validate->check($data) ? null : $validate->getError();
    }
    protected function beforeDelete(iterable $models, bool $force): ?Response
    {
        $error = (new ProductService())->assertNotReferenced($models, $force);
        return $error === null ? null : $this->fail(msg: $error, code: 422);
    }
    protected function transformData(Model $model): array
    {
        return [
            'id' => (int) $model->id,
            'name' => (string) ($model->name ?? ''),
            'price' => (string) $model->price,
            'status' => (int) $model->status,
            'createdAt' => (string) ($model->created_at ?? ''),
            'updatedAt' => (string) ($model->updated_at ?? ''),
        ];
    }
    protected function resourceName(): string { return '商品'; }
}

