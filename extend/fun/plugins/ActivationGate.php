<?php

declare(strict_types=1);

namespace fun\plugins;

final class ActivationGate
{
    public function __construct(private readonly PluginActivationReader $reader)
    {
    }

    public function assertEnabled(string $code, string $application): void
    {
        $snapshot = $this->reader->read();
        if (!$snapshot->isTrusted()) {
            throw new ActivationUnavailableException('插件激活状态不可用');
        }
        $plugins = $snapshot->plugins();
        $plugin = $plugins[$code] ?? null;
        if (!is_array($plugin) || !$this->enabled($plugin, $application, $plugins)) {
            throw new PluginNotActiveException('插件不可用');
        }
    }

    private function enabled(array $plugin, string $application, array $plugins, array $visited = []): bool
    {
        if (($plugin['enabled'] ?? false) !== true
            || ($plugin['state'] ?? '') !== 'enabled'
            || ($plugin['needs_reinstall'] ?? true) !== false
            || ($plugin['operation_token'] ?? null) !== null
            || ($plugin['applications'][$application] ?? false) !== true) {
            return false;
        }
        $code = (string) ($plugin['code'] ?? '');
        if ($code === '' || isset($visited[$code])) {
            return false;
        }
        $visited[$code] = true;
        foreach ((array) ($plugin['dependencies'] ?? []) as $dependency) {
            $required = $plugins[$dependency] ?? null;
            if (!is_array($required) || !$this->dependencyEnabled($required, $plugins, $visited)) {
                return false;
            }
        }
        return true;
    }

    private function dependencyEnabled(array $plugin, array $plugins, array $visited): bool
    {
        $plugin['applications']['dependency'] = true;
        return $this->enabled($plugin, 'dependency', $plugins, $visited);
    }
}
