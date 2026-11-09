<?php

declare(strict_types=1);

namespace app\admin\plugins\example\service;

use app\admin\plugins\example\model\Test;
use support\Db;

final class TestService
{
    public const WRITABLE_FIELDS = array (
  0 => 'field_1',
  1 => 'field_2',
  2 => 'field_3',
);
    public const WITH_RELATIONS = array (
);

    public function prepareCreatePayload(array $payload): array
    {
        return $payload;
    }

    public function query(?array $departmentIds = null, bool $recycled = false)
    {
        $query = $recycled ? Test::onlyTrashed()->with(self::WITH_RELATIONS) : Test::with(self::WITH_RELATIONS);
        return $query;
    }

    public function save(?Test $model, array $payload, callable $relations): Test
    {
        return (new Test())->getConnection()->transaction(function () use ($model, $payload, $relations): Test {
            $model ??= new Test();
            $model->fill(array_intersect_key($payload, array_flip(self::WRITABLE_FIELDS)))->save();
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
        $connection = (new Test())->getConnection();
        $references = \app\common\service\database\ReferenceInspector::incoming($connection, 'example_test', 'id');
        foreach ($references as $reference) {
            $table = (string) $reference->TABLE_NAME;
            $column = (string) $reference->COLUMN_NAME;
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table) || !preg_match('/^[a-z_][a-z0-9_]*$/', $column)) {
                throw new \RuntimeException(trans('test.referenceMetadataInvalid'));
            }
            if ($connection->table($connection->getTablePrefix() !== '' && str_starts_with($table, $connection->getTablePrefix()) ? substr($table, strlen($connection->getTablePrefix())) : $table)->whereIn($column, $ids)->limit(1)->count() > 0) {
                return trans('test.referenced', ['target' => $table . '.' . $column]);
            }
        }
        return null;
    }
}
