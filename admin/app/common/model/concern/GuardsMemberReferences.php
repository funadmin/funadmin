<?php

declare(strict_types=1);

namespace app\common\model\concern;

use app\common\service\member\MemberReferenceGuard;

/**
 * 会员等级 / 分组 / 标签模型：还被未删除会员使用时拒绝删除（DomainException，code 422）；
 * 彻底删除时在同一事务里先物理删除剩余的关联行。见 MemberReferenceGuard。需与 LaravelSoftDelete 一起使用。
 */
trait GuardsMemberReferences
{
    /** MemberReferenceGuard::LEVEL / GROUP / TAG */
    abstract protected function memberReferenceKind(): string;

    public static function bootGuardsMemberReferences(): void
    {
        static::deleting(static function (self $model): void {
            $force = $model->isForceDeleting();
            MemberReferenceGuard::assertDeletable($model->memberReferenceKind(), [$model->getKey()], $force);
            if ($force) {
                MemberReferenceGuard::purgeRelations($model->memberReferenceKind(), [$model->getKey()]);
            }
        });
    }

    public function delete()
    {
        return $this->getConnection()->transaction(fn () => parent::delete());
    }
}
