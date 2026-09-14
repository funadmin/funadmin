<?php

declare(strict_types=1);

namespace app\console\service\generated;

use app\console\model\generated\OrderTest;
use think\facade\Db;

final class OrderTestService
{
    public const WRITABLE_FIELDS = array (
  0 => 'field_1',
  1 => 'field_7',
  2 => 'field_2',
  3 => 'field_3',
  4 => 'field_4',
  5 => 'field_5',
  6 => 'field_6',
);
    public const WITH_RELATIONS = array (
  0 => 'field_1',
);

    public function prepareCreatePayload(array $payload): array
    {
        return $payload;
    }

    public function query(?array $departmentIds = null, bool $recycled = false)
    {
        $query = $recycled ? OrderTest::onlyTrashed()->with(self::WITH_RELATIONS) : OrderTest::with(self::WITH_RELATIONS);
        return $query;
    }

    public function save(?OrderTest $model, array $payload, callable $relations): OrderTest
    {
        return Db::transaction(function () use ($model, $payload, $relations): OrderTest {
            $model ??= new OrderTest();
            $model->save(array_intersect_key($payload, array_flip(self::WRITABLE_FIELDS)));
            $relations($model, $payload);
            return $model;
        });
    }

    public function assertNotReferenced(iterable $models, bool $force): ?string
    {
        $ids = [];
        foreach ($models as $model) {
            $ids[] = $model->id;
        }
        if ($ids === []) return null;
        $references = Db::query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?', ['fun_test', 'id']);
        foreach ($references as $reference) {
            $table = (string) ($reference['TABLE_NAME'] ?? $reference['table_name'] ?? '');
            $column = (string) ($reference['COLUMN_NAME'] ?? $reference['column_name'] ?? '');
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table) || !preg_match('/^[a-z_][a-z0-9_]*$/', $column)) {
                throw new \RuntimeException('数据库引用元数据包含非法标识符');
            }
            if (Db::table($table)->whereIn($column, $ids)->limit(1)->count() > 0) {
                return '记录仍被 ' . $table . '.' . $column . ' 引用，无法删除';
            }
        }
        return null;
    }

    public function options(string $source, ?array $departmentIds = null): array
    {
        return match ($source) {
            'field_1_options' => array_map(static fn (array $row): array => ['label' => (string) $row['username'], 'value' => $row['id']], \app\console\model\generated\AdminLog::order('id', 'asc')->field('id,username')->select()->toArray()),
            default => throw new \InvalidArgumentException('未知 optionsSource'),
        };
    }
}

