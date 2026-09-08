<?php

declare(strict_types=1);

namespace app\common\form\component;

use fun\plugins\Manifest;
use fun\plugins\PluginActivationReader;

/** 仅暴露可信激活清单中启用插件声明的表单组件。 */
final class PluginFormComponentRegistry
{
    public function __construct(private readonly mixed $enabledManifests = null)
    {
    }

    public function catalog(): array
    {
        $components = [];
        foreach ($this->manifests() as $code => $manifest) {
            if (!$manifest instanceof Manifest) {
                continue;
            }
            foreach ((array) ($manifest->toArray()['formComponents'] ?? []) as $definition) {
                $components[] = $definition + ['namespace' => (string) $code];
            }
        }
        usort($components, static fn (array $left, array $right): int => strcmp((string) $left['type'], (string) $right['type']));
        return $components;
    }

    public function definition(string $type): ?array
    {
        foreach ($this->catalog() as $definition) {
            if (($definition['type'] ?? null) === $type) {
                return $definition;
            }
        }
        return null;
    }

    private function manifests(): array
    {
        if (is_callable($this->enabledManifests)) {
            return (array) ($this->enabledManifests)();
        }
        $root = function_exists('root_path') ? root_path() : dirname(__DIR__, 4) . DIRECTORY_SEPARATOR;
        $snapshot = (new PluginActivationReader($root . 'runtime/plugins/activation'))->read();
        if (!$snapshot->isTrusted()) {
            return [];
        }
        $manifests = [];
        foreach ($snapshot->plugins() as $code => $plugin) {
            if (($plugin['enabled'] ?? false) !== true || ($plugin['state'] ?? '') !== 'enabled') {
                continue;
            }
            try {
                $pluginDirectory = defined('PLUGIN_DIR') ? PLUGIN_DIR : 'plugins';
                $manifests[$code] = Manifest::fromDirectory($root . $pluginDirectory . DIRECTORY_SEPARATOR . $code);
            } catch (\Throwable) {
                continue;
            }
        }
        return $manifests;
    }
}
