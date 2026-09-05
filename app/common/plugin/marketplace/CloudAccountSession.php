<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

use app\common\plugin\marketplace\dto\CloudAccountDto;
use InvalidArgumentException;

final class CloudAccountSession
{
    private const KEY = 'plugin_marketplace';

    public function __construct(private readonly SessionStore $store)
    {
    }

    public function login(CloudAccountDto $account, string $accessToken): void
    {
        if ($accessToken === '') {
            throw new InvalidArgumentException('云 access token 不能为空');
        }
        $this->store->set(self::KEY, [
            'account' => $account->toSession(),
            'access_token' => $accessToken,
        ]);
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

    public function logout(): void
    {
        $this->store->delete(self::KEY);
    }

    private function state(): array
    {
        $state = $this->store->get(self::KEY);
        return is_array($state) ? $state : [];
    }
}
