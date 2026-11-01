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
        if (isset($this->name) && is_string($this->name) && $this->name !== '') {
            return $this->name;
        }
        return parent::getTable();
    }

    public static function get_table_prefix(): string
    {
        return (string) (config('database.connections.' . config('database.default') . '.prefix') ?? '');
    }

    public static function get_databasename(): string
    {
        return (string) (config('database.connections.' . config('database.default') . '.database') ?? '');
    }

    public static function get_pluginstablename($tablename, $plugin): string
    {
        $tablename = str_replace($plugin . '_', '', str_replace('plugins_', '', (string) $tablename));
        return self::get_table_prefix() . $plugin . '_' . $tablename;
    }
}
