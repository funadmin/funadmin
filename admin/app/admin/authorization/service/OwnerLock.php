<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use app\admin\authentication\model\Admin;
use app\admin\authorization\model\AuthGroup;
use app\admin\system\model\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * 整组替换授权/关系集合前锁住所属的管理员、角色或会员行（SELECT ... FOR UPDATE），
 * 使同一所属的并发替换串行化：只锁已有关系行挡不住"原集合为空、各自插入不同行"的并集结果。
 * 必须在事务内调用；每次只锁一个所属行，且先锁所属行再读写关系行，顺序固定，避免死锁。
 */
final class OwnerLock
{
    /** 关系表中的所属列 => 所属模型 */
    private const OWNERS = [
        'role_id' => AuthGroup::class,
        'admin_id' => Admin::class,
        'member_id' => Member::class,
    ];

    /** @param class-string<Model> $model */
    public static function lock(string $model, int $id): void
    {
        self::query($model, $id)->first();
    }

    /** @param array<string, int|string> $owner 如 ['role_id' => 5] */
    public static function lockOwner(array $owner): void
    {
        if (count($owner) !== 1 || !isset(self::OWNERS[array_key_first($owner)])) {
            throw new InvalidArgumentException('关系同步缺少可加锁的所属行：' . implode(',', array_keys($owner)));
        }
        self::lock(self::OWNERS[array_key_first($owner)], (int) reset($owner));
    }

    /**
     * 加锁查询本身（不走全局作用域：已删除的所属行同样要锁住，且不依赖 deleted_at 列）。
     * @param class-string<Model> $model
     */
    public static function query(string $model, int $id): Builder
    {
        $instance = new $model();
        return $instance->newQueryWithoutScopes()->select($instance->getKeyName())->whereKey($id)->lockForUpdate();
    }
}
