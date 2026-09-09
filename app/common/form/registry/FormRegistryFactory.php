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
        return new self(is_array($config) ? $config : [], $enabledPluginManifests);
    }

    public function actions(): FormActionRegistry
    {
        return new FormActionRegistry($this->section('actions'));
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

    private function section(string $key): array
    {
        return is_array($this->config[$key] ?? null) ? $this->config[$key] : [];
    }
}
