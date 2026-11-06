<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\PermissionCode;
use app\admin\authorization\service\PermissionSyncService;
use PHPUnit\Framework\TestCase;

/**
 * 插件前端按钮、菜单与路由使用的权限码必须真实存在：
 * 接口权限 = 控制器方法推导码；其余只能是插件清单声明的能力权限（例如 CMS 站点操作）。
 */
final class PluginFrontendPermissionContractTest extends TestCase
{
    private const PLUGINS = ['cms', 'qa', 'example', 'document', 'oss'];

    public function test_every_plugin_permission_code_used_by_the_frontend_exists(): void
    {
        $root = dirname(__DIR__, 2);
        $sources = [$root . '/web/src/admin/modules'];
        foreach (self::PLUGINS as $plugin) $sources[] = dirname(__DIR__) . "/plugins/$plugin/web/src";
        $checked = 0;
        foreach ($sources as $source) {
            if (!is_dir($source)) continue;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!preg_match('/\.(vue|ts)$/', $file->getFilename()) || str_contains($file->getFilename(), '.spec.')) continue;
                preg_match_all("/'((" . implode('|', self::PLUGINS) . "):[a-z][a-z-]*:[a-z][a-z-]*)'/", (string) file_get_contents($file->getPathname()), $matches, PREG_SET_ORDER);
                foreach ($matches as [, $code, $plugin]) {
                    self::assertContains($code, $this->known($plugin), $file->getPathname() . " 使用了不存在的权限码 $code");
                    $checked++;
                }
            }
        }
        self::assertGreaterThan(100, $checked);
    }

    public function test_plugin_menus_and_routes_bind_derived_endpoint_codes(): void
    {
        foreach (self::PLUGINS as $plugin) {
            $file = dirname(__DIR__) . "/plugins/$plugin/plugin.json";
            if (!is_file($file)) continue;
            $manifest = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $bound = [];
            $walk = function (array $items) use (&$walk, &$bound): void {
                foreach ($items as $item) {
                    if (isset($item['permission'])) $bound[] = $item['permission'];
                    $walk($item['menulist'] ?? []);
                }
            };
            $walk($manifest['adminWeb']['menu'] ?? []);
            foreach ($manifest['adminWeb']['routes'] ?? [] as $route) if (isset($route['meta']['permission'])) $bound[] = $route['meta']['permission'];
            self::assertNotEmpty($bound, $plugin);
            foreach ($bound as $code) self::assertContains($code, $this->derived($plugin), "$plugin 菜单/路由必须绑定接口推导码：$code");
        }
    }

    /** @return list<string> */
    private function known(string $plugin): array
    {
        $codes = $this->derived($plugin);
        $file = dirname(__DIR__) . "/plugins/$plugin/plugin.json";
        if (is_file($file)) {
            $manifest = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $codes = array_merge($codes, array_column($manifest['adminWeb']['permissions'] ?? [], 'code'));
        }
        return $codes;
    }

    /** @return list<string> 已安装插件按控制器反射推导；未安装插件按源码中的路由注解推导。 */
    private function derived(string $plugin): array
    {
        $installed = (new PermissionSyncService())->pluginCodes($plugin);
        if ($installed !== []) return $installed;
        $codes = [];
        foreach (glob(dirname(__DIR__) . "/plugins/$plugin/app/admin/plugins/$plugin/controller/*.php") ?: [] as $file) {
            $class = "app\\admin\\plugins\\$plugin\\controller\\" . basename($file, '.php');
            preg_match_all('/#\[(?:Get|Post|Put|Delete|Patch|Route|Any)\([^\]]*\)\]\s*(?:#\[[^\]]*\]\s*)*public function (\w+)/', (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $method) $codes[] = PermissionCode::derive($class, $method);
        }
        return array_values(array_filter($codes));
    }
}
