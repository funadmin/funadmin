<?php

declare(strict_types=1);

namespace app\cms\service;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Webman\Limiter\Limiter;

/** 公开表单防刷：签名令牌（绑定站点与表单，3 秒至 7200 秒有效）、蜜罐 `_hp`、按站点 + 表单 + IP 限流。 */
final class FormGuard
{
    public const HONEYPOT = '_hp';
    public const MIN_AGE = 3;
    public const MAX_AGE = 7200;
    public const THROTTLE_WINDOW = 600;
    private readonly string $secret;
    private readonly Closure $clock;

    /** @param Closure():int|null $clock 返回秒级时间戳，供测试替换；$secretPath 仅供测试替换密钥文件位置。 */
    public function __construct(?string $secret = null, ?Closure $clock = null, ?string $secretPath = null)
    {
        $this->secret = self::secret($secret, $secretPath);
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function issue(string $siteId, string $formId): string
    {
        if (!self::bindable($siteId) || !self::bindable($formId)) throw self::rejected('token_invalid');
        $payload = $siteId . '|' . $formId . '|' . ($this->clock)() . '|' . bin2hex(random_bytes(16));
        return self::encode($payload) . '.' . self::encode($this->sign($payload));
    }

    public function verify(string $token, string $siteId, string $formId): void
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) throw self::rejected('token_invalid');
        $payload = self::decode($parts[0]);
        $signature = self::decode($parts[1]);
        if ($payload === null || $signature === null || !hash_equals($this->sign($payload), $signature)) throw self::rejected('token_invalid');
        if (!preg_match('/^([^|]+)\|([^|]+)\|(\d{1,12})\|[0-9a-f]{32}$/D', $payload, $match) || $match[1] !== $siteId || $match[2] !== $formId) throw self::rejected('token_invalid');
        $age = ($this->clock)() - (int)$match[3];
        if ($age < 0) throw self::rejected('token_invalid');
        if ($age < self::MIN_AGE) throw self::rejected('too_fast');
        if ($age > self::MAX_AGE) throw self::rejected('token_expired');
    }

    /** 蜜罐字段被填写（任何非空值）即视为机器人提交。 */
    public function isHoneypot(array $input): bool
    {
        $value = $input[self::HONEYPOT] ?? null;
        return $value !== null && $value !== '' && $value !== [];
    }

    /** 每次调用计一次提交；同一站点、表单与 IP 在 600 秒窗口内超过 $limit 次（上限 60）即拒绝。 */
    public function throttle(string $siteId, string $formId, string $ip, int $limit): void
    {
        $limit = max(1, min(FormService::RATE_LIMIT_MAX, $limit));
        if (!Limiter::attempt("cms_form:{$siteId}:{$formId}:{$ip}", $limit, self::THROTTLE_WINDOW)) {
            throw new InvalidArgumentException('too_many_requests', 429);
        }
    }

    /**
     * 签名密钥：显式传入 → 环境变量 CMS_FORM_SECRET → 运行时密钥文件（目录 0700、文件 0600，缺失时生成）。
     * 表单令牌与验证码 ID 共用此密钥。
     */
    public static function secret(?string $secret = null, ?string $path = null): string
    {
        $candidate = trim((string)$secret);
        if ($candidate === '') {
            $environment = getenv('CMS_FORM_SECRET');
            $candidate = trim($environment === false ? '' : $environment);
        }
        return $candidate !== '' ? $candidate : self::fileSecret($path ?? runtime_path('plugins-data/cms/form.secret'));
    }

    private static function fileSecret(string $path): string
    {
        $directory = dirname($path);
        $oldMask = umask(0077);
        try {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('无法创建表单密钥目录');
        } finally {
            umask($oldMask);
        }
        if (is_link($directory) || !chmod($directory, 0700)) throw new RuntimeException('表单密钥目录权限不安全');
        clearstatcache(true, $path);
        $existing = @lstat($path);
        if ($existing !== false && ($existing['mode'] & 0170000) !== 0100000) throw new RuntimeException('表单密钥必须是普通文件');
        $oldMask = umask(0077);
        try {
            $handle = @fopen($path, 'c+x');
            if ($handle === false && $existing !== false) $handle = @fopen($path, 'r+');
        } finally {
            umask($oldMask);
        }
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) fclose($handle);
            throw new RuntimeException('CMS_FORM_SECRET 未配置且表单密钥文件不可用');
        }
        try {
            clearstatcache(true, $path);
            $locked = @lstat($path);
            $opened = fstat($handle);
            if ($locked === false || $opened === false || ($locked['mode'] & 0170000) !== 0100000 || $locked['dev'] !== $opened['dev'] || $locked['ino'] !== $opened['ino']) {
                throw new RuntimeException('表单密钥文件类型不安全');
            }
            if (!chmod($path, 0600)) throw new RuntimeException('无法将表单密钥文件收紧为 0600');
            rewind($handle);
            $stored = trim((string)stream_get_contents($handle));
            if ($stored !== '') return $stored;
            $generated = bin2hex(random_bytes(32));
            if (!ftruncate($handle, 0) || !rewind($handle) || fwrite($handle, $generated) === false || !fflush($handle)) throw new RuntimeException('无法持久化表单密钥');
            return $generated;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', 'form|' . $payload, $this->secret, true);
    }

    private static function bindable(string $id): bool
    {
        return $id !== '' && !str_contains($id, '|');
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $value)) return null;
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }

    private static function rejected(string $key): InvalidArgumentException
    {
        return new InvalidArgumentException($key, 422);
    }
}
