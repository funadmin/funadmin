<?php

namespace app\backend\service;

use app\common\model\Plugin;
use app\common\service\AbstractService;
use RuntimeException;
use think\facade\Cache;
use think\facade\Db;

/**
 * 插件配置读取、校验与原子保存。
 */
class PluginConfigService extends AbstractService
{
    public function get(string $name): array
    {
        $config = $this->loadSchema($name);
        foreach ($config as &$definition) {
            if (is_array($definition) && ($definition['type'] ?? '') === 'password') {
                $definition['value'] = '';
            }
        }
        unset($definition);
        return $config;
    }

    public function save(string $name, array $values): bool
    {
        $this->assertName($name);
        $this->assertConfigColumn();
        $record = Plugin::where('name', $name)->find();
        if (!$record) {
            throw new RuntimeException('插件尚未安装');
        }

        $plugin = $this->plugin($name);
        $config = $this->mergeValues($this->loadSchema($name), $values);
        $file = $this->configFile($name);
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

        config([], "plugin_{$name}_config");
        Cache::delete('pluginslist');
        Cache::delete('plugins_data_list');
        Cache::delete('plugins_data_list_config');
        return true;
    }

    private function loadSchema(string $name): array
    {
        $this->assertName($name);
        $file = $this->configFile($name);
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

    private function configFile(string $name): string
    {
        return root_path() . PLUGIN_DIR . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'config.php';
    }

    private function plugin(string $name): object
    {
        $plugin = get_plugin_instance($name);
        if (!$plugin) {
            throw new RuntimeException('插件入口类不存在');
        }
        return $plugin;
    }

    private function assertName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            throw new RuntimeException('插件名称不合法');
        }
    }
}
