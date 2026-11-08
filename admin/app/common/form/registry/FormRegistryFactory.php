<?php

declare(strict_types=1);

namespace app\common\form\registry;

use app\common\form\action\FormActionRegistry;
use app\common\form\component\PluginFormComponentRegistry;
use app\common\form\dataSource\FormDataSourceRegistry;
use app\common\form\validation\FormAsyncValidatorRegistry;

/** 从同一份生产配置构造 FormSchema 所有依赖注册表。 */
final class FormRegistryFactory
{
    /**
     * @param string|null $businessRoot 生成业务模块根目录（含 {模块}/config/actions.php）；null 为 app/admin/business。
     */
    public function __construct(
        private readonly array $config,
        private readonly mixed $enabledPluginManifests = null,
        private readonly ?string $businessRoot = null
    ) {
    }

    public static function production(?callable $enabledPluginManifests = null): self
    {
        $config = function_exists('config') ? config('form', []) : [];
        if (!is_array($config) || $config === []) {
            $path = config_path('form.php');
            $config = is_file($path) ? require $path : [];
        }
        return new self(is_array($config) ? $config : [], $enabledPluginManifests);
    }

    /** 合并 config/form.php 的动作与各生成模块的注册清单；任一注册键重复均报错，绝不静默覆盖。 */
    public function actions(): FormActionRegistry
    {
        $actions = $this->section('actions');
        $sources = array_fill_keys(array_map('strval', array_keys($actions)), 'config/form.php');
        foreach ($this->businessActionFiles() as $directory => $file) {
            $source = 'app/admin/business/' . $directory . '/config/actions.php';
            $declared = require $file;
            if (!is_array($declared)) throw new \InvalidArgumentException('生成动作注册清单必须返回数组：' . $source);
            $prefix = 'business.' . \app\common\form\action\CustomActions::entity($directory) . '.';
            foreach ($declared as $key => $definition) {
                $key = (string) $key;
                if (!str_starts_with($key, $prefix)) {
                    throw new \InvalidArgumentException('生成动作注册键必须以 ' . $prefix . ' 开头：' . $key . '（' . $source . '）');
                }
                if (isset($sources[$key])) {
                    throw new \InvalidArgumentException('表单动作注册键冲突：' . $key . ' 同时声明于 ' . $sources[$key] . ' 与 ' . $source);
                }
                $sources[$key] = $source;
                $actions[$key] = $definition;
            }
        }
        return new FormActionRegistry($actions);
    }

    /** @return array<string, string> 模块目录名 → 注册清单绝对路径（按目录名排序，目录缺失时为空）。 */
    private function businessActionFiles(): array
    {
        $root = $this->businessRoot ?? (function_exists('app_path') ? app_path() . '/admin/business' : '');
        if ($root === '' || !is_dir($root)) return [];
        $files = [];
        foreach (scandir($root) ?: [] as $directory) {
            if (preg_match('/^[a-z][a-z0-9_]*$/D', $directory) !== 1) continue;
            $file = $root . '/' . $directory . '/config/actions.php';
            if (is_file($file)) $files[$directory] = $file;
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    /** @var array<string, string> 进程内缓存的确认密钥（键：DSN 与密钥表）。 */
    private static array $confirmationSecrets = [];

    /**
     * 专用非持久 PDO 连接，不复用业务事务，也不自动创建存储表。
     * 二次确认密钥在此惰性解析：首次构建时自动生成并持久化到 {前缀}system_secret，之后按进程缓存。
     */
    public function listExecutor(): \app\common\form\action\ListButtonExecutor
    {
        $store = $this->section('list_action_store');
        if (!is_string($store['dsn'] ?? null) || $store['dsn'] === '' || !is_string($store['table'] ?? null)
            || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $store['table'])) throw new \InvalidArgumentException('FORM_LIST_ATOMIC_STORE_UNAVAILABLE');
        try {
            $pdo = new \PDO($store['dsn'], $store['username'] ?? null, $store['password'] ?? null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_PERSISTENT => false,
            ]);
            $pdo->query('SELECT scope, digest, state, result FROM ' . $store['table'] . ' WHERE 1 = 0');
        } catch (\PDOException) {
            // 不传播底层连接异常，避免异常链泄露部署连接信息。
            throw new \InvalidArgumentException('FORM_LIST_ATOMIC_STORE_UNAVAILABLE');
        }
        return new \app\common\form\action\ListButtonExecutor($this->actions(), $this->confirmationSecret($pdo, $store),
            new \app\common\form\action\ListActionStore($pdo, $store['table']));
    }

    /** 仅供测试模拟进程重启。 */
    public static function forgetCachedSecrets(): void
    {
        self::$confirmationSecrets = [];
    }

    private function confirmationSecret(\PDO $pdo, array $store): string
    {
        $table = $store['secret_table'] ?? null;
        if (!is_string($table) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $table)) throw new \InvalidArgumentException('FORM_LIST_CONFIRMATION_UNAVAILABLE');
        $key = $store['dsn'] . "\0" . $table;
        if (isset(self::$confirmationSecrets[$key])) return self::$confirmationSecrets[$key];
        try {
            $secret = (new \app\common\service\security\SystemSecretStore($pdo, $table))->get(\app\common\service\security\SystemSecretStore::FORM_LIST_CONFIRMATION);
        } catch (\Throwable) {
            throw new \InvalidArgumentException('FORM_LIST_CONFIRMATION_UNAVAILABLE');
        }
        return self::$confirmationSecrets[$key] = $secret;
    }

    public function listResources(): \app\common\form\action\ListResourceRegistry
    {
        return new \app\common\form\action\ListResourceRegistry($this->section('list_resources'));
    }

    public function resourceExecutor(): \app\common\form\action\ListButtonExecutor
    {
        return new \app\common\form\action\ListButtonExecutor($this->actions(), '', resources: $this->listResources());
    }

    public function dataSources(): FormDataSourceRegistry
    {
        return FormDataSourceRegistry::core($this->section('data_sources'));
    }

    public function asyncValidators(): FormAsyncValidatorRegistry
    {
        return new FormAsyncValidatorRegistry($this->section('validators'));
    }

    public function pluginComponents(): PluginFormComponentRegistry
    {
        return new PluginFormComponentRegistry($this->enabledPluginManifests);
    }

    /** 合并核心能力与可信启用插件声明，核心定义不可覆盖。 */
    public function fieldCapabilities(): FieldCapabilityRegistry
    {
        return new FieldCapabilityRegistry($this->pluginComponents()->catalog());
    }

    private function section(string $key): array
    {
        return is_array($this->config[$key] ?? null) ? $this->config[$key] : [];
    }
}
