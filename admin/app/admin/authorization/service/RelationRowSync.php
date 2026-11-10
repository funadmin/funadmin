<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use Illuminate\Database\Eloquent\Model;
use support\Db;

/**
 * 关系行按差异同步：只软删除移除的行、只插入缺少的行，未变化的行不动（附加列变化时原地更新）。
 * 关系表不做清理，整组删除再插入会让每次保存都新增一整套软删除行。
 */
final class RelationRowSync
{
    /**
     * @param class-string<Model> $model 使用软删除的关系模型
     * @param array<string, int|string> $owner 关系所属的定值列，如 ['role_id' => 5]
     * @param string $key 区分关系行的列，如 'dept_id'
     * @param array<int|string, array<string, mixed>> $rows 目标集合：$key 的值 => 需要保持一致的附加列
     * @param array<string, mixed> $insertDefaults 仅新插入行写入的列（如 created_at）
     * @param bool $modelEvents 新行逐条经模型创建（触发 created 事件），否则批量插入
     *
     * 读取现有集合前先锁住所属行（见 OwnerLock），并发的整组替换按所属串行化，不会得到两次提交的并集。
     */
    public static function sync(string $model, array $owner, string $key, array $rows, array $insertDefaults = [], bool $modelEvents = false): void
    {
        Db::transaction(static function () use ($model, $owner, $key, $rows, $insertDefaults, $modelEvents): void {
            OwnerLock::lockOwner($owner);
            self::replace($model, $owner, $key, $rows, $insertDefaults, $modelEvents);
        });
    }

    private static function replace(string $model, array $owner, string $key, array $rows, array $insertDefaults, bool $modelEvents): void
    {
        $kept = [];
        $removed = [];
        foreach ($model::query()->where($owner)->orderBy('id')->get() as $row) {
            $value = (string) $row->getAttribute($key);
            if (!array_key_exists($value, $rows) || isset($kept[$value])) {
                $removed[] = $row->getKey();
                continue;
            }
            $kept[$value] = true;
            $changes = [];
            foreach ($rows[$value] as $column => $expected) {
                if ((string) $row->getAttribute($column) !== (string) $expected) $changes[$column] = $expected;
            }
            if ($changes !== []) $model::query()->whereKey($row->getKey())->update($changes);
        }
        if ($removed !== []) $model::query()->whereKey($removed)->delete();
        $inserts = [];
        foreach ($rows as $value => $attributes) {
            if (!isset($kept[(string) $value])) $inserts[] = $owner + [$key => $value] + $attributes + $insertDefaults;
        }
        if ($inserts === []) return;
        if (!$modelEvents) {
            $model::query()->insert($inserts);
            return;
        }
        foreach ($inserts as $attributes) $model::query()->forceCreate($attributes);
    }

    /** @param list<int|string> $values @return array<int|string, array{}> */
    public static function keys(array $values): array
    {
        return array_fill_keys(array_values(array_unique(array_map('intval', $values))), []);
    }
}
