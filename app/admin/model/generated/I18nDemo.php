<?php

declare(strict_types=1);

namespace app\admin\model\generated;

use app\admin\model\BackendModel;
use app\common\model\concern\LaravelSoftDelete;

final class I18nDemo extends BackendModel
{
    use LaravelSoftDelete { delete as private treeDelete; restore as private treeRestore; }

    protected string $table = 'fun_i18n_demo';
    protected $connection = 'mysql';
    protected string $pk = 'id';
    protected array $type = array (
  'id' => 'integer',
  'status' => 'integer',
  'created_at' => 'datetime',
  'updated_at' => 'datetime',
  'deleted_at' => 'datetime',
);

    public function save(array|object $data = [], $where = [], bool $refresh = false): bool
    {
        return \think\facade\Db::connect('mysql')->transaction(function () use ($data, $where, $refresh): bool {
            $payload = array_replace($this->getData(), (array) $data);
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('i18n_demo', (string) ($payload['id'] ?? ''), $payload);
            return parent::save($data, $where, $refresh);
        });
    }

    public function delete(): bool
    {
        return \think\facade\Db::connect('mysql')->transaction(function (): bool {
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('i18n_demo', (string) $this->getAttr('id'), [], true);
            return $this->treeDelete();
        });
    }

    public function restore(array $where = []): bool
    {
        return \think\facade\Db::connect('mysql')->transaction(function () use ($where): bool {
            (new \app\admin\form\service\FormDataService())->guardTreeWrite('i18n_demo', (string) $this->getAttr('id'), $this->getData());
            return $this->treeRestore($where);
        });
    }
}

