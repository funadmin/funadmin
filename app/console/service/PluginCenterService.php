<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\model\Plugin;
use app\common\model\PluginOperation;
use app\common\service\AbstractService;
use fun\plugins\Manifest;
use fun\plugins\Registry;
use RuntimeException;
use think\facade\Cache;
use think\facade\Db;

/**
 * 插件中心查询、Admin Web 模块发现与插件配置管理。
 */
final class PluginCenterService extends AbstractService
{
    public function discovered(): array
    {
        $installed = array_fill_keys(Plugin::column('code'), true);
        $items = [];
        foreach ($this->manifests() as $code => $manifest) {
            if (!isset($installed[$code])) {
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
            $code = (string) $record->code;
            $items[] = $this->manifestData($manifests[$code] ?? null, $record, 'installed');
        }
        return $items;
    }

    public function detail(string $code): array
    {
        $this->assertCode($code);
        $record = Plugin::where('code', $code)->find();
        $manifest = $this->manifests()[$code] ?? null;
        if (!$record && !$manifest) {
            throw new RuntimeException('插件不存在');
        }
        return $this->manifestData($manifest, $record, $record ? 'installed' : 'local');
    }

    public function deletePackage(string $code): void
    {
        $this->assertCode($code);
        if (Plugin::where('code', $code)->find()) {
            throw new RuntimeException('请先卸载插件再删除本地包');
        }
        $directory = root_path() . PLUGIN_DIR . DIRECTORY_SEPARATOR . $code;
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
            $code = (string) $record->code;
            $manifest = $manifests[$code] ?? null;
            if (!$manifest) {
                continue;
            }
            $adminWeb = $manifest->toArray()['adminWeb'] ?? null;
            if (!is_array($adminWeb) || !is_array($adminWeb['components'] ?? null)) {
                continue;
            }
            $modules[] = [
                'code' => $code,
                'version' => (string) $record->version,
                'components' => $adminWeb['components'],
                'routes' => $this->routeDtos($adminWeb['routes'] ?? [], $code),
            ];
        }
        return $modules;
    }

    public function get(string $code): array
    {
        $config = $this->loadSchema($code);
        foreach ($config as &$definition) {
            if (is_array($definition) && ($definition['type'] ?? '') === 'password') {
                $definition['value'] = '';
            }
        }
        unset($definition);
        return $config;
    }

    public function save(string $code, array $values): bool
    {
        $this->assertCode($code);
        $this->assertConfigColumn();
        $record = Plugin::where('code', $code)->find();
        if (!$record) {
            throw new RuntimeException('插件尚未安装');
        }

        $plugin = $this->plugin($code);
        $config = $this->mergeValues($this->loadSchema($code), $values);
        $file = $this->configFile($code);
        $previous = is_file($file) ? file_get_contents($file) : null;
        if ($previous === false) {
            throw new RuntimeException('无法读取插件原配置');
        }

        $this->atomicWrite($file, "<?php\n\nreturn " . var_export($config, true) . ";\n");
        try {
            $plainValues = $this->plainValues($config);
            if (method_exists($plugin, 'configChanged') && $plugin->configChanged($plainValues) === false) {
                throw new RuntimeException('插件配置变更钩子执行失败');
            }
            $snapshot = json_encode($this->snapshotValues($config), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if ($record->save(['config' => $snapshot]) === false) {
                throw new RuntimeException('插件配置状态保存失败');
            }
        } catch (\Throwable $exception) {
            $this->restore($file, $previous);
            throw $exception;
        }

        config([], "plugin_{$code}_config");
        Cache::delete('pluginslist');
        Cache::delete('plugins_data_list');
        Cache::delete('plugins_data_list_config');
        return true;
    }

    private function manifests(): array
    {
        return (new Registry(root_path() . PLUGIN_DIR, static fn (): array => []))->discover();
    }

    private function manifestData(?Manifest $manifest, ?Plugin $record, string $source): array
    {
        $data = $manifest?->toArray() ?? $this->decodedManifest($record);
        $dependencies = is_array($data['requires']['plugins'] ?? null) ? $data['requires']['plugins'] : [];
        $operation = $record ? PluginOperation::where('plugin_code', (string) $record->code)->order('id', 'desc')->find() : null;
        return [
            'code' => (string) ($record?->code ?? $manifest?->code() ?? ''),
            'name' => (string) ($record?->name ?? $manifest?->name() ?? ''),
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
            'adminWeb' => is_array($data['adminWeb'] ?? null) ? $data['adminWeb'] : null,
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

    private function routeDtos(mixed $routes, string $code): array
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
            $routeName = (string) ($route['name'] ?? 'Plugin_' . $code . '_' . count($result));
            if (!preg_match('~^/plugin/' . preg_quote($code, '~') . '/[a-zA-Z0-9/_-]+$~', $path)
                || !preg_match('/^Plugin_' . preg_quote($code, '/') . '(?:_[A-Za-z0-9_-]+)?$/', $routeName)
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
        foreach (Plugin::whereIn('code', array_keys($dependencies))->select() as $record) {
            $records[(string) $record->code] = $record;
        }
        foreach ($dependencies as $code => $constraint) {
            $dependency = $records[$code] ?? null;
            if (!$dependency) {
                return '依赖插件未安装：' . $code;
            }
            if ((string) $dependency->lifecycle_state !== 'enabled' || (int) ($dependency->needs_reinstall ?? 0) === 1) {
                return '依赖插件未启用：' . $code;
            }
            if (!$this->matchesVersion((string) $dependency->version, (string) $constraint)) {
                return "依赖插件 {$code} 版本不满足 {$constraint}，当前 {$dependency->version}";
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

    private function loadSchema(string $code): array
    {
        $this->assertCode($code);
        $file = $this->configFile($code);
        if (!is_file($file)) {
            return [];
        }
        $config = include $file;
        if (!is_array($config)) {
            throw new RuntimeException('插件配置格式无效');
        }
        return $config;
    }

    private function mergeValues(array $schema, array $values): array
    {
        foreach ($values as $key => $_value) {
            if (!array_key_exists($key, $schema)) {
                throw new RuntimeException('包含未定义的插件配置项：' . $key);
            }
        }
        foreach ($schema as $key => &$definition) {
            if (!is_array($definition) || !array_key_exists('value', $definition)) {
                throw new RuntimeException('插件配置定义无效：' . $key);
            }
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            if (($definition['type'] ?? '') === 'password' && $value === '') {
                continue;
            }
            $definition['value'] = $this->normalizeValue($value, $definition);
        }
        unset($definition);
        return $schema;
    }

    private function normalizeValue(mixed $value, array $definition): mixed
    {
        $type = (string) ($definition['type'] ?? 'text');
        if (in_array($type, ['checkbox', 'selects', 'xmselect', 'images', 'files', 'array'], true)) {
            return is_array($value) ? $value : ($value === '' ? [] : [$value]);
        }
        if (is_array($value)) {
            throw new RuntimeException('插件配置值类型无效');
        }
        return is_string($value) ? trim($value) : $value;
    }

    private function plainValues(array $config): array
    {
        $values = [];
        foreach ($config as $key => $definition) {
            if (is_array($definition) && array_key_exists('value', $definition)) {
                $values[$key] = $definition['value'];
            }
        }
        return $values;
    }

    private function snapshotValues(array $config): array
    {
        $values = [];
        foreach ($config as $key => $definition) {
            if (!is_array($definition) || !array_key_exists('value', $definition)) {
                continue;
            }
            $values[$key] = ($definition['type'] ?? '') === 'password' ? '[REDACTED]' : $definition['value'];
        }
        return $values;
    }

    private function atomicWrite(string $file, string $content): void
    {
        $directory = dirname($file);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('插件配置目录不可写');
        }
        $temporary = tempnam($directory, '.config-');
        if ($temporary === false) {
            throw new RuntimeException('无法创建插件配置临时文件');
        }
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $file)) {
                throw new RuntimeException('插件配置保存失败');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function restore(string $file, string|false|null $previous): void
    {
        if ($previous === null) {
            @unlink($file);
            return;
        }
        $this->atomicWrite($file, (string) $previous);
    }

    private function assertConfigColumn(): void
    {
        $prefix = (string) config('database.connections.mysql.prefix');
        $table = str_replace('`', '``', $prefix . 'plugin');
        if (Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'config'") === []) {
            throw new RuntimeException('插件生命周期表结构未升级，请先执行 database/migrations/005_plugin_lifecycle_schema.sql');
        }
    }

    private function configFile(string $code): string
    {
        return root_path() . PLUGIN_DIR . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'config.php';
    }

    private function plugin(string $code): object
    {
        $plugin = get_plugin_instance($code);
        if (!$plugin) {
            throw new RuntimeException('插件入口类不存在');
        }
        return $plugin;
    }

    private function assertCode(string $code): void
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $code)) {
            throw new RuntimeException('插件标识不合法');
        }
    }
}
