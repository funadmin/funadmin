<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com/
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 */

declare(strict_types=1);

namespace app\backend\service\casbin;

use Casbin\Model\Model;
use Casbin\Persist\Adapter;
use Casbin\Persist\AdapterHelper;
use Casbin\Persist\BatchAdapter;
use think\facade\Db;
use think\db\exception\PDOException;

/**
 * 使用 ThinkPHP 数据库连接持久化 Casbin 策略，复用项目表前缀与事务连接。
 */
class ThinkAdapter implements Adapter, BatchAdapter
{
    use AdapterHelper;

    private string $table;

    public function __construct(string $table = 'casbin_rule')
    {
        $this->table = $table;
    }

    public function loadPolicy(Model $model): void
    {
        $rows = Db::name($this->table)
            ->order('id', 'asc')
            ->field('ptype,v0,v1,v2,v3,v4,v5')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $rule = [(string) $row['ptype']];
            foreach (['v0', 'v1', 'v2', 'v3', 'v4', 'v5'] as $field) {
                $rule[] = (string) ($row[$field] ?? '');
            }
            while (count($rule) > 1 && end($rule) === '') {
                array_pop($rule);
            }
            $this->loadPolicyArray($rule, $model);
        }
    }

    public function savePolicy(Model $model): void
    {
        $rows = [];
        foreach (['p', 'g'] as $section) {
            if (!isset($model[$section])) {
                continue;
            }
            foreach ($model[$section] as $ptype => $assertion) {
                foreach ($assertion->policy as $rule) {
                    $rows[] = $this->makeRow((string) $ptype, $rule);
                }
            }
        }

        Db::transaction(function () use ($rows) {
            Db::name($this->table)->delete(true);
            if ($rows) {
                Db::name($this->table)->insertAll($rows);
            }
        });
    }

    public function addPolicy(string $sec, string $ptype, array $rule): void
    {
        try {
            Db::name($this->table)->insert($this->makeRow($ptype, $rule));
        } catch (PDOException $e) {
            if (!str_contains(strtolower($e->getMessage()), 'duplicate')) {
                throw $e;
            }
        }
    }

    public function addPolicies(string $sec, string $ptype, array $rules): void
    {
        if (!$rules) {
            return;
        }
        Db::transaction(function () use ($sec, $ptype, $rules) {
            foreach ($rules as $rule) {
                $this->addPolicy($sec, $ptype, $rule);
            }
        });
    }

    public function removePolicy(string $sec, string $ptype, array $rule): void
    {
        $query = Db::name($this->table)->where('ptype', $ptype);
        foreach (array_values($rule) as $index => $value) {
            $query->where('v' . $index, (string) $value);
        }
        $query->delete();
    }

    public function removePolicies(string $sec, string $ptype, array $rules): void
    {
        Db::transaction(function () use ($sec, $ptype, $rules) {
            foreach ($rules as $rule) {
                $this->removePolicy($sec, $ptype, $rule);
            }
        });
    }

    public function removeFilteredPolicy(string $sec, string $ptype, int $fieldIndex, string ...$fieldValues): void
    {
        $query = Db::name($this->table)->where('ptype', $ptype);
        foreach ($fieldValues as $offset => $value) {
            if ($value !== '') {
                $query->where('v' . ($fieldIndex + $offset), $value);
            }
        }
        $query->delete();
    }

    private function makeRow(string $ptype, array $rule): array
    {
        if (count($rule) > 6) {
            throw new \InvalidArgumentException('Casbin 策略字段不能超过 6 个');
        }

        $values = array_map('strval', array_values($rule));
        $row = [
            'ptype' => $ptype,
            'rule_hash' => hash('sha256', implode("\x1f", array_merge([$ptype], $values))),
        ];
        for ($index = 0; $index < 6; $index++) {
            $row['v' . $index] = $values[$index] ?? '';
        }
        return $row;
    }
}
