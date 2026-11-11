<?php
declare(strict_types=1);
namespace app\cms\service;
use Illuminate\Database\Eloquent\Model;
use support\Db;

/**
 * 关系行按差异同步（与核心 RelationRowSync 相同的规则）：只软删除移除的行、只插入缺少的行，未变化的行不动。
 * 整组删除再插入会让每次保存都新增一整套软删除行，并让它们混进回收站。
 *
 * 所属行由调用方先加锁（CMS 的站点权限写入都先锁站点行，见 SiteService::lock），本类只要求处于事务中。
 */
final class RelationSync
{
    /**
     * @param class-string<Model> $model 使用软删除的关系模型
     * @param array<string,string> $owner 关系所属的定值列，如 ['site_id'=>1,'role_id'=>5]
     * @param string $key 区分关系行的列
     * @param list<string> $values 目标集合
     */
    public static function sync(string $model, array $owner, string $key, array $values): void
    {
        if (Db::connection()->transactionLevel() === 0) throw new \LogicException('Relation sync requires a transaction and a locked owner');
        $wanted = array_fill_keys(array_map('strval', $values), true);
        $kept = [];
        $removed = [];
        foreach ($model::query()->where($owner)->orderBy('id')->get() as $row) {
            $value = (string)$row->getAttribute($key);
            if (!isset($wanted[$value]) || isset($kept[$value])) {
                $removed[] = $row->getKey();
                continue;
            }
            $kept[$value] = true;
        }
        if ($removed !== []) $model::query()->whereKey($removed)->delete();
        $inserts = [];
        foreach (array_keys($wanted) as $value) {
            if (!isset($kept[(string)$value])) $inserts[] = $owner + [$key => (string)$value];
        }
        if ($inserts !== []) $model::query()->insert($inserts);
    }
}
