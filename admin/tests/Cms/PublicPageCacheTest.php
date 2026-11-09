<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';

use app\cms\service\PublicPageCache;
use PHPUnit\Framework\TestCase;
use support\Cache;

final class PublicPageCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::store('array')->clear();
    }

    public function test_cache_is_scoped_by_site_locale_and_path_and_can_be_invalidated(): void
    {
        $cache = new PublicPageCache('array');
        $cache->put('1', 'zh-cn', '/', '<html>A</html>', 'etag-a');
        $cache->put('2', 'zh-cn', '/', '<html>B</html>', 'etag-b');

        self::assertSame('<html>A</html>', $cache->get('1', 'zh-cn', '/')['body']);
        self::assertSame('<html>B</html>', $cache->get('2', 'zh-cn', '/')['body']);

        $cache->invalidateSite('1');
        self::assertNull($cache->get('1', 'zh-cn', '/'));
        self::assertSame('<html>B</html>', $cache->get('2', 'zh-cn', '/')['body']);
    }

    public function test_etag_is_stable_for_the_same_page_body(): void
    {
        $cache = new PublicPageCache('array');
        self::assertSame('"'.sha1('<html>A</html>').'"', $cache->etag('<html>A</html>'));
        self::assertSame($cache->etag('<html>A</html>'), $cache->etag('<html>A</html>'));
    }

    public function test_if_none_match_uses_weak_comparison_over_lists(): void
    {
        self::assertTrue(PublicPageCache::matches('"a"', '"a"'));
        self::assertTrue(PublicPageCache::matches('W/"a"', '"a"'));
        self::assertTrue(PublicPageCache::matches('"b" , "a"', '"a"'));
        self::assertTrue(PublicPageCache::matches('*', '"a"'));
        self::assertFalse(PublicPageCache::matches('', '"a"'));
        self::assertFalse(PublicPageCache::matches('"b"', '"a"'));
    }

    public function test_variant_separates_entries_for_the_same_path(): void
    {
        $cache = new PublicPageCache('array');
        $cache->put('1', 'zh-cn', '/', '<html>A</html>', 'etag-a', null, 'theme-a');
        self::assertNull($cache->get('1', 'zh-cn', '/', 'theme-b'));
        self::assertSame('etag-a', $cache->get('1', 'zh-cn', '/', 'theme-a')['etag']);
    }
}
