<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\IdentityTenant;
use app\common\model\identity\OidcSigningKey;
use DomainException;
use RuntimeException;
use Throwable;
use think\facade\Db;

final class SigningKeyService
{
    public function __construct(private readonly ?string $secretDirectory = null)
    {
    }

    public function generateKeyMaterial(int $tenantId): array
    {
        if ($tenantId <= 0) throw new RuntimeException('租户 ID 无效');
        $directory = $this->secretDirectory ?? runtime_path('secret' . DIRECTORY_SEPARATOR . 'oidc');
        $oldMask = umask(0077);
        try {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('无法创建 OIDC 私钥目录');
            chmod($directory, 0700);
            $resource = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
            if ($resource === false || !openssl_pkey_export($resource, $privateKey)) throw new RuntimeException('无法生成 RS256 私钥');
            $details = openssl_pkey_get_details($resource);
            if (!is_array($details) || !isset($details['rsa']['n'], $details['rsa']['e'])) throw new RuntimeException('无法读取 RS256 公钥');
            $kid = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
            $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tenant-' . $tenantId . '-' . $kid . '.pem';
            $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
            try {
                if (file_put_contents($temporary, $privateKey, LOCK_EX) === false || !chmod($temporary, 0600) || !rename($temporary, $path) || !chmod($path, 0600)) throw new RuntimeException('无法安全写入 RS256 私钥');
            } catch (Throwable $exception) {
                if (is_file($temporary)) unlink($temporary);
                if (is_file($path)) unlink($path);
                throw $exception;
            }
            $jwk = ['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $kid, 'n' => self::base64Url($details['rsa']['n']), 'e' => self::base64Url($details['rsa']['e'])];
            sodium_memzero($privateKey);
            return ['kid' => $kid, 'algorithm' => 'RS256', 'status' => 'pending', 'private_key_ref' => 'file://' . $path, 'public_jwk' => json_encode($jwk, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)];
        } finally {
            umask($oldMask);
        }
    }

    public function rotate(int $tenantId, int $publishWindowSeconds = 86400): array
    {
        if ($publishWindowSeconds < 300) throw new RuntimeException('旧签名 key publish 窗口不得少于 300 秒');
        $material = $this->generateKeyMaterial($tenantId);
        try {
            return Db::transaction(function () use ($tenantId, $publishWindowSeconds, $material): array {
                if (!IdentityTenant::forTenant($tenantId)->lock(true)->find()) throw new DomainException('租户不存在或跨租户');
                $now = date('Y-m-d H:i:s');
                $publishUntil = date('Y-m-d H:i:s', time() + $publishWindowSeconds);
                OidcSigningKey::forTenant($tenantId)->where('status', 'retiring')->where('publish_until', '<=', $now)->update(['status' => 'retired', 'retired_at' => $now]);
                $active = OidcSigningKey::forTenant($tenantId)->where('status', 'active')->lock(true)->select()->toArray();
                if (count($active) > 1) throw new DomainException('检测到多个 active 签名 key，拒绝继续 rotation');
                $record = OidcSigningKey::create($material + ['tenant_id' => $tenantId]);
                if ($active !== []) {
                    OidcSigningKey::forTenant($tenantId)->where('id', (int) $active[0]['id'])->where('status', 'active')->update(['status' => 'retiring', 'publish_until' => $publishUntil]);
                }
                $activated = OidcSigningKey::forTenant($tenantId)->where('id', (int) $record->id)->where('status', 'pending')->update(['status' => 'active', 'activated_at' => $now]);
                if ($activated !== 1) throw new DomainException('签名 key 激活发生并发冲突');
                $activatedKey = OidcSigningKey::forTenant($tenantId)->where('id', (int) $record->id)->find();
                if (!$activatedKey) throw new DomainException('签名 key 激活后不可读取');
                return $activatedKey->toArray();
            });
        } catch (Throwable $exception) {
            $this->deleteKeyMaterial((string) $material['private_key_ref']);
            throw $exception;
        }
    }

    public function retireExpired(int $tenantId, int $limit = 100, bool $dryRun = false): array
    {
        $limit = max(1, min(1000, $limit));
        $ids = array_map('intval', OidcSigningKey::forTenant($tenantId)
            ->where('status', 'retiring')->whereNotNull('publish_until')
            ->where('publish_until', '<=', date('Y-m-d H:i:s'))->order('id')->limit($limit)->column('id'));
        if (!$dryRun && $ids !== []) {
            OidcSigningKey::forTenant($tenantId)->whereIn('id', $ids)->where('status', 'retiring')
                ->update(['status' => 'retired', 'retired_at' => date('Y-m-d H:i:s')]);
        }
        return ['tenant_id' => $tenantId, 'dry_run' => $dryRun, 'retired' => count($ids), 'ids' => $ids];
    }

    public function list(int $tenantId): array
    {
        return OidcSigningKey::forTenant($tenantId)->order('id', 'desc')->select()->toArray();
    }

    public function listPublishable(int $tenantId, ?string $at = null): array
    {
        $at ??= date('Y-m-d H:i:s');
        return OidcSigningKey::forTenant($tenantId)->where(function ($query) use ($at): void {
            $query->where('status', 'active')->whereOr(function ($retiring) use ($at): void {
                $retiring->where('status', 'retiring')->where('publish_until', '>', $at);
            });
        })->field('kid,algorithm,status,public_jwk,activated_at,publish_until')->order('id', 'desc')->select()->toArray();
    }

    public function deleteKeyMaterial(string $reference): void
    {
        if (!str_starts_with($reference, 'file://')) return;
        $path = substr($reference, 7);
        $directory = realpath($this->secretDirectory ?? runtime_path('secret' . DIRECTORY_SEPARATOR . 'oidc'));
        $parent = realpath(dirname($path));
        if ($directory === false || $parent === false || !hash_equals($directory, $parent) || is_link($path)) throw new RuntimeException('拒绝删除不安全的 OIDC 私钥路径');
        if (is_file($path) && !unlink($path)) throw new RuntimeException('OIDC 私钥回滚删除失败');
    }

    public function readPrivateKey(string $reference): string
    {
        if (str_starts_with($reference, 'env://')) {
            $value = getenv(substr($reference, 6));
            if (!is_string($value) || $value === '') throw new RuntimeException('OIDC 私钥环境引用不可用');
            return $value;
        }
        if (!str_starts_with($reference, 'file://')) throw new RuntimeException('OIDC 私钥只支持 env/file 引用');
        $path = substr($reference, 7);
        if (!is_file($path) || is_link($path) || (fileperms($path) & 0777) !== 0600) throw new RuntimeException('OIDC 私钥文件不存在或权限不安全');
        $value = file_get_contents($path);
        if (!is_string($value) || $value === '') throw new RuntimeException('OIDC 私钥文件不可读');
        return $value;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
