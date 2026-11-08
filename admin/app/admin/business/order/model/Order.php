<?php

declare(strict_types=1);

namespace app\admin\business\order\model;

use app\common\model\concern\LaravelSoftDelete;

final class Order extends \app\common\model\BaseModel
{
    use LaravelSoftDelete { restore as private treeRestore; }

    protected $table = 'order';
    protected $connection = 'mysql';
    protected $primaryKey = 'id';
    public $timestamps = true;
    public $incrementing = true;
    protected $keyType = 'int';
    protected $fillable = array (
  0 => 'field_1',
  1 => 'field_2',
  2 => 'field_3',
  3 => 'field_5',
  4 => 'field_5_copy',
  5 => 'field_4',
);
    protected $casts = array (
  'id' => 'integer',
  'field_5' => 'integer',
  'field_5_copy' => 'integer',
  'created_at' => 'datetime',
  'updated_at' => 'datetime',
  'deleted_at' => 'datetime',
);

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('order', (string) ($this->getKey() ?? ''), $this->getAttributes());
            return parent::save($options);
        });
    }

    public function delete()
    {
        return $this->getConnection()->transaction(function () {
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('order', (string) $this->getKey(), [], true);
            return parent::delete();
        });
    }

    public function restore()
    {
        return $this->getConnection()->transaction(function () {
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('order', (string) $this->getKey(), $this->getAttributes());
            return $this->treeRestore();
        });
    }
}

