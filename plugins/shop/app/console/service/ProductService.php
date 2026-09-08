<?php

declare(strict_types=1);

namespace app\console\service\plugin\shop;

use app\console\model\plugin\shop\Product;
use think\facade\Db;

final class ProductService
{
    public const WRITABLE_FIELDS = array (
  0 => 'name',
  1 => 'price',
  2 => 'status',
);
    public const WITH_RELATIONS = array (
);

    public function prepareCreatePayload(array $payload): array
    {
        return $payload;
    }

    public function query(?array $departmentIds = null, bool $recycled = false)
    {
        $query = $recycled ? Product::onlyTrashed()->with(self::WITH_RELATIONS) : Product::with(self::WITH_RELATIONS);
        return $query;
    }

    public function save(?Product $model, array $payload, callable $relations): Product
    {
        return Db::transaction(function () use ($model, $payload, $relations): Product {
            $model ??= new Product();
            $model->save(array_intersect_key($payload, array_flip(self::WRITABLE_FIELDS)));
            $relations($model, $payload);
            return $model;
        });
    }

    public function assertNotReferenced(iterable $models, bool $force): ?string
    {
        return null;
    }
}

