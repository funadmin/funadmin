<?php

declare(strict_types=1);

namespace app\console\model\generated;

use app\console\model\BackendModel;
use app\common\model\concern\LaravelSoftDelete;
use app\console\model\BackendModel;

final class OrderTest extends BackendModel
{
    use LaravelSoftDelete { delete as private treeDelete; restore as private treeRestore; }

    protected string $table = 'fun_test';
    protected $connection = 'mysql';
    protected string $pk = 'id';
    protected array $type = array (
  'id' => 'integer',
  'field_7' => 'json',
  'field_5' => 'integer',
  'field_6' => 'json',
  'created_at' => 'datetime',
  'updated_at' => 'datetime',
  'deleted_at' => 'datetime',
);

    public function field_1()
    {
        return $this->belongsTo(\app\console\model\generated\AdminLog::class, 'field_1', 'id');
    }

    public function save(array|object $data = [], $where = [], bool $refresh = false): bool
    {
        return \think\facade\Db::connect('mysql')->transaction(function () use ($data, $where, $refresh): bool {
            $payload = array_replace($this->getData(), (array) $data);
            (new \app\console\form\service\FormDataService())->guardTreeWrite('order_test', (string) ($payload['id'] ?? ''), $payload);
            return parent::save($data, $where, $refresh);
        });
    }

    public function delete(): bool
    {
        return \think\facade\Db::connect('mysql')->transaction(function (): bool {
            (new \app\console\form\service\FormDataService())->guardTreeWrite('order_test', (string) $this->getAttr('id'), [], true);
            return $this->treeDelete();
        });
    }

    public function restore(array $where = []): bool
    {
        return \think\facade\Db::connect('mysql')->transaction(function () use ($where): bool {
            (new \app\console\form\service\FormDataService())->guardTreeWrite('order_test', (string) $this->getAttr('id'), $this->getData());
            return $this->treeRestore($where);
        });
    }
}

