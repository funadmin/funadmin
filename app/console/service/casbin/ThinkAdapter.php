<?php

declare(strict_types=1);

namespace app\console\service\casbin;

use app\console\model\CasbinRule;
use Casbin\Model\Model;
use Casbin\Persist\Adapter;
use Casbin\Persist\AdapterHelper;
use Casbin\Persist\BatchAdapter;
use think\db\exception\PDOException;
use think\facade\Db;

/**
 * Casbin 的 ThinkPHP 模型适配器。
 */
class ThinkAdapter implements Adapter, BatchAdapter
{
    use AdapterHelper;

    public function loadPolicy(Model $model): void
    {
        $rows = CasbinRule::order('id', 'asc')
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
            CasbinRule::where('id', '>', 0)->delete();
            if ($rows) {
                (new CasbinRule())->saveAll($rows);
            }
        });
    }

    public function addPolicy(string $sec, string $ptype, array $rule): void
    {
        try {
            CasbinRule::create($this->makeRow($ptype, $rule));
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
        $query = CasbinRule::where('ptype', $ptype);
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
        $query = CasbinRule::where('ptype', $ptype);
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
