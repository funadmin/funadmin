<?php

declare(strict_types=1);

namespace app\common\service\member;

/** 会员不能恢复（例如会员等级已删除）：code 422。 */
final class MemberRestoreBlockedException extends \DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 422);
    }
}
