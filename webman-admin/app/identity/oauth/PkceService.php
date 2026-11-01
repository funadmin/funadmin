<?php

declare(strict_types=1);

namespace app\identity\oauth;

use InvalidArgumentException;

/**
 * 实现 RFC 7636 S256 PKCE，所有授权码客户端均强制使用。
 */
final class PkceService
{
    public static function challenge(string $verifier): string
    {
        if (preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier) !== 1) {
            throw new InvalidArgumentException('invalid code_verifier');
        }

        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function verify(string $verifier, string $challenge): bool
    {
        try {
            return hash_equals($challenge, self::challenge($verifier));
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
