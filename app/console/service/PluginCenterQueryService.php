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
            $adminWeb = $manifest->toArray()['admin_web'] ?? null;
            if (!is_array($adminWeb) || !is_string($adminWeb['entry'] ?? null)) {
                continue;
            }
            $entry = (string) $adminWeb['entry'];
            $publishRoot = root_path() . 'public' . DIRECTORY_SEPARATOR . 'plugin-assets' . DIRECTORY_SEPARATOR . $name;
            $realPublishRoot = realpath($publishRoot);
            $realFile = realpath($publishRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry));
            if ($realPublishRoot === false || $realFile === false || !is_file($realFile)) {
                continue;
            }
            if (!str_starts_with($realFile, rtrim($realPublishRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $hash = hash_file('sha256', $realFile);
            if (!is_string($hash)) {
                continue;
            }
            $modules[] = [
                'name' => $name,
                'version' => (string) $record->version,
                'hash' => $hash,
                'entryUrl' => '/plugin-assets/' . $name . '/' . $entry . '?v=' . $hash,
                'routes' => $this->routeDtos($adminWeb['routes'] ?? [], $name),
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
        $operation = $record ? PluginOperation::where('plugin_name', (string) $record->name)->order('id', 'desc')->find() : null;
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
            'needsReinstall' => (bool) ($record?->needs_reinstall ?? false),
            'operation' => (string) ($record?->operation_token ?? '') !== '' ? (string) ($operation?->operation ?? 'unknown') : '',
            'progress' => (int) ($operation?->progress ?? 0),
            'disabledReason' => $this->disabledReason($record, $dependencies),
            'admin_web' => is_array($data['admin_web'] ?? null) ? $data['admin_web'] : null,
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
            $routeName = (string) ($route['name'] ?? 'Plugin_' . $name . '_' . count($result));
            if (!preg_match('~^/plugin/' . preg_quote($name, '~') . '/[a-zA-Z0-9/_-]+$~', $path)
                || !preg_match('/^Plugin_' . preg_quote($name, '/') . '(?:_[A-Za-z0-9_-]+)?$/', $routeName)
                || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $component)) {
                continue;
            }
            $result[] = [
                'path' => $path,
                'name' => $routeName,
                'component' => $component,
                'meta' => is_array($route['meta'] ?? null) ? $route['meta'] : [],
            ];
        }
        return $result;
    }

    private function disabledReason(?Plugin $record, array $dependencies): string
    {
        if (!$record) {
            return '';
        }
        if ((int) ($record->needs_reinstall ?? 0) === 1) {
            return '必须使用可信插件包重新安装';
        }
        if ((string) ($record->operation_token ?? '') !== '') {
            return '插件生命周期操作进行中';
        }
        if ((bool) ($record->migration_pending ?? false)) {
            return '数据库迁移尚未完成';
        }
        return $this->dependencyReason($dependencies);
    }

    private function dependencyReason(array $dependencies): string
    {
        if ($dependencies === []) {
            return '';
        }
        $records = [];
        foreach (Plugin::whereIn('name', array_keys($dependencies))->select() as $record) {
            $records[(string) $record->name] = $record;
        }
        foreach ($dependencies as $name => $constraint) {
            $dependency = $records[$name] ?? null;
            if (!$dependency) {
                return '依赖插件未安装：' . $name;
            }
            if ((string) $dependency->lifecycle_state !== 'enabled' || (int) ($dependency->needs_reinstall ?? 0) === 1) {
                return '依赖插件未启用：' . $name;
            }
            if (!$this->matchesVersion((string) $dependency->version, (string) $constraint)) {
                return "依赖插件 {$name} 版本不满足 {$constraint}，当前 {$dependency->version}";
            }
        }
        return '';
    }

    private function matchesVersion(string $version, string $constraint): bool
    {
        foreach (preg_split('/\s*,\s*|\s+/', trim($constraint)) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            if (str_starts_with($part, '^')) {
                $minimum = substr($part, 1);
                $major = (int) explode('.', $minimum)[0];
                if (version_compare($version, $minimum, '<') || version_compare($version, ($major + 1) . '.0.0', '>=')) {
                    return false;
                }
                continue;
            }
            if (!preg_match('/^(>=|<=|>|<|=)?(.+)$/', $part, $matches)
                || !version_compare($version, $matches[2], $matches[1] ?: '=')) {
                return false;
            }
        }
        return true;
    }

    private function assertName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $name)) {
            throw new RuntimeException('插件名称不合法');
        }
    }
}
