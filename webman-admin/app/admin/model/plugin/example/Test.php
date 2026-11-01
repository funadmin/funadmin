<?php

declare(strict_types=1);

namespace app\admin\model\plugin\example;

use app\admin\model\BackendModel;
use app\common\model\concern\LaravelSoftDelete;

final class Test extends BackendModel
{
    use LaravelSoftDelete { delete as private treeDelete; restore as private treeRestore; }

    protected string $table = 'example_test';
    protected $connection = 'mysql';
    protected string $pk = 'id';
    protected array $type = array (
  'id' => 'integer',
  'created_at' => 'datetime',
  'updated_at' => 'datetime',
  'deleted_at' => 'datetime',
);

    public function save(array|object $data = [], $where = [], bool $refresh = false): bool
    {
        return \think\facade\Db::connect('mysql')->transaction(function () use ($data, $where, $refresh): bool {
            $payload = array_replace($this->getData(), (array) $data);
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('test', (string) ($payload['id'] ?? ''), $payload);
            return parent::save($data, $where, $refresh);
        });
    }

    public function delete(): bool
    {
        return \think\facade\Db::connect('mysql')->transaction(function (): bool {
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('test', (string) $this->getAttr('id'), [], true);
            return $this->treeDelete();
        });
    }

    public function restore(array $where = []): bool
    {
        return \think\facade\Db::connect('mysql')->transaction(function () use ($where): bool {
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('test', (string) $this->getAttr('id'), $this->getData());
            return $this->treeRestore($where);
        });
    }
}

