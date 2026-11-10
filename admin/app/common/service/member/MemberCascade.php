<?php

declare(strict_types=1);

namespace app\common\service\member;

use app\admin\system\model\MemberGroup;
use app\admin\system\model\MemberLevel;
use app\common\model\MemberApiSession;
use app\common\model\MemberApiSessionUsed;
use app\common\model\MemberTag;
use app\common\model\MemberGroupRelation;
use app\common\model\MemberTagRelation;
use Illuminate\Database\Query\Expression;

/**
 * 会员删除 / 恢复 / 彻底删除时的连带处理。
 *
 * - 软删除会员：分组、标签关联写入与会员完全相同的 deleted_at（同一事务内、同一个时间值）；
 *   接口会话吊销并软删除，已登录设备立即失效。
 * - 恢复会员：会员等级已删除（或不存在）时拒绝恢复（MemberRestoreBlockedException，422），避免未删除会员挂在已删除等级上。
 *   只恢复 deleted_at 与会员删除时间相同的关联，也就是这次删除带走的那些；编辑时移除的关联更早就已删除（时间不同），保持删除。
 *   同一会员 / 分组已有有效关联时跳过；分组 / 标签本身已删除的关联也跳过（计入返回的跳过数）。会话不恢复。
 * - 彻底删除会员：物理删除它的全部关联行（含已软删除的）、接口会话及其已使用的 refresh 记录。
 */
final class MemberCascade
{
    /** @var array<class-string, string> 关联模型 => 被关联的列 */
    private const RELATIONS = [MemberGroupRelation::class => 'group_id', MemberTagRelation::class => 'tag_id'];

    /** @var array<class-string, class-string> 关联模型 => 被关联记录的模型 */
    private const TARGETS = [MemberGroupRelation::class => MemberGroup::class, MemberTagRelation::class => MemberTag::class];

    /** 恢复前检查：会员等级必须是未删除的记录。 */
    public static function assertRestorable(int $levelId): void
    {
        if ($levelId <= 0 || !MemberLevel::query()->whereKey($levelId)->exists()) {
            throw new MemberRestoreBlockedException('会员等级已删除，请先恢复等级或修改会员等级');
        }
    }

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

    /** @return int 因分组 / 标签已删除而未恢复的关联数 */
    public static function afterRestore(int $memberId, ?string $deletedAt): int
    {
        if ($deletedAt === null || $deletedAt === '') {
            return 0;
        }
        $skipped = 0;
        foreach (self::RELATIONS as $class => $key) {
            $rows = $class::onlyTrashed()->where('member_id', $memberId)->where('deleted_at', $deletedAt)->orderBy('id')->get();
            $target = self::TARGETS[$class];
            $liveTargets = array_flip(array_map('intval', $target::query()->whereKey($rows->pluck($key)->all())->pluck('id')->all()));
            foreach ($rows as $row) {
                if (!isset($liveTargets[(int) $row->getAttribute($key)])) {
                    $skipped++;
                    continue;
                }
                if ($class::query()->where('member_id', $memberId)->where($key, $row->getAttribute($key))->exists()) {
                    continue;
                }
                $row->restore();
            }
        }
        return $skipped;
    }

    /** 彻底删除会员前调用（调用方负责事务）：关联行、接口会话、会话已使用的 refresh 记录全部物理删除。 */
    public static function purgeRelations(int $memberId): void
    {
        foreach (array_keys(self::RELATIONS) as $class) {
            $class::withTrashed()->where('member_id', $memberId)->forceDelete();
        }
        $sessionIds = MemberApiSession::withTrashed()->where('member_id', $memberId)->pluck('session_id')->all();
        foreach (array_chunk($sessionIds, 500) as $chunk) {
            MemberApiSessionUsed::withTrashed()->whereIn('session_id', $chunk)->forceDelete();
        }
        MemberApiSession::withTrashed()->where('member_id', $memberId)->forceDelete();
    }
}
