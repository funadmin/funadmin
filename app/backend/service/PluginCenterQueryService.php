<?php

declare(strict_types=1);

namespace app\backend\service;

use app\common\model\Plugin;
use app\common\model\PluginOperation;
use app\common\model\PluginVersionHistory;
use app\common\service\AbstractService;
use fun\plugins\Manifest;
use fun\plugins\Registry;
use RuntimeException;

/**
 * 插件中心只读查询与 Admin Web ESM 模块契约。
 */
final class PluginCenterQueryService extends AbstractService
{
    public function discovered(): array
    {
        $installed = array_fill_keys(Plugin::column('name'), true);
        $items = [];
        foreach ($this->manifests() as $name => $manifest) {
            if (!isset($installed[$name])) {
                $items[] = $this->manifestData($manifest, null, 'local');
            }
        }
        return $items;
    }

    public function installed(): array
    {
        $manifests = $this->manifests();
        $items = [];
        foreach (Plugin::order('id', 'desc')->select() as $record) {
            $name = (string) $record->name;
            $items[] = $this->manifestData($manifests[$name] ?? null, $record, 'installed');
        }
        return $items;
    }

    public function detail(string $name): array
    {
        $this->assertName($name);
        $record = Plugin::where('name', $name)->find();
        $manifest = $this->manifests()[$name] ?? null;
        if (!$record && !$manifest) {
            throw new RuntimeException('插件不存在');
        }
        return $this->manifestData($manifest, $record, $record ? 'installed' : 'local');
    }

    public function versions(string $name): array
    {
        $this->assertName($name);
        return PluginVersionHistory::where('plugin_name', $name)->order('id', 'desc')->select()->toArray();
    }

    public function operations(string $name): array
    {
        $this->assertName($name);
        return PluginOperation::where('plugin_name', $name)->order('id', 'desc')->limit(100)->select()->toArray();
    }

    public function deletePackage(string $name): void
    {
        $this->assertName($name);
        if (Plugin::where('name', $name)->find()) {
            throw new RuntimeException('请先卸载插件再删除本地包');
        }
        $directory = root_path() . PLUGIN_DIR . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($directory)) {
            throw new RuntimeException('本地插件包不存在');
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $removed = $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            if (!$removed) {
                throw new RuntimeException('无法删除本地插件包');
            }
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('无法删除本地插件包');
        }
    }

    public function enabledModules(): array
    {
        $manifests = $this->manifests();
        $modules = [];
        foreach (Plugin::where('lifecycle_state', 'enabled')->where('needs_reinstall', 0)->select() as $record) {
            $name = (string) $record->name;
            $manifest = $manifests[$name] ?? null;
            if (!$manifest) {
                continue;
            }
            $admin = $manifest->toArray()['admin_web'] ?? null;
            if (!is_array($admin) || !is_string($admin['entry'] ?? null)) {
                continue;
            }
            $entry = (string) $admin['entry'];
            $publishRoot = root_path() . 'public' . DIRECTORY_SEPARATOR . 'plugin-assets' . DIRECTORY_SEPARATOR . $name;
            $realPublishRoot = realpath($publishRoot);
            $realFile = realpath($publishRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry));
            if ($realPublishRoot === false || $realFile === false || !is_file($realFile)) {
                continue;
            }
            $prefix = rtrim($realPublishRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!str_starts_with($realFile, $prefix)) {
                continue;
            }
            $hash = hash_file('sha256', $realFile);
            $modules[] = [
                'name' => $name,
                'version' => (string) $record->version,
                'hash' => $hash,
                'entryUrl' => '/plugin-assets/' . $name . '/' . $entry . '?v=' . $hash,
                'routes' => $this->routeDtos($admin['routes'] ?? [], $name),
            ];
        }
        return $modules;
    }

    private function manifests(): array
    {
        return (new Registry(root_path() . PLUGIN_DIR, static fn (): array => []))->discover();
    }

    private function manifestData(?Manifest $manifest, ?Plugin $record, string $source): array
    {
        $data = $manifest?->toArray() ?? $this->decodedManifest($record);
        $dependencies = is_array($data['requires']['plugins'] ?? null) ? $data['requires']['plugins'] : [];
        return [
            'name' => (string) ($record?->name ?? $manifest?->name() ?? ''),
            'title' => (string) ($record?->title ?? $manifest?->title() ?? ''),
            'version' => (string) ($record?->version ?? $manifest?->version() ?? ''),
            'latestVersion' => '',
            'dbVersion' => (string) ($record?->db_version ?? ''),
            'state' => (string) ($record?->lifecycle_state ?? 'discovered'),
            'dependencies' => $dependencies,
            'migrationPending' => (bool) ($record?->migration_pending ?? false),
            'lastError' => (string) ($record?->last_error ?? ''),
            'source' => $source,
        ];
    }

    private function decodedManifest(?Plugin $record): array
    {
        if (!$record || trim((string) $record->manifest) === '') {
            return [];
        }
        $data = json_decode((string) $record->manifest, true);
        return is_array($data) ? $data : [];
    }

    private function routeDtos(mixed $routes, string $name): array
    {
        if (!is_array($routes)) {
            return [];
        }
        $result = [];
        foreach ($routes as $route) {
            if (!is_array($route)) {
                continue;
            }
            $path = (string) ($route['path'] ?? '');
            $component = (string) ($route['component'] ?? '');
            if (!str_starts_with($path, '/plugin/' . $name . '/') || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $component)) {
                continue;
            }
            $result[] = [
                'path' => $path,
                'name' => (string) ($route['name'] ?? 'Plugin_' . $name . '_' . count($result)),
                'component' => $component,
                'meta' => is_array($route['meta'] ?? null) ? $route['meta'] : [],
            ];
        }
        return $result;
    }

    private function assertName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $name)) {
            throw new RuntimeException('插件名称不合法');
        }
    }
}
