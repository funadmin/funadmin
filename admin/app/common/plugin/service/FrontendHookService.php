<?php

declare(strict_types=1);

namespace app\common\plugin\service;

use app\common\plugin\sdk\ActivationGate;
use app\common\plugin\sdk\ActivationUnavailableException;
use app\common\plugin\sdk\PluginActivationReader;
use app\common\plugin\sdk\PluginNotActiveException;
use app\common\plugin\sdk\Registry;
use Closure;
use InvalidArgumentException;

/** Read-only audience projection. Never loads a plugin entry or hook handler. */
final class FrontendHookService
{
    private PluginActivationReader $reader;
    private Closure $discover;

    public function __construct(?PluginActivationReader $reader = null, ?callable $discover = null)
    {
        $this->reader = $reader ?? new PluginActivationReader(runtime_path('plugins/activation'));
        $this->discover = Closure::fromCallable($discover ?? static function (): array {
            $directory = base_path(defined('PLUGIN_DIR') ? PLUGIN_DIR : 'plugins');
            return (new Registry($directory, static fn (): array => []))->discover();
        });
    }

    public function descriptors(string $audience, ?array $permissions = null, bool $includeRoutes = false): array
    {
        if (!in_array($audience, ['public', 'member', 'admin'], true)) {
            throw new InvalidArgumentException('Invalid hook audience');
        }
        $snapshot = $this->reader->read();
        $generation = $this->reader->activeGeneration() ?? '';
        $result = ['generation' => $generation, 'modules' => []];
        if (!$snapshot->isTrusted()) {
            return $result;
        }

        $gate = new ActivationGate($snapshot);
        foreach (($this->discover)() as $code => $manifest) {
            try {
                $gate->assertPluginEnabled($code);
            } catch (PluginNotActiveException|ActivationUnavailableException) {
                continue;
            }
            $data = $manifest->toArray();
            $web = $data['adminWeb'] ?? null;
            if (!is_array($web)) {
                continue;
            }
            $hooks = [];
            $components = [];
            foreach ($data['frontendHooks'] ?? [] as $hook) {
                if (!in_array($audience, $hook['audiences'] ?? ['admin'], true)
                    || !$this->allowed($hook['permission'] ?? '', $permissions)) {
                    continue;
                }
                $hook['priority'] ??= 100;
                $hook['audiences'] ??= ['admin'];
                $hooks[] = $hook;
                if (isset($hook['component'], $web['components'][$hook['component']])) {
                    $components[$hook['component']] = $web['components'][$hook['component']];
                }
            }
            usort($hooks, static fn (array $a, array $b): int => [$a['priority'], $a['id']] <=> [$b['priority'], $b['id']]);

            $routes = [];
            if ($includeRoutes && $audience === 'admin') {
                foreach ($web['routes'] ?? [] as $route) {
                    if (!$this->allowed($route['meta']['permission'] ?? '', $permissions)) {
                        continue;
                    }
                    $routes[] = $route;
                    if (isset($web['components'][$route['component']])) {
                        $components[$route['component']] = $web['components'][$route['component']];
                    }
                }
            }
            if ($hooks === [] && $routes === []) {
                continue;
            }
            $module = [
                'code' => $code,
                'version' => $snapshot->plugins()[$code]['version'],
                'generation' => $generation,
                'components' => $components === [] ? (object) [] : $components,
                'frontendHooks' => $hooks,
            ];
            if ($includeRoutes) {
                $module['routes'] = $routes;
            }
            $result['modules'][] = $module;
        }
        usort($result['modules'], static fn (array $a, array $b): int => $a['code'] <=> $b['code']);
        return $result;
    }

    private function allowed(string $permission, ?array $permissions): bool
    {
        return $permission === ''
            || $permissions === null
            || in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);
    }
}
