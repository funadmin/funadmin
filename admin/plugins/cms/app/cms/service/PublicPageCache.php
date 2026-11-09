<?php
declare(strict_types=1);
namespace app\cms\service;

use support\Cache;

/**
 * 公开页面 HTML 共享缓存：键含站点、站点版本、语言、规范路径与变体；站点版本递增即整站失效。
 * 配置 app/cms/config/app.php 的 public_page_cache = ['enabled'=>bool,'ttl'=>秒,'store'=>缓存 store 名]。
 */
final class PublicPageCache
{
    private const VERSION_PREFIX = 'cms-public-version-';
    private const ENTRY_PREFIX = 'cms-public-page-';
    private static ?string $storeOverride = null;

    public function __construct(private readonly ?string $store = null) {}

    /** 测试或多进程隔离场景替换默认缓存 store；传 null 恢复配置值。 */
    public static function useStore(?string $store): void
    {
        self::$storeOverride = $store;
    }

    public static function enabled(): bool
    {
        return (bool)(self::config()['enabled'] ?? true);
    }

    public static function ttl(): int
    {
        return max(1, min(3600, (int)(self::config()['ttl'] ?? 300)));
    }

    public function get(string $siteId, string $locale, string $path, string $variant = ''): ?array
    {
        $entry = $this->cache()->get($this->entryKey($siteId, $locale, $path, $variant));
        return is_array($entry) && isset($entry['body'], $entry['etag']) && is_string($entry['body']) && is_string($entry['etag']) ? $entry : null;
    }

    public function put(string $siteId, string $locale, string $path, string $body, string $etag, ?int $ttl = null, string $variant = ''): void
    {
        $this->cache()->set($this->entryKey($siteId, $locale, $path, $variant), [
            'body' => $body,
            'etag' => $etag,
        ], max(1, min(3600, $ttl ?? self::ttl())));
    }

    /** 站点版本递增，旧条目不再命中并随 TTL 过期。写操作应在事务提交后再调用一次，避免并发请求把提交前的数据写入新版本。 */
    public function invalidateSite(string $siteId): void
    {
        $key = self::VERSION_PREFIX . Input::id($siteId);
        $this->cache()->set($key, ((int)$this->cache()->get($key, 0)) + 1, 31536000);
    }

    public function etag(string $body): string
    {
        return '"' . sha1($body) . '"';
    }

    /** 按 RFC 9110 弱比较判断 If-None-Match 是否命中：支持逗号列表、W/ 前缀与 *。 */
    public static function matches(string $header, string $etag): bool
    {
        $header = trim($header);
        if ($header === '') return false;
        if ($header === '*') return true;
        $strip = static fn (string $tag): string => str_starts_with($tag, 'W/') ? substr($tag, 2) : $tag;
        foreach (explode(',', $header) as $candidate) {
            if ($strip(trim($candidate)) === $strip($etag)) return true;
        }
        return false;
    }

    private function entryKey(string $siteId, string $locale, string $path, string $variant = ''): string
    {
        $siteId = Input::id($siteId);
        $locale = Input::locale($locale);
        $path = RouteService::normalize($path, false);
        $version = (int)$this->cache()->get(self::VERSION_PREFIX . $siteId, 0);
        return self::ENTRY_PREFIX . $siteId . '-' . $version . '-' . sha1($locale . "\0" . $path . "\0" . $variant);
    }

    private function cache(): object
    {
        $store = $this->store ?? self::$storeOverride ?? (string)(self::config()['store'] ?? '');
        return $store === '' ? Cache::store() : Cache::store($store);
    }

    private static function config(): array
    {
        static $config = null;
        if ($config === null) {
            $file = dirname(__DIR__) . '/config/app.php';
            $all = is_file($file) ? require $file : [];
            $config = is_array($all['public_page_cache'] ?? null) ? $all['public_page_cache'] : [];
        }
        return $config;
    }
}
