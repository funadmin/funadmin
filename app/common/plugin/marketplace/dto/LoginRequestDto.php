<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

use InvalidArgumentException;

final class LoginRequestDto
{
    public function __construct(public readonly string $account, public readonly string $password)
    {
        if (trim($account) === '' || $password === '') {
            throw new InvalidArgumentException('云账号和密码不能为空');
        }
    }
}
