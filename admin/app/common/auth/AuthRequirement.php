<?php

declare(strict_types=1);

namespace app\common\auth;

/**
 * 某个控制器方法的访问要求。
 *
 * login=false：无需登录；permission=false：登录即可、不校验权限；
 * code：注解声明的权限码（null 表示未声明，由各端自行决定，如后台按资源查库）。
 */
final readonly class AuthRequirement
{
    public function __construct(
        public bool $login,
        public bool $permission,
        public string|array|null $code
    ) {
    }

    public static function strictest(): self
    {
        return new self(true, true, null);
    }
}
