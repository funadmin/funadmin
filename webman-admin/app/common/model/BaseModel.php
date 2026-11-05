<?php

declare(strict_types=1);

namespace app\common\model;

use support\Model as EloquentModel;

class BaseModel extends EloquentModel
{
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $casts = [];

    public function getTable(): string
    {
        if (property_exists($this, 'name') && is_string($this->name) && $this->name !== '') {
            return $this->name;
        }
        return parent::getTable();
    }

    public static function getTablePrefix(): string
    {
        return (string) (config('database.connections.' . config('database.default') . '.prefix') ?? '');
    }

    public static function getDatabaseName(): string
    {
        return (string) (config('database.connections.' . config('database.default') . '.database') ?? '');
    }

    public static function getPluginTableName(string $tableName, string $plugin): string
    {
        $tableName = str_replace($plugin . '_', '', str_replace('plugins_', '', $tableName));
        return self::getTablePrefix() . $plugin . '_' . $tableName;
    }
}
