<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use Webman\Finder\ControllerFinder;
use Webman\Route;

/** Annotation discovery must gate plugins before reflecting vendor-dependent controllers. */
final class PluginRouteLoader extends Route
{
    private const CORE_APPLICATIONS = ['app', 'admin', 'api', 'index', 'install', 'common'];

    /** 旧版生成模块布局（已改为 business），残留目录不得再注册路由。 */
    private const LEGACY_GENERATED_DIRECTORIES = ['app/admin/generated', 'app/api/controller/generated'];
    private const LEGACY_GENERATED_CLASS = '/^app\\\\(?:admin\\\\generated|api\\\\controller\\\\generated)\\\\/';

    protected static function loadAnnotationRoutes(): void
    {
        self::warnLegacyGeneratedDirectories(base_path());
        $definitions = self::definitionsFor(
            ControllerFinder::files('*', false),
            new PluginActivationReader(base_path('runtime/plugins/activation')),
            base_path('plugins')
        );
        static::registerAnnotationRouteDefinitions($definitions);
    }

    /** Build routes only after activation checks, without executing inactive plugin PHP. */
    public static function definitionsFor(array $files, PluginActivationReader $reader, string $pluginsPath): array
    {
        $bundle = $reader->readBundle();
        $snapshot = $bundle['activation'];
        $plugins = $snapshot->plugins();
        $gate = new ActivationGate($snapshot);
        $eligible = [];
        foreach ($files as $file) {
            $class = (string) ($file->meta()['class'] ?? '');
            // 旧版 generated 控制器在反射前跳过，避免超级管理员绕过权限访问已停用的模块。
            if (preg_match(self::LEGACY_GENERATED_CLASS, $class) === 1) continue;
            $code = null; $application = 'app';
            if (preg_match('/^app\\\\admin\\\\plugins\\\\([a-z][a-z0-9]*)\\\\controller\\\\/', $class, $match)) {
                $code = $match[1]; $application = 'admin';
            } elseif (preg_match('/^app\\\\([a-z][a-z0-9]*)\\\\controller\\\\/', $class, $match)
                && !in_array($match[1], self::CORE_APPLICATIONS, true)) {
                $candidate = $match[1];
                if (isset($plugins[$candidate]) || $bundle['ownership']->owns($candidate, 'app')
                    || is_file(rtrim($pluginsPath, '/\\') . '/' . $candidate . '/plugin.json')) {
                    $code = $candidate;
                }
            }
            if ($code !== null) {
                try { $gate->assertEnabled($code, $application); }
                catch (PluginNotActiveException|ActivationUnavailableException) { continue; }
                PluginVendorAutoloader::ensure(rtrim($pluginsPath, '/\\') . '/' . $code);
            }
            $eligible[] = $file;
        }
        return parent::buildAnnotationRouteDefinitions($eligible);
    }

    /** 启动时列出残留的旧版 generated 目录并记录一条告警；无残留时静默。 */
    public static function warnLegacyGeneratedDirectories(string $basePath, ?callable $sink = null): void
    {
        $leftovers = array_values(array_filter(self::LEGACY_GENERATED_DIRECTORIES, static fn (string $directory): bool => is_dir(rtrim($basePath, '/\\') . '/' . $directory)));
        if ($leftovers === []) return;
        $message = '旧版 generated 目录已停用，请重新生成为 business 模块或删除：' . implode(', ', $leftovers);
        ($sink ?? static fn (string $text) => \support\Log::warning($text))($message);
    }
}
