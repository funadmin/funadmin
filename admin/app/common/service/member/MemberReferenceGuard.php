<?php

declare(strict_types=1);

namespace app\common\service\member;

use app\common\model\Member;
use app\common\model\MemberGroupRelation;
use app\common\model\MemberTagRelation;

/**
 * 会员等级 / 分组 / 标签的删除保护。
 *
 * - 软删除：还被未删除会员使用时拒绝（软删除后外键拦不住，会员会挂在已删除的记录上）。
 *   分组看有效的 member_group_relation 行（只算未删除会员），并兼容旧的 member.group_id 文本列；标签看 member_tag_relation。
 * - 彻底删除：同样要求没有未删除会员使用；等级还要求回收站中也没有会员引用（fk_member_level ON DELETE RESTRICT，level_id 非空）。
 *   分组 / 标签的剩余关联行（已软删除的关联、回收站会员的关联）由 purgeRelations 在同一事务里物理删除，
 *   否则 fk_member_group_relation_group / fk_member_tag_relation_tag（ON DELETE RESTRICT）会拒绝删除。
 */
final class MemberReferenceGuard
{
    public const LEVEL = 'level';
    public const GROUP = 'group';
    public const TAG = 'tag';

    /** @param list<int|string> $ids */
    public static function blockingMessage(string $kind, array $ids, bool $force): ?string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return null;
        }
        return match ($kind) {
            self::LEVEL => match (true) {
                Member::query()->whereIn('level_id', $ids)->exists() => '会员等级仍被会员使用，不能删除',
                $force && Member::onlyTrashed()->whereIn('level_id', $ids)->exists() => '会员等级仍被回收站中的会员引用，不能彻底删除，请先彻底删除这些会员',
                default => null,
            },
            self::GROUP => self::relationInUse(MemberGroupRelation::class, 'group_id', $ids) || self::legacyGroupColumnInUse($ids)
                ? '会员组仍被会员使用，不能删除'
                : null,
            self::TAG => self::relationInUse(MemberTagRelation::class, 'tag_id', $ids)
                ? '会员标签仍被会员使用，不能删除'
                : null,
            default => throw new \InvalidArgumentException('未知的会员引用类型：' . $kind),
        };
    }

    /** @param list<int|string> $ids */
    public static function assertDeletable(string $kind, array $ids, bool $force): void
    {
        if ($message = self::blockingMessage($kind, $ids, $force)) {
            throw new \DomainException($message, 422);
        }
    }

    /**
     * 彻底删除分组 / 标签前物理删除引用它们的剩余关联行（调用方负责事务）。
     *
     * @param list<int|string> $ids
     */
    public static function purgeRelations(string $kind, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }
        match ($kind) {
            self::GROUP => MemberGroupRelation::withTrashed()->whereIn('group_id', $ids)->forceDelete(),
            self::TAG => MemberTagRelation::withTrashed()->whereIn('tag_id', $ids)->forceDelete(),
            default => null,
        };
    }

    /** @param class-string $relation */
    private static function relationInUse(string $relation, string $column, array $ids): bool
    {
        return $relation::query()->whereIn($column, $ids)->whereIn('member_id', Member::query()->select('id'))->exists();
    }

    /** 旧数据的 member.group_id 文本列（单个编号或逗号分隔），新代码不再写入；表上没有该列时跳过。 */
    private static function legacyGroupColumnInUse(array $ids): bool
    {
        $member = new Member();
        if (!$member->getConnection()->getSchemaBuilder()->hasColumn($member->getTable(), 'group_id')) {
            return false;
        }
        return Member::query()->where(static function ($query) use ($ids): void {
            foreach ($ids as $id) {
                $query->orWhere('group_id', (string) $id)
                    ->orWhere('group_id', 'like', $id . ',%')
                    ->orWhere('group_id', 'like', '%,' . $id)
                    ->orWhere('group_id', 'like', '%,' . $id . ',%');
            }
        })->exists();
    }
}
