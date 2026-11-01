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
    public function __construct(
        private readonly array $config,
        private readonly mixed $enabledPluginManifests = null
    ) {
    }

    public static function production(?callable $enabledPluginManifests = null): self
    {
        $config = function_exists('config') ? config('form', []) : [];
        if (!is_array($config) || $config === []) {
            $path = dirname(__DIR__, 4) . '/config/form.php';
            // 未启动应用时（CLI 工具、隔离测试）容器没有 env，配置文件中的 Env 门面会直接崩溃。
            $container = \think\Container::getInstance();
            if (!$container->bound('env')) {
                $container->instance('env', new \think\Env());
            }
            $config = is_file($path) ? require $path : [];
        }
        return new self(is_array($config) ? $config : [], $enabledPluginManifests);
    }

    public function actions(): FormActionRegistry
    {
        return new FormActionRegistry($this->section('actions'));
    }

    /** 专用非持久 PDO 连接，不复用业务事务，也不自动创建存储表。 */
    public function listExecutor(): \app\common\form\action\ListButtonExecutor
    {
        $secret = $this->config['list_confirmation_secret'] ?? '';
        if (!is_string($secret) || strlen($secret) < 32) throw new \InvalidArgumentException('FORM_LIST_CONFIRMATION_UNAVAILABLE');
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
        return new \app\common\form\action\ListButtonExecutor($this->actions(), $secret,
            new \app\common\form\action\ListActionStore($pdo, $store['table']));
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
