<?php

declare(strict_types=1);

namespace app\admin\service\generated;

use app\admin\model\generated\I18nDemo;
use think\facade\Db;

final class I18nDemoService
{
    public const WRITABLE_FIELDS = array (
  0 => 'name',
  1 => 'status',
  2 => 'remark',
);
    public const WITH_RELATIONS = array (
);

    public function prepareCreatePayload(array $payload): array
    {
        return $payload;
    }

    public function query(?array $departmentIds = null, bool $recycled = false)
    {
        $query = $recycled ? I18nDemo::onlyTrashed()->with(self::WITH_RELATIONS) : I18nDemo::with(self::WITH_RELATIONS);
        return $query;
    }

    public function save(?I18nDemo $model, array $payload, callable $relations): I18nDemo
    {
        return Db::transaction(function () use ($model, $payload, $relations): I18nDemo {
            $model ??= new I18nDemo();
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
        $references = Db::query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?', ['fun_i18n_demo', 'id']);
        foreach ($references as $reference) {
            $table = (string) ($reference['TABLE_NAME'] ?? $reference['table_name'] ?? '');
            $column = (string) ($reference['COLUMN_NAME'] ?? $reference['column_name'] ?? '');
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table) || !preg_match('/^[a-z_][a-z0-9_]*$/', $column)) {
                throw new \RuntimeException(lang('i18n-demo.referenceMetadataInvalid'));
            }
            if (Db::table($table)->whereIn($column, $ids)->limit(1)->count() > 0) {
                return lang('i18n-demo.referenced', ['target' => $table . '.' . $column]);
            }
        }
        return null;
    }
}

