<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

use app\common\plugin\marketplace\dto\CloudAccountDto;
use InvalidArgumentException;

final class CloudAccountSession
{
    private const KEY = 'plugin_marketplace';

    /** @param null|callable(): int $clock */
    public function __construct(private readonly SessionStore $store, private readonly mixed $clock = null)
    {
    }

    public function login(
        CloudAccountDto $account,
        string $accessToken,
        string $refreshToken = '',
        ?int $expiresAt = null
    ): void {
        if ($accessToken === '') {
            throw new InvalidArgumentException('云 access token 不能为空');
        }
        $this->store->set(self::KEY, [
            'account' => $account->toSession(),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
        ]);
    }

    public function rotate(string $accessToken, string $refreshToken, int $expiresAt): void
    {
        $account = $this->account();
        if ($account === null) {
            throw new InvalidArgumentException('云账号会话不存在');
        }
        $this->login($account, $accessToken, $refreshToken, $expiresAt);
    }

    public function account(): ?CloudAccountDto
    {
        $account = $this->state()['account'] ?? null;
        if (!is_array($account) || (int) ($account['id'] ?? 0) < 1) {
            return null;
        }
        return new CloudAccountDto(
            (int) $account['id'],
            (string) ($account['username'] ?? ''),
            (string) ($account['nickname'] ?? ''),
            (string) ($account['avatar'] ?? '')
        );
    }

    public function token(): string
    {
        return (string) ($this->state()['access_token'] ?? '');
    }

    public function refreshToken(): string
    {
        return (string) ($this->state()['refresh_token'] ?? '');
    }

    public function expiresAt(): ?int
    {
        $expiresAt = $this->state()['expires_at'] ?? null;
        return is_int($expiresAt) ? $expiresAt : (is_numeric($expiresAt) ? (int) $expiresAt : null);
    }

    public function logout(): void
    {
        $this->store->delete(self::KEY);
    }

    private function state(): array
    {
        $state = $this->store->get(self::KEY);
        if (!is_array($state)) {
            return [];
        }
        $expiresAt = $state['expires_at'] ?? null;
        if ($expiresAt !== null && (int) $expiresAt <= $this->now()) {
            $this->logout();
            return [];
        }
        return $state;
    }

    private function now(): int
    {
        return is_callable($this->clock) ? (int) ($this->clock)() : time();
    }
}
