<?php

declare(strict_types=1);

namespace app\market\service;

use JsonException;
use RuntimeException;

/**
 * Ed25519 发布签名。签名载荷必须与客户端 PluginPackageDownloader::signaturePayload() 逐字节一致。
 */
final class MarketSigner
{
    private const KEY_FILE = 'keys/ed25519.secret';

    public static function available(): bool
    {
        return function_exists('sodium_crypto_sign_detached') && self::secretKey() !== null;
    }

    public static function publicKey(): string
    {
        $secret = self::secretKey();
        return $secret === null ? '' : base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret));
    }

    /** 密钥来源：环境变量优先（便于多实例共享），其次是插件私有存储中的密钥文件。 */
    public static function keySource(): string
    {
        if (self::envSecretKey() !== null) {
            return 'env';
        }
        return is_file(self::keyFile()) ? 'file' : 'none';
    }

    public static function generate(): string
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            throw new RuntimeException('服务器未启用 sodium 扩展，无法生成 Ed25519 密钥');
        }
        if (self::keySource() !== 'none') {
            throw new RuntimeException('签名密钥已存在；更换密钥会使已发布版本在客户端验签失败，请手动轮换');
        }
        $keypair = sodium_crypto_sign_keypair();
        $file = self::keyFile();
        MarketSettings::ensureDirectory(dirname($file), 0700);
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, base64_encode(sodium_crypto_sign_secretkey($keypair)), LOCK_EX) === false) {
            throw new RuntimeException('无法写入签名密钥');
        }
        chmod($temporary, 0600);
        if (!rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException('无法保存签名密钥');
        }
        return self::publicKey();
    }

    /**
     * @param array{code:string, code_version:string, database_capability:string, sha256:string, size:int, tree_hash:string} $meta
     */
    public static function sign(array $meta): string
    {
        $secret = self::secretKey();
        if ($secret === null) {
            throw new RuntimeException('尚未配置签名密钥，请先在市场设置中生成');
        }
        return base64_encode(sodium_crypto_sign_detached(self::payload($meta), $secret));
    }

    public static function verify(array $meta, string $signature): bool
    {
        $public = base64_decode(self::publicKey(), true);
        $raw = base64_decode($signature, true);
        if ($public === false || $public === '' || $raw === false || strlen($raw) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }
        return sodium_crypto_sign_verify_detached($raw, self::payload($meta), $public);
    }

    public static function payload(array $meta): string
    {
        try {
            return json_encode([
                'code' => (string) $meta['code'],
                'code_version' => (string) $meta['code_version'],
                'database_capability' => (string) $meta['database_capability'],
                'manifest_schema' => MarketProtocol::MANIFEST_SCHEMA,
                'package_format' => MarketProtocol::PACKAGE_FORMAT,
                'sha256' => (string) $meta['sha256'],
                'size' => (int) $meta['size'],
                'tree_hash' => (string) $meta['tree_hash'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('签名 metadata 无法规范化', 0, $exception);
        }
    }

    /** 下载链接 HMAC 密钥由签名私钥派生，避免再维护一份独立密钥。 */
    public static function downloadSecret(): string
    {
        $secret = self::secretKey();
        if ($secret === null) {
            throw new RuntimeException('尚未配置签名密钥');
        }
        return hash('sha256', 'funadmin-market-download|' . $secret, true);
    }

    private static function secretKey(): ?string
    {
        $env = self::envSecretKey();
        if ($env !== null) {
            return $env;
        }
        $file = self::keyFile();
        if (!is_file($file)) {
            return null;
        }
        return self::decodeSecret((string) file_get_contents($file));
    }

    private static function envSecretKey(): ?string
    {
        $value = trim((string) env('MARKET_SIGNING_SECRET_KEY', ''));
        return $value === '' ? null : self::decodeSecret($value);
    }

    private static function decodeSecret(string $value): ?string
    {
        if (!defined('SODIUM_CRYPTO_SIGN_SECRETKEYBYTES')) {
            return null;
        }
        $decoded = base64_decode(trim($value), true);
        return $decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ? $decoded : null;
    }

    private static function keyFile(): string
    {
        return MarketSettings::storagePath(self::KEY_FILE);
    }
}
