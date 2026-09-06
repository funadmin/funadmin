<?php

declare(strict_types=1);

namespace app\common\crud;

use Closure;
use RuntimeException;

/**
 * 签发并验证与生成计划绑定的一次性 HMAC 确认凭证。
 */
final class ConfirmationToken
{
    /** @var Closure(): int */
    private readonly Closure $clock;

    private readonly string $secret;
    private readonly string $nonceDirectory;

    public function __construct(
        private readonly string $projectRoot,
        ?string $secret = null,
        private readonly int $ttl = 300,
        ?callable $clock = null
    ) {
        if ($ttl < 1) {
            throw new RuntimeException('确认 token 有效期必须为正数');
        }
        $this->secret = $this->resolveSecret($secret);
        $this->clock = Closure::fromCallable($clock ?? static fn (): int => time());
        $this->nonceDirectory = rtrim($projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'crud-confirm-nonces';
    }

    public static function planDigest(array $plan): string
    {
        $source = [
            'definitionHash' => (string) ($plan['definitionHash'] ?? ''),
            'files' => array_map(static fn (array $file): array => [
                'path' => (string) ($file['path'] ?? ''),
                'status' => (string) ($file['status'] ?? ''),
                'hash' => (string) ($file['hash'] ?? ''),
                'previousHash' => $file['previousHash'] ?? null,
            ], $plan['files'] ?? []),
        ];
        return hash('sha256', CrudDefinition::canonicalJson($source));
    }

    public function issue(string $planDigest): string
    {
        $issuedAt = ($this->clock)();
        $payload = [
            'planDigest' => $planDigest,
            'issuedAt' => $issuedAt,
            'expiresAt' => $issuedAt + $this->ttl,
            'nonce' => bin2hex(random_bytes(16)),
        ];
        $encoded = self::base64UrlEncode(CrudDefinition::canonicalJson($payload));
        return $encoded . '.' . self::base64UrlEncode(hash_hmac('sha256', $encoded, $this->secret, true));
    }

    public function verify(string $token, string $planDigest): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new RuntimeException('确认 token 格式不合法');
        }
        [$encoded, $signature] = $parts;
        $actualSignature = self::base64UrlDecode($signature, '确认 token 签名编码不合法');
        $expectedSignature = hash_hmac('sha256', $encoded, $this->secret, true);
        if (!hash_equals($expectedSignature, $actualSignature)) {
            throw new RuntimeException('确认 token 签名无效');
        }
        $decoded = json_decode(self::base64UrlDecode($encoded, '确认 token payload 编码不合法'), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('确认 token payload 不合法');
        }
        foreach (['planDigest', 'issuedAt', 'expiresAt', 'nonce'] as $claim) {
            if (!array_key_exists($claim, $decoded)) {
                throw new RuntimeException('确认 token 缺少字段：' . $claim);
            }
        }
        if (!is_string($decoded['planDigest']) || !hash_equals($planDigest, $decoded['planDigest'])) {
            throw new RuntimeException('确认 token planDigest 不匹配');
        }
        if (!is_int($decoded['issuedAt']) || !is_int($decoded['expiresAt']) || $decoded['expiresAt'] <= $decoded['issuedAt']) {
            throw new RuntimeException('确认 token 时间字段不合法');
        }
        $now = ($this->clock)();
        if ($decoded['issuedAt'] > $now + 30 || $decoded['expiresAt'] < $now) {
            throw new RuntimeException('确认 token 已过期或尚未生效');
        }
        if (!is_string($decoded['nonce']) || preg_match('/^[a-f0-9]{32}$/', $decoded['nonce']) !== 1) {
            throw new RuntimeException('确认 token nonce 不合法');
        }
        if (is_file($this->noncePath($decoded['nonce']))) {
            throw new RuntimeException('确认 token 已使用');
        }
        return $decoded;
    }

    public function consume(string $nonce, int $expiresAt): void
    {
        $this->ensureNonceDirectory();
        $path = $this->noncePath($nonce);
        $oldMask = umask(0077);
        try {
            $handle = @fopen($path, 'x');
        } finally {
            umask($oldMask);
        }
        if ($handle === false) {
            throw new RuntimeException('确认 token 已使用');
        }
        try {
            if (!chmod($path, 0600)
                || fwrite($handle, (string) $expiresAt) === false
                || !fflush($handle)) {
                throw new RuntimeException('无法安全持久化确认 token nonce');
            }
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        } finally {
            fclose($handle);
        }
    }

    private function resolveSecret(?string $secret): string
    {
        $candidate = trim((string) $secret);
        if ($candidate === '' && function_exists('config')) {
            $configured = config('crud.confirm_secret');
            $candidate = is_string($configured) ? trim($configured) : '';
        }
        if ($candidate === '') {
            $environment = getenv('CRUD_CONFIRM_SECRET');
            $candidate = trim($environment === false ? '' : $environment);
        }
        if ($candidate !== '') {
            return $candidate;
        }
        $secretPath = rtrim($this->projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'crud-confirm.secret';
        $directory = dirname($secretPath);
        $this->ensureSecureDirectory($directory, '运行时密钥');
        clearstatcache(true, $secretPath);
        $existingStat = @lstat($secretPath);
        if ($existingStat !== false && (($existingStat['mode'] & 0170000) !== 0100000)) {
            throw new RuntimeException('运行时 CRUD 确认密钥必须是普通文件');
        }
        $oldMask = umask(0077);
        try {
            $handle = @fopen($secretPath, 'c+x');
            if ($handle === false && $existingStat !== false) {
                $handle = @fopen($secretPath, 'r+');
            }
        } finally {
            umask($oldMask);
        }
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('CRUD_CONFIRM_SECRET 缺失且运行时密钥不可用');
        }
        try {
            clearstatcache(true, $secretPath);
            $lockedStat = @lstat($secretPath);
            $handleStat = fstat($handle);
            if ($lockedStat === false || $handleStat === false
                || (($lockedStat['mode'] & 0170000) !== 0100000)
                || $lockedStat['dev'] !== $handleStat['dev']
                || $lockedStat['ino'] !== $handleStat['ino']) {
                throw new RuntimeException('运行时 CRUD 确认密钥权限或类型不安全');
            }
            if (!chmod($secretPath, 0600)) {
                throw new RuntimeException('无法将运行时 CRUD 确认密钥收紧为 0600');
            }
            $securedStat = fstat($handle);
            if ($securedStat === false || ($securedStat['mode'] & 0777) !== 0600) {
                throw new RuntimeException('运行时 CRUD 确认密钥必须为 0600');
            }
            rewind($handle);
            $stored = stream_get_contents($handle);
            if (is_string($stored) && trim($stored) !== '') {
                return trim($stored);
            }
            $generated = bin2hex(random_bytes(32));
            if (!ftruncate($handle, 0) || rewind($handle) === false
                || fwrite($handle, $generated) === false || !fflush($handle)) {
                throw new RuntimeException('无法持久化运行时 CRUD 确认密钥');
            }
            return $generated;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function ensureNonceDirectory(): void
    {
        $this->ensureSecureDirectory($this->nonceDirectory, '确认 token nonce');
    }

    private function ensureSecureDirectory(string $directory, string $label): void
    {
        $oldMask = umask(0077);
        try {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('无法创建' . $label . '存储目录');
            }
        } finally {
            umask($oldMask);
        }
        if (is_link($directory) || !chmod($directory, 0700)) {
            throw new RuntimeException($label . '存储目录权限不安全');
        }
        clearstatcache(true, $directory);
        if ((fileperms($directory) & 0777) !== 0700) {
            throw new RuntimeException($label . '存储目录必须为 0700');
        }
    }

    private function noncePath(string $nonce): string
    {
        return $this->nonceDirectory . DIRECTORY_SEPARATOR . hash('sha256', $nonce) . '.used';
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value, string $message): string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new RuntimeException($message);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException($message);
        }
        return $decoded;
    }
}
