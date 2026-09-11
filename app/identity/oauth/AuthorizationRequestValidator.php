<?php

declare(strict_types=1);

namespace app\identity\oauth;

use InvalidArgumentException;

/**
 * 在进入持久化前执行 OAuth/OIDC 授权请求协议校验。
 */
final class AuthorizationRequestValidator
{
    public function validateProtocol(array $input): array
    {
        if (($input['response_type'] ?? '') !== 'code') {
            throw new InvalidArgumentException('unsupported_response_type');
        }
        $scopes = array_values(array_unique(array_filter(preg_split('/\s+/', trim((string) ($input['scope'] ?? ''))) ?: [])));
        if ($scopes === []) {
            throw new InvalidArgumentException('invalid_scope');
        }
        $nonce = array_key_exists('nonce', $input) ? (string) $input['nonce'] : null;
        if ((in_array('openid', $scopes, true) && ($nonce === null || $nonce === '')) || ($nonce !== null && strlen($nonce) > 255)) {
            throw new InvalidArgumentException('invalid_request');
        }
        $challenge = (string) ($input['code_challenge'] ?? '');
        if (($input['code_challenge_method'] ?? '') !== 'S256' || preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge) !== 1) {
            throw new InvalidArgumentException('invalid_request');
        }
        $state = array_key_exists('state', $input) ? (string) $input['state'] : null;
        if ($state !== null && strlen($state) > 2048) {
            throw new InvalidArgumentException('invalid_request');
        }

        return ['response_type' => 'code', 'scopes' => $scopes, 'state' => $state, 'nonce' => $nonce, 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'];
    }
}
