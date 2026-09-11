<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\IdentityUser;
use Ramsey\Uuid\Uuid;

/**
 * 统一身份用户写入服务；所有操作都显式接收 tenantId。
 */
class IdentityUserService
{
    public function create(int $tenantId, string $realm, int $sourceId, array $attributes): IdentityUser
    {
        return IdentityUser::create([
            'tenant_id' => $tenantId,
            'public_id' => Uuid::uuid4()->toString(),
            'realm' => $realm,
            'source_id' => $sourceId,
            ...$this->attributes($attributes),
        ]);
    }

    public function update(int $tenantId, int $userId, array $attributes): IdentityUser
    {
        $user = IdentityUser::forTenant($tenantId)->where('id', $userId)->findOrFail();
        $user->save($this->attributes($attributes));
        return $user;
    }

    public function assertCanCreateOidcSession(int $tenantId, int $userId): void
    {
        $user = IdentityUser::forTenant($tenantId)->where('id', $userId)->find();
        if (!$user || (int) $user->status !== 1) {
            throw new \DomainException('统一身份已禁用，不能创建 OIDC session');
        }
    }

    private function attributes(array $attributes): array
    {
        return [
            'username' => trim((string) ($attributes['username'] ?? '')),
            'display_name' => trim((string) ($attributes['display_name'] ?? $attributes['username'] ?? '')),
            'email' => $this->nullable($attributes['email'] ?? null),
            'mobile' => $this->nullable($attributes['mobile'] ?? null),
            'avatar' => $this->nullable($attributes['avatar'] ?? null),
            'locale' => $this->nullable($attributes['locale'] ?? null) ?? 'zh-CN',
            'status' => (int) ($attributes['status'] ?? 1) === 1 ? 1 : 0,
            'last_login_at' => $attributes['last_login_at'] ?? null,
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }
}
