<?php

declare(strict_types=1);

namespace app\common\traits;

use InvalidArgumentException;
use LogicException;
use think\facade\Db;
use think\Model;
use think\Response;

/**
 * Admin Web REST CRUD 公共流程。
 *
 * 控制器负责模型、查询、字段映射和业务校验；Trait 只编排稳定的 CRUD 流程。
 */
trait Crud
{
    protected function crudPayload(?Model $model = null): array
    {
        throw new LogicException(static::class . ' 必须实现 crudPayload()');
    }

    protected function crudValidate(array &$data, ?Model $model = null): ?string
    {
        return null;
    }

    protected function crudData(Model $model): array
    {
        return $model->toArray();
    }

    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = $this->crudOrderedQuery($this->crudRecycled());
        $result = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
        ]);

        return $this->ok(data: $this->paginationData(
            array_map(fn (Model $model): array => $this->crudData($model), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    public function detail(int $id): Response
    {
        $model = $this->crudFind($id, true);
        return $model
            ? $this->ok(data: $this->crudData($model))
            : $this->fail(msg: $this->crudMessage('不存在'), code: 404);
    }

    public function create(): Response
    {
        $data = $this->crudPayload();
        if ($error = $this->crudValidate($data)) {
            return $this->fail(msg: $error, code: 422);
        }

        $model = ($this->model)::create($data);
        $this->crudAfterSave($model, true);

        return $this->ok('创建成功', $this->crudData($model));
    }

    public function update(int $id): Response
    {
        $model = $this->crudFind($id);
        if (!$model) {
            return $this->fail(msg: $this->crudMessage('不存在'), code: 404);
        }

        $data = $this->crudPayload($model);
        if ($error = $this->crudValidate($data, $model)) {
            return $this->fail(msg: $error, code: 422);
        }

        $model->save($data);
        $this->crudAfterSave($model, false);

        return $this->ok('保存成功', $this->crudData($model));
    }

    public function status(int $id): Response
    {
        $model = $this->crudFind($id);
        if (!$model) {
            return $this->fail(msg: $this->crudMessage('不存在'), code: 404);
        }

        $model->save(['status' => $this->binaryStatus($this->request->post('status', 0))]);
        $this->crudAfterSave($model, false);

        return $this->ok('状态更新成功', $this->crudData($model));
    }

    public function recycle(): Response
    {
        $models = $this->crudModelsForAction(false);
        if ($models instanceof Response) {
            return $models;
        }
        if ($denied = $this->crudBeforeDelete($models, false)) {
            return $denied;
        }

        foreach ($models as $model) {
            $model->delete();
        }

        return $this->ok('已移入回收站', ['removed' => count($models)]);
    }

    public function restore(): Response
    {
        $models = $this->crudModelsForAction(true, '恢复');
        if ($models instanceof Response) {
            return $models;
        }

        foreach ($models as $model) {
            $model->restore();
        }

        return $this->ok('恢复成功', ['restored' => count($models)]);
    }

    public function destroy(): Response
    {
        $models = $this->crudModelsForAction(true);
        if ($models instanceof Response) {
            return $models;
        }
        if ($denied = $this->crudBeforeDelete($models, true)) {
            return $denied;
        }

        foreach ($models as $model) {
            $model->force()->delete();
        }

        return $this->ok('永久删除成功', ['removed' => count($models)]);
    }

    public function import(): Response
    {
        $rows = $this->request->post('rows', []);
        if (!is_array($rows) || !$rows) {
            return $this->fail(msg: '导入数据不能为空', code: 422);
        }
        if (count($rows) > $this->crudImportLimit()) {
            return $this->fail(msg: sprintf('单次最多导入 %d 条数据', $this->crudImportLimit()), code: 422);
        }

        try {
            $created = Db::transaction(function () use ($rows): array {
                $models = [];
                foreach (array_values($rows) as $index => $row) {
                    if (!is_array($row)) {
                        throw new InvalidArgumentException(sprintf('第 %d 行数据格式错误', $index + 1));
                    }
                    $data = $this->crudImportPayload($row);
                    if ($error = $this->crudValidate($data)) {
                        throw new InvalidArgumentException(sprintf('第 %d 行：%s', $index + 1, $error));
                    }
                    $model = ($this->model)::create($data);
                    $this->crudAfterSave($model, true);
                    $models[] = $model;
                }
                return $models;
            });
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        }

        return $this->ok('导入成功', ['created' => count($created)]);
    }

    public function export(): Response
    {
        $query = $this->crudOrderedQuery($this->crudRecycled());
        if ((clone $query)->count() > $this->crudExportLimit()) {
            return $this->fail(msg: sprintf('导出数据超过 %d 条，请缩小筛选范围', $this->crudExportLimit()), code: 422);
        }

        return $this->ok(data: array_map(
            fn (Model $model): array => $this->crudExportData($model),
            $query->select()->all()
        ));
    }

    protected function crudResourceName(): string
    {
        return '数据';
    }

    protected function crudSearchFields(): array
    {
        return [];
    }

    protected function crudExactFilters(): array
    {
        return ['status' => 'status'];
    }

    protected function crudRangeFilters(): array
    {
        return [];
    }

    protected function crudSortFields(): array
    {
        return [];
    }

    protected function crudQuery(bool $recycled)
    {
        $query = $recycled ? ($this->model)::onlyTrashed() : ($this->model)::where('id', '>', 0);
        return $this->crudApplyFilters($query);
    }

    protected function crudOrder(): array
    {
        return ['id' => 'asc'];
    }

    protected function crudImportFields(): array
    {
        return [];
    }

    protected function crudExportFields(): array
    {
        return [];
    }

    protected function crudImportPayload(array $row): array
    {
        return $this->crudMapImportRow($row);
    }

    protected function crudImportLimit(): int
    {
        return 1000;
    }

    protected function crudExportLimit(): int
    {
        return 10000;
    }

    protected function crudBeforeDelete(iterable $models, bool $force): ?Response
    {
        return null;
    }

    protected function crudAfterSave(Model $model, bool $created): void
    {
    }

    private function crudRecycled(): bool
    {
        return (int) $this->request->get('recycled', 0) === 1;
    }

    private function crudOrderedQuery(bool $recycled)
    {
        return $this->crudApplyOrder($this->crudQuery($recycled));
    }

    private function crudApplyFilters($query)
    {
        foreach ($this->crudSearchFields() as $parameter => $field) {
            $value = trim((string) $this->request->get($parameter, ''));
            if ($value !== '') {
                $query->whereLike($field, '%' . $value . '%');
            }
        }
        foreach ($this->crudExactFilters() as $parameter => $field) {
            $value = $this->request->get($parameter, null);
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }
        foreach ($this->crudRangeFilters() as $parameter => $field) {
            $range = $this->crudRangeValue($this->request->get($parameter, null));
            if ($range[0] !== null) {
                $query->where($field, '>=', $range[0]);
            }
            if ($range[1] !== null) {
                $query->where($field, '<=', $range[1]);
            }
        }
        return $query;
    }

    private function crudApplyOrder($query)
    {
        $orders = $this->crudOrder();
        $sort = trim((string) $this->request->get('sort', ''));
        $sortFields = $this->crudSortFields();
        if ($sort !== '' && isset($sortFields[$sort])) {
            $orders = [$sortFields[$sort] => $this->request->get('order', 'asc')];
        }
        foreach ($orders as $field => $direction) {
            $query->order($field, strtolower((string) $direction) === 'desc' ? 'desc' : 'asc');
        }
        return $query;
    }

    private function crudRangeValue(mixed $value): array
    {
        if (is_array($value)) {
            $parts = array_values($value);
        } else {
            $parts = preg_split('/\s+-\s+|,/', trim((string) $value), 2) ?: [];
        }
        $begin = isset($parts[0]) && $parts[0] !== '' ? $parts[0] : null;
        $end = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;
        return [$begin, $end];
    }

    protected function crudMapImportRow(array $row): array
    {
        $fields = $this->crudImportFields();
        if (!$fields) {
            throw new InvalidArgumentException(static::class . ' 未配置导入字段映射');
        }
        $data = [];
        foreach ($fields as $input => $field) {
            if (array_key_exists($input, $row)) {
                $data[$field] = $row[$input];
            }
        }
        return $data;
    }

    private function crudExportData(Model $model): array
    {
        $data = $this->crudData($model);
        $fields = $this->crudExportFields();
        return $fields ? array_intersect_key($data, array_flip($fields)) : $data;
    }

    private function crudFind(int $id, bool $withTrashed = false): ?Model
    {
        $query = $withTrashed ? ($this->model)::withTrashed() : ($this->model)::where('id', '>', 0);
        $model = $query->where('id', $id)->find();
        return $model instanceof Model ? $model : null;
    }

    private function crudModelsForAction(bool $onlyTrashed, string $action = '操作'): iterable|Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: sprintf('请选择要%s的%s', $action, $this->crudResourceName()), code: 422);
        }

        $query = $onlyTrashed ? ($this->model)::onlyTrashed() : ($this->model)::where('id', '>', 0);
        $models = $query->whereIn('id', $ids)->select();
        if (count($models) !== count($ids)) {
            $message = $onlyTrashed
                ? sprintf('部分%s不存在或不在回收站', $this->crudResourceName())
                : sprintf('部分%s不存在或已在回收站', $this->crudResourceName());
            return $this->fail(msg: $message, code: 404);
        }

        return $models;
    }

    private function crudMessage(string $suffix): string
    {
        return $this->crudResourceName() . $suffix;
    }
}
