<?php

declare(strict_types=1);

namespace app\common\service\identity;

use RuntimeException;

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
            if (file_put_contents($temporary, $privateKey, LOCK_EX) === false || !chmod($temporary, 0600) || !rename($temporary, $path) || !chmod($path, 0600)) throw new RuntimeException('无法安全写入 RS256 私钥');
            $jwk = ['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $kid, 'n' => self::base64Url($details['rsa']['n']), 'e' => self::base64Url($details['rsa']['e'])];
            sodium_memzero($privateKey);
            return ['kid' => $kid, 'algorithm' => 'RS256', 'status' => 'pending', 'private_key_ref' => 'file://' . $path, 'public_jwk' => json_encode($jwk, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)];
        } finally {
            umask($oldMask);
        }
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
