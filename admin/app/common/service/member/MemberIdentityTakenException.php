<?php

declare(strict_types=1);

namespace app\common\service\member;

/** 会员标识被占用：$cooling 为 true 表示占用者是冷静期内已注销的会员。 */
final class MemberIdentityTakenException extends \InvalidArgumentException
{
    public function __construct(
        public readonly string $field,
        public readonly bool $cooling,
        string $message,
    ) {
        parent::__construct($message, 422);
    }
}
