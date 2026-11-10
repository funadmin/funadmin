<?php

declare(strict_types=1);

namespace app\common\model\concern;

use app\common\service\member\MemberCascade;

/**
 * 会员模型的连带处理（见 MemberCascade）：删除在一个事务里完成会员与关联 / 会话的标记，
 * 恢复时带回这次删除的关联，彻底删除时清掉全部关联行。需与 LaravelSoftDelete 一起使用。
 */
trait CascadesMemberDeletion
{
    /** 恢复前的 deleted_at，restored 事件里据此找回同一次删除带走的关联。 */
    protected ?string $cascadeRestoringDeletedAt = null;

    /** 最近一次恢复时因分组 / 标签已删除而未恢复的关联数。 */
    public int $cascadeSkippedRelations = 0;

    public static function bootCascadesMemberDeletion(): void
    {
        static::deleting(static function (self $member): void {
            if ($member->isForceDeleting()) {
                MemberCascade::purgeRelations((int) $member->getKey());
            }
        });
        static::softDeleted(static function (self $member): void {
            MemberCascade::afterSoftDelete((int) $member->getKey(), (string) $member->getAttributes()[$member->getDeletedAtColumn()]);
        });
        static::restoring(static function (self $member): void {
            MemberCascade::assertRestorable((int) $member->getAttribute('level_id'));
            $deletedAt = $member->getRawOriginal($member->getDeletedAtColumn());
            $member->cascadeRestoringDeletedAt = $deletedAt === null ? null : (string) $deletedAt;
        });
        static::restored(static function (self $member): void {
            $member->cascadeSkippedRelations = MemberCascade::afterRestore((int) $member->getKey(), $member->cascadeRestoringDeletedAt);
            $member->cascadeRestoringDeletedAt = null;
        });
    }

    /** 会员行与关联、会话的标记在同一事务内写入（彻底删除同理）。 */
    public function delete()
    {
        return $this->getConnection()->transaction(fn () => parent::delete());
    }

    /**
     * 会员行与关联在同一事务内恢复：任一步失败整体回滚。
     * 使用类需写 `use LaravelSoftDelete, CascadesMemberDeletion { CascadesMemberDeletion::restore insteadof LaravelSoftDelete;
     * LaravelSoftDelete::restore as restoreSoftDeleted; }`。
     */
    public function restore()
    {
        $this->cascadeSkippedRelations = 0;
        return $this->getConnection()->transaction(fn () => $this->restoreSoftDeleted());
    }
}
