<?php

declare(strict_types=1);

namespace app\common\service\member;

use app\common\model\MemberApiSession;
use app\common\model\MemberGroupRelation;
use app\common\model\MemberTagRelation;
use Illuminate\Database\Query\Expression;

/**
 * 会员删除 / 恢复 / 彻底删除时的连带处理。
 *
 * - 软删除会员：分组、标签关联写入与会员完全相同的 deleted_at（同一事务内、同一个时间值）；
 *   接口会话吊销并软删除，已登录设备立即失效。
 * - 恢复会员：只恢复 deleted_at 与会员删除时间相同的关联，也就是这次删除带走的那些；
 *   编辑时移除的关联更早就已删除（时间不同），保持删除。同一会员 / 分组已有有效关联时跳过。会话不恢复。
 * - 彻底删除会员：物理删除它的全部关联行（含已软删除的），避免外键 / 孤儿行。
 */
final class MemberCascade
{
    /** @var array<class-string, string> 关联模型 => 被关联的列 */
    private const RELATIONS = [MemberGroupRelation::class => 'group_id', MemberTagRelation::class => 'tag_id'];

    public static function afterSoftDelete(int $memberId, string $deletedAt): void
    {
        foreach (array_keys(self::RELATIONS) as $class) {
            $model = new $class();
            $columns = [$model->getDeletedAtColumn() => $deletedAt];
            if ($model->hasDeletedToken()) {
                $columns[$model->getDeletedTokenColumn()] = new Expression($model->getConnection()->getQueryGrammar()->wrap($model->getKeyName()));
            }
            $class::query()->where('member_id', $memberId)->update($columns);
        }
        MemberApiSession::query()->where('member_id', $memberId)->whereNull('revoked_at')->update(['revoked_at' => $deletedAt]);
        MemberApiSession::query()->where('member_id', $memberId)->delete();
    }

    public static function afterRestore(int $memberId, ?string $deletedAt): void
    {
        if ($deletedAt === null || $deletedAt === '') {
            return;
        }
        foreach (self::RELATIONS as $class => $key) {
            $rows = $class::onlyTrashed()->where('member_id', $memberId)->where('deleted_at', $deletedAt)->orderBy('id')->get();
            foreach ($rows as $row) {
                if ($class::query()->where('member_id', $memberId)->where($key, $row->getAttribute($key))->exists()) {
                    continue;
                }
                $row->restore();
            }
        }
    }

    public static function purgeRelations(int $memberId): void
    {
        foreach (array_keys(self::RELATIONS) as $class) {
            $class::withTrashed()->where('member_id', $memberId)->forceDelete();
        }
    }
}
