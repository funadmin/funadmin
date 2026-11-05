<?php

declare(strict_types=1);

namespace app\admin\plugins\example\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

final class Test extends BaseModel
{
    use LaravelSoftDelete { restore as private treeRestore; }

    protected $table = 'example_test';
    protected $connection = 'mysql';
    protected $primaryKey = 'id';
    protected $fillable = ['field_1', 'field_2', 'field_3'];
    protected $casts = array (
  'id' => 'integer',
  'created_at' => 'datetime',
  'updated_at' => 'datetime',
  'deleted_at' => 'datetime',
);

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            $payload = $this->getAttributes();
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('test', (string) ($payload['id'] ?? ''), $payload);
            return parent::save($options);
        });
    }

    public function delete()
    {
        return $this->getConnection()->transaction(function () {
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('test', (string) $this->getKey(), [], true);
            return parent::delete();
        });
    }

    public function restore()
    {
        return $this->getConnection()->transaction(function () {
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('test', (string) $this->getKey(), $this->getAttributes());
            return $this->treeRestore();
        });
    }
}
