<?php

declare(strict_types=1);

namespace app\backend\service;

use RuntimeException;

/** 使用服务端可信发布者公钥验证升级 manifest。 */
final class UpgradeManifestVerifier
{
    public function __construct(
        private readonly string $publicKey,
        private readonly string $algorithm = 'ed25519'
    ) {
    }

    public function verify(array $manifest): array
    {
        if ($this->algorithm !== 'ed25519') {
            throw new RuntimeException('升级签名算法不受支持');
        }
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('服务器未安装 Sodium 扩展，禁止执行升级');
        }
        $publicKey = base64_decode(trim($this->publicKey), true);
        if (!is_string($publicKey) || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('升级可信公钥未配置或格式无效');
        }
        $signature = $manifest['signature'] ?? null;
        if (!is_array($signature) || ($signature['algorithm'] ?? '') !== $this->algorithm) {
            throw new RuntimeException('升级 manifest 缺少可信签名');
        }
        $value = base64_decode(trim((string) ($signature['value'] ?? '')), true);
        if (!is_string($value) || strlen($value) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new RuntimeException('升级 manifest 签名格式无效');
        }
        if (!sodium_crypto_sign_verify_detached($value, $this->canonicalPayload($manifest), $publicKey)) {
            throw new RuntimeException('升级 manifest 签名验证失败');
        }
        return $manifest;
    }

    public function canonicalPayload(array $manifest): string
    {
        unset($manifest['signature']);
        $normalized = $this->sortRecursively($manifest);
        return json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function sortRecursively(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }
        return $value;
    }
}
