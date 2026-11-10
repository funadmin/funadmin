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
            $deletedAt = $member->getRawOriginal($member->getDeletedAtColumn());
            $member->cascadeRestoringDeletedAt = $deletedAt === null ? null : (string) $deletedAt;
        });
        static::restored(static function (self $member): void {
            MemberCascade::afterRestore((int) $member->getKey(), $member->cascadeRestoringDeletedAt);
            $member->cascadeRestoringDeletedAt = null;
        });
    }

    /** 会员行与关联、会话的标记在同一事务内写入（彻底删除同理）。 */
    public function delete()
    {
        return $this->getConnection()->transaction(fn () => parent::delete());
    }
}
