<?php

declare(strict_types=1);

namespace app\common\service;

use InvalidArgumentException;

final class MemberInput
{
    public static function normalizeEmail(mixed $value): ?string
    {
        $email = strtolower(trim((string) $value));
        if ($email === '') {
            return null;
        }
        if (strlen($email) > 60 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('邮箱格式错误或超过 60 个字符');
        }
        return $email;
    }

    public static function normalizeIp(mixed $value): string
    {
        $ip = trim((string) $value);
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
    }
}