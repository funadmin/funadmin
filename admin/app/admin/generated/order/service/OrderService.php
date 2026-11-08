<?php

declare(strict_types=1);

namespace app\admin\generated\order\service;

use app\admin\generated\order\model\Order;
use support\Db;

final class OrderService
{
    public const WRITABLE_FIELDS = array (
  0 => 'field_1',
  1 => 'field_2',
  2 => 'field_3',
  3 => 'field_5',
  4 => 'field_5_copy',
  5 => 'field_4',
);
    public const WITH_RELATIONS = array (
);

    public function prepareCreatePayload(array $payload): array
    {
        return $payload;
    }

    public function query(?array $departmentIds = null, bool $recycled = false)
    {
        $query = $recycled ? Order::onlyTrashed()->with(self::WITH_RELATIONS) : Order::with(self::WITH_RELATIONS);
        return $query;
    }

    public function save(?Order $model, array $payload, callable $relations): Order
    {
        return (new Order())->getConnection()->transaction(function () use ($model, $payload, $relations): Order {
            $model ??= new Order();
            $model->fill(array_intersect_key($payload, array_flip(self::WRITABLE_FIELDS)))->save();
            $relations($model, $payload);
            return $model;
        });
    }

    public function assertNotReferenced(iterable $models, bool $force): ?string
    {
        $ids = [];
        foreach ($models as $model) $ids[] = $model->id;
        if ($ids === []) return null;
        $connection = (new Order())->getConnection();
        $references = \app\common\service\database\ReferenceInspector::incoming($connection, 'fun_order', 'id');
        foreach ($references as $reference) {
            $table = (string) $reference->TABLE_NAME;
            $column = (string) $reference->COLUMN_NAME;
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table) || !preg_match('/^[a-z_][a-z0-9_]*$/', $column)) throw new \RuntimeException(trans('order.referenceMetadataInvalid'));
            $prefix = $connection->getTablePrefix();
            $logical = $prefix !== '' && str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;
            if ($connection->table($logical)->whereIn($column, $ids)->exists()) return trans('order.referenced', ['target' => $table . '.' . $column]);
        }
        return null;
    }
}
