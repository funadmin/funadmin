<?php

declare(strict_types=1);

namespace app\common\service\translation;

use Symfony\Component\Translation\Translator;

/**
 * 把分组语言包注册为默认域的点分键（[模块 => [键 => 文案]] → 模块.键）。
 *
 * - 配置目录（公共语言包）下的 <语言>/<分组>.php；messages.php 由 Webman 原生加载。
 * - 模块语言包跟随模块：业务模块 app/admin/business/<模块>/lang/<语言>.php、插件 app/admin/plugins/<插件>/lang/<语言>.php。
 */
final class GeneratedLanguagePacks
{
    /** 模块语言包所在位置（相对 app_path()）。 */
    private const MODULE_PACKS = ['admin/business/*/lang/*.php', 'admin/plugins/*/lang/*.php'];

    private static ?\WeakMap $registered = null;

    public static function register(Translator $translator, array $paths): void
    {
        self::$registered ??= new \WeakMap();
        if (isset(self::$registered[$translator])) {
            return;
        }
        foreach ($paths as $path) {
            foreach ((array) glob(rtrim($path, '/\\') . '/*/*.php') as $file) {
                if (basename($file) === 'messages.php') {
                    continue; // Already loaded by native Webman configuration.
                }
                self::add($translator, (string) $file, basename(dirname((string) $file)));
            }
        }
        foreach (self::MODULE_PACKS as $pattern) {
            foreach ((array) glob(app_path($pattern)) as $file) {
                $locale = basename((string) $file, '.php');
                if (preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/D', $locale) === 1) self::add($translator, (string) $file, $locale);
            }
        }
        self::$registered[$translator] = true;
    }

    private static function add(Translator $translator, string $file, string $locale): void
    {
        $messages = require $file;
        if (!is_array($messages) || !array_filter($messages, 'is_array')) {
            return;
        }
        // PhpFileLoader flattens generated nested arrays without altering their keys.
        $translator->addResource('phpfile', $file, $locale, 'messages');
    }
}
