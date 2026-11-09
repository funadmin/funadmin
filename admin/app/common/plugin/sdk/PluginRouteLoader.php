<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use Webman\Finder\ControllerFinder;
use Webman\Route;

/** Annotation discovery must gate plugins before reflecting vendor-dependent controllers. */
final class PluginRouteLoader extends Route
{
    private const CORE_APPLICATIONS = ['app', 'admin', 'api', 'index', 'install', 'common'];

    protected static function loadAnnotationRoutes(): void
    {
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
        $definitions = parent::buildAnnotationRouteDefinitions($eligible);
        $manifests = [];
        foreach ($plugins as $code => $record) {
            if (($record['enabled'] ?? false) !== true) continue;
            $directory = rtrim($pluginsPath, '/\\') . '/' . $code;
            $manifestFile = $directory . '/plugin.json';
            if (!is_file($manifestFile)) continue;
            $raw = json_decode((string) @file_get_contents($manifestFile), true);
            if (is_array($raw) && !empty($raw['routeOverrides'])) $manifests[$code] = Manifest::fromDirectory($directory);
        }
        return PluginRouteOverride::apply($definitions, $manifests);
    }
}
