<?php

declare(strict_types=1);

namespace app\middleware;

use support\Translation;
use app\common\service\translation\GeneratedLanguagePacks;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Webman 多语言中间件：按请求选择 locale，并把当前语言同步到客户端 Cookie。
 *
 * 翻译资源由 support\Translation 根据 config/translation.php 统一加载，
 * 这里不再依赖已移除的 ThinkPHP middleware、Request 或 Cookie API。
 */
class LoadLangPackMiddleware implements MiddlewareInterface
{
    private const COOKIE_NAME = 'locale';
    private const COOKIE_MAX_AGE = 31536000;

    public function process(Request $request, callable $handler): Response
    {
        $locale = $this->detect($request);
        $plugin = (string) ($request->plugin ?? '');
        $config = $plugin === ''
            ? config('translation', [])
            : config("plugin.$plugin.translation", config('translation', []));
        $config = is_array($config) ? $config : [];
        $translator = Translation::instance($plugin, $config);
        $translator->setLocale($locale);
        GeneratedLanguagePacks::register($translator, (array) ($config['path'] ?? []));

        $response = $handler($request);
        return $response->cookie(self::COOKIE_NAME, $locale, self::COOKIE_MAX_AGE, '/', '', false, false, 'Lax');
    }

    private function detect(Request $request): string
    {
        // The header selects UI messages; query locale can select a CMS/translation data language.
        foreach ([(string) $request->header('X-Locale', ''), (string) $request->get('locale', ''), (string) $request->cookie(self::COOKIE_NAME, '')] as $candidate) {
            if (($locale = $this->normalizeLocale($candidate)) !== null) {
                return $locale;
            }
        }

        return $this->normalizeLocale((string) config('translation.locale', 'zh-cn')) ?? 'zh-cn';
    }

    private function normalizeLocale(string $locale): ?string
    {
        $locale = strtolower(str_replace('_', '-', trim($locale)));
        return preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})*$/', $locale) === 1 ? $locale : null;
    }
}
