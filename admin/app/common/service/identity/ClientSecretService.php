<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\ClientSecret;
use app\common\model\identity\OAuthClient;
use DomainException;
use RuntimeException;
use support\Db;

final class ClientSecretService
{
    public static function generateSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function encodeForStorage(string $secret): array
    {
        if (strlen($secret) < 43) throw new RuntimeException('OAuth client secret 熵不足');
        $hash = password_hash($secret, PASSWORD_ARGON2ID);
        if (!is_string($hash)) throw new RuntimeException('OAuth client secret 哈希失败');
        return ['secret_prefix' => substr($secret, 0, 12), 'secret_hash' => $hash];
    }

    public static function verifyEncoded(string $secret, string $prefix, string $hash): bool
    {
        $candidatePrefix = substr($secret, 0, strlen($prefix));
        $prefixMatches = hash_equals($prefix, $candidatePrefix);
        $hashMatches = password_verify($secret, $hash);
        return $prefixMatches && $hashMatches;
    }

    public function list(int $tenantId, int $clientId): array
    {
        if (!OAuthClient::forTenant($tenantId)->where('id', $clientId)->first()) throw new DomainException('OAuth client 不存在或跨租户', 404);
        return ClientSecret::forTenant($tenantId)->where('client_id', $clientId)->orderByDesc('id')->get()->makeHidden(['secret_hash'])->toArray();
    }

    public function rotate(int $tenantId, int $clientId, ?string $expiresAt = null): array
    {
        if ($expiresAt !== null) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $expiresAt);
            if (!$date || $date->format('Y-m-d H:i:s') !== $expiresAt || $date->getTimestamp() <= time()) {
                throw new \support\validation\ValidationException('有效期必须为将来的日期时间');
            }
        }
        $plain = self::generateSecret();
        $encoded = self::encodeForStorage($plain);
        $metadata = Db::transaction(function () use ($tenantId, $clientId, $expiresAt, $encoded): array {
            $client = OAuthClient::forTenant($tenantId)->where('id', $clientId)->lockForUpdate()->first();
            if (!$client || $client->client_type === 'public' || $client->status !== 'active') throw new DomainException('OAuth client 不存在、跨租户、已禁用或不支持 secret', 422);
            $active = ClientSecret::forTenant($tenantId)->where('client_id', $clientId)->whereNull('revoked_at')->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            })->lockForUpdate()->orderBy('id')->get()->toArray();
            while (count($active) >= 2) {
                $oldest = array_shift($active);
                ClientSecret::forTenant($tenantId)->where('id', (int) $oldest['id'])->update(['revoked_at' => date('Y-m-d H:i:s')]);
            }
            $record = new ClientSecret();
            $record->forceFill(['tenant_id' => $tenantId, 'client_id' => $clientId, 'secret_prefix' => $encoded['secret_prefix'], 'secret_hash' => $encoded['secret_hash'], 'expires_at' => $expiresAt])->save();
            return ['id' => (int) $record->id, 'prefix' => $encoded['secret_prefix'], 'expiresAt' => $expiresAt];
        });
        return $metadata + ['secret' => $plain];
    }

    public function verify(int $tenantId, int $clientId, string $secret): bool
    {
        return Db::transaction(function () use ($tenantId, $clientId, $secret): bool {
            $client = OAuthClient::forTenant($tenantId)->where('id', $clientId)->lockForUpdate()->first();
            if (!$client
                || !in_array((string) $client->client_type, ['confidential', 'machine'], true)
                || (string) $client->token_endpoint_auth_method !== 'client_secret_basic'
                || (string) $client->status !== 'active') {
                return false;
            }
            $prefix = substr($secret, 0, 12);
            $records = ClientSecret::forTenant($tenantId)->where('client_id', $clientId)->where('secret_prefix', $prefix)->whereNull('revoked_at')->get();
            $valid = false;
            foreach ($records as $record) {
                $expiresAt = $record->getAttribute('expires_at');
                $notExpired = $expiresAt === null || strtotime((string) $expiresAt) > time();
                $valid = self::verifyEncoded($secret, (string) $record->getAttribute('secret_prefix'), (string) $record->getAttribute('secret_hash')) && $notExpired || $valid;
            }
            return $valid;
        });
    }

    public function revoke(int $tenantId, int $clientId, int $secretId): void
    {
        $updated = ClientSecret::forTenant($tenantId)->where('client_id', $clientId)->where('id', $secretId)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
        if ($updated !== 1) throw new DomainException('secret 不存在、跨租户或已吊销', 404);
    }
}
