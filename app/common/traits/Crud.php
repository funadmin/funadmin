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
    protected function payload(?Model $model = null): array
    {
        throw new LogicException(static::class . ' 必须实现 payload()');
    }

    protected function validatePayload(array &$data, ?Model $model = null): ?string
    {
        return null;
    }

    protected function transformData(Model $model): array
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
            array_map(fn (Model $model): array => $this->transformData($model), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    public function detail(int|string $id): Response
    {
        $model = $this->crudFind($id, true);
        return $model
            ? $this->ok(data: $this->transformData($model))
            : $this->fail(msg: $this->crudMessage('不存在'), code: 404);
    }

    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }

        $model = Db::transaction(function () use ($data): Model {
            $model = ($this->model)::create($data);
            $this->afterSave($model, true);
            return $model;
        });

        return $this->ok('创建成功', $this->transformData($model));
    }

    public function update(int|string $id): Response
    {
        $model = $this->crudFind($id);
        if (!$model) {
            return $this->fail(msg: $this->crudMessage('不存在'), code: 404);
        }

        $data = $this->payload($model);
        if ($error = $this->validatePayload($data, $model)) {
            return $this->fail(msg: $error, code: 422);
        }

        Db::transaction(function () use ($model, $data): void {
            $model->save($data);
            $this->afterSave($model, false);
        });

        return $this->ok('保存成功', $this->transformData($model));
    }

    public function status(int|string $id): Response
    {
        $model = $this->crudFind($id);
        if (!$model) {
            return $this->fail(msg: $this->crudMessage('不存在'), code: 404);
        }

        $model->save(['status' => $this->binaryStatus($this->request->post('status', 0))]);
        $this->afterSave($model, false);

        return $this->ok('状态更新成功', $this->transformData($model));
    }

    public function remove(int|string $id): Response
    {
        return $this->crudDeleteOne($id, false, false);
    }

    public function restoreOne(int|string $id): Response
    {
        return $this->crudDeleteOne($id, true, false);
    }

    public function destroyOne(int|string $id): Response
    {
        return $this->crudDeleteOne($id, true, true);
    }

    public function recycle(): Response
    {
        $models = $this->crudModelsForAction(false);
        if ($models instanceof Response) {
            return $models;
        }
        if ($denied = $this->beforeDelete($models, false)) {
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
        if ($denied = $this->beforeDelete($models, true)) {
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
        if (count($rows) > $this->importLimit()) {
            return $this->fail(msg: sprintf('单次最多导入 %d 条数据', $this->importLimit()), code: 422);
        }

        try {
            $created = Db::transaction(function () use ($rows): array {
                $models = [];
                foreach (array_values($rows) as $index => $row) {
                    if (!is_array($row)) {
                        throw new InvalidArgumentException(sprintf('第 %d 行数据格式错误', $index + 1));
                    }
                    $data = $this->importPayload($row);
                    if ($error = $this->validatePayload($data)) {
                        throw new InvalidArgumentException(sprintf('第 %d 行：%s', $index + 1, $error));
                    }
                    $model = ($this->model)::create($data);
                    $this->afterSave($model, true);
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
        if ((clone $query)->count() > $this->exportLimit()) {
            return $this->fail(msg: sprintf('导出数据超过 %d 条，请缩小筛选范围', $this->exportLimit()), code: 422);
        }

        return $this->ok(data: array_map(
            fn (Model $model): array => $this->crudExportData($model),
            $query->select()->all()
        ));
    }

    protected function resourceName(): string
    {
        return '数据';
    }

    protected function searchFields(): array
    {
        return [];
    }

    protected function exactFilters(): array
    {
        return ['status' => 'status'];
    }

    protected function rangeFilters(): array
    {
        return [];
    }

    protected function sortFields(): array
    {
        return [];
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function primaryKeyType(): string
    {
        return 'integer';
    }

    protected function primaryKeyPattern(): ?string
    {
        return null;
    }

    protected function query(bool $recycled)
    {
        return $this->applyFilters($this->baseQuery($recycled, false));
    }

    protected function baseQuery(bool $onlyTrashed, bool $withTrashed)
    {
        $primaryKey = $this->primaryKey();
        return match (true) {
            $onlyTrashed => ($this->model)::onlyTrashed(),
            $withTrashed => ($this->model)::withTrashed(),
            default => ($this->model)::where($primaryKey, '<>', ''),
        };
    }

    protected function order(): array
    {
        return [$this->primaryKey() => 'asc'];
    }

    protected function importFields(): array
    {
        return [];
    }

    protected function exportFields(): array
    {
        return [];
    }

    protected function importPayload(array $row): array
    {
        return $this->mapImportRow($row);
    }

    protected function importLimit(): int
    {
        return 1000;
    }

    protected function exportLimit(): int
    {
        return 10000;
    }

    protected function beforeDelete(iterable $models, bool $force): ?Response
    {
        return null;
    }

    protected function afterSave(Model $model, bool $created): void
    {
    }

    private function crudRecycled(): bool
    {
        return (int) $this->request->get('recycled', 0) === 1;
    }

    private function crudOrderedQuery(bool $recycled)
    {
        return $this->crudApplyOrder($this->query($recycled));
    }

    protected function applyFilters($query)
    {
        foreach ($this->searchFields() as $parameter => $field) {
            $value = trim((string) $this->request->get($parameter, ''));
            if ($value !== '') {
                $query->whereLike($field, '%' . $value . '%');
            }
        }
        foreach ($this->exactFilters() as $parameter => $field) {
            $value = $this->request->get($parameter, null);
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }
        foreach ($this->rangeFilters() as $parameter => $field) {
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
        $orders = $this->order();
        $sort = trim((string) $this->request->get('sort', ''));
        $sortFields = $this->sortFields();
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

    protected function mapImportRow(array $row): array
    {
        $fields = $this->importFields();
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
        $data = $this->transformData($model);
        $fields = $this->exportFields();
        return $fields ? array_intersect_key($data, array_flip($fields)) : $data;
    }

    private function crudFind(int|string $id, bool $withTrashed = false): ?Model
    {
        $primaryKey = $this->primaryKey();
        $model = $this->baseQuery(false, $withTrashed)->where($primaryKey, $id)->find();
        return $model instanceof Model ? $model : null;
    }

    private function crudDeleteOne(int|string $id, bool $onlyTrashed, bool $force): Response
    {
        $model = $this->baseQuery($onlyTrashed, false)->where($this->primaryKey(), $id)->find();
        if (!$model instanceof Model) {
            $suffix = $onlyTrashed ? '不存在或不在回收站' : '不存在或已在回收站';
            return $this->fail(msg: $this->crudMessage($suffix), code: 404);
        }
        if ($denied = $this->beforeDelete([$model], $force)) {
            return $denied;
        }
        if ($force) {
            $model->force()->delete();
            return $this->ok('永久删除成功');
        }
        if ($onlyTrashed) {
            $model->restore();
            return $this->ok('恢复成功', $this->transformData($model));
        }
        $model->delete();
        return $this->ok('已移入回收站');
    }

    private function crudModelsForAction(bool $onlyTrashed, string $action = '操作'): iterable|Response
    {
        try {
            $idsInput = $this->request->get('ids', null);
            $ids = $this->normalizeIds(
                $idsInput ?? $this->request->post('ids', []),
                $this->primaryKeyType(),
                $this->primaryKeyPattern(),
                true
            );
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        }
        if (!$ids) {
            return $this->fail(msg: sprintf('请选择要%s的%s', $action, $this->resourceName()), code: 422);
        }

        $primaryKey = $this->primaryKey();
        $models = $this->baseQuery($onlyTrashed, false)->whereIn($primaryKey, $ids)->select();
        if (count($models) !== count($ids)) {
            $message = $onlyTrashed
                ? sprintf('部分%s不存在或不在回收站', $this->resourceName())
                : sprintf('部分%s不存在或已在回收站', $this->resourceName());
            return $this->fail(msg: $message, code: 404);
        }

        return $models;
    }

    private function crudMessage(string $suffix): string
    {
        return $this->resourceName() . $suffix;
    }
}
