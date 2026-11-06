<?php

declare(strict_types=1);
namespace app\common\plugin\hook;

use app\common\plugin\sdk\{ActivationGate, ActivationUnavailableException, PluginActivationReader, PluginNotActiveException};
use RuntimeException;
use Throwable;

/** Private PHP callable maps are loaded once per process; deployments require worker reload. */
final class HookRuntime
{
    private static array $maps = [];
    private readonly mixed $reporter;

    public function __construct(private readonly PluginActivationReader $reader, private readonly string $pluginsPath, ?callable $reporter = null)
    {
        $this->reporter = $reporter ?? static function (Throwable $error, array $context): void {
            error_log('Plugin hook ' . json_encode($context, JSON_UNESCAPED_UNICODE) . ': ' . $error->getMessage());
        };
    }

    public function action(string $point, mixed $payload = null, array $context = []): void
    {
        $this->dispatch('action', $point, $payload, $context, []);
    }

    public function filter(string $point, mixed $value, array $context = []): mixed
    {
        return $this->dispatch('filter', $point, $value, $context, []);
    }

    /**
     * Filter variant that isolates listener failures per plugin: a failing listener is reported and
     * $onFailure(value, pluginCode, error) decides the value passed to the next listener.
     * Snapshot or depth failures still throw, because no listener could be attributed.
     */
    public function filterIsolated(string $point, mixed $value, callable $onFailure, array $context = []): mixed
    {
        return $this->dispatch('filter', $point, $value, $context, [], $onFailure);
    }

    /** Internal chain entry, exposed only through the bound context dispatcher. */
    public function dispatch(string $type, string $point, mixed $value, array $context, array $chain, ?callable $onFilterFailure = null): mixed
    {
        $chain[] = $point;
        if (count($chain) > 32) {
            $error = new RuntimeException('Plugin hook maximum depth 32 exceeded: ' . implode(' -> ', $chain));
            $this->report($error, ['point' => $point, 'chain' => $chain]);
            throw $error;
        }
        $snapshot = $this->reader->read();
        if (!$snapshot->isTrusted()) {
            $error = new ActivationUnavailableException('插件钩子激活状态不可用');
            $this->report($error, ['point' => $point]);
            if ($type === 'filter') { throw $error; }
            return $value;
        }
        $gate = new ActivationGate($snapshot);
        $listeners = [];
        foreach ($snapshot->plugins() as $code => $plugin) {
            try { $gate->assertPluginEnabled($code); } catch (PluginNotActiveException) { continue; }
            foreach ($plugin['serverHooks'] ?? [] as $hook) {
                if ($hook['point'] === $point && $hook['type'] === $type) {
                    $listeners[] = $hook + ['pluginCode' => $code, 'hash' => $plugin['serverHookFiles'][$hook['file']]];
                }
            }
        }
        usort($listeners, static fn(array $a, array $b): int => [($a['priority'] ?? 100), $a['pluginCode'], $a['id']] <=> [($b['priority'] ?? 100), $b['pluginCode'], $b['id']]);
        foreach ($listeners as $hook) {
            $metadata = ['pluginCode' => $hook['pluginCode'], 'point' => $point, 'id' => $hook['id']];
            try {
                $file = self::ownedFile($this->pluginsPath, $hook['pluginCode'], $hook['file']);
                if (!hash_equals($hook['hash'], hash_file('sha256', $file))) { throw new RuntimeException('Plugin hook file hash mismatch'); }
                if (!array_key_exists($file, self::$maps)) {
                    try {
                        \app\common\plugin\sdk\PluginVendorAutoloader::ensure(rtrim($this->pluginsPath, '/\\') . '/' . $hook['pluginCode']);
                        $map = (static fn(string $path): mixed => include $path)($file);
                        if (!is_array($map)) { throw new RuntimeException('Plugin hook file must return a callable map'); }
                        self::$maps[$file] = $map;
                    } catch (Throwable $error) {
                        self::$maps[$file] = $error;
                    }
                }
                if (self::$maps[$file] instanceof Throwable) { throw self::$maps[$file]; }
                $handler = self::$maps[$file][$hook['handler']] ?? null;
                if (!is_callable($handler)) { throw new RuntimeException('Plugin hook handler is not callable'); }
                $bound = new BoundHooks($this, $chain);
                $result = $handler($value, array_replace($context, $metadata, ['hooks' => $bound]));
                if ($type === 'filter') { $value = $result; }
            } catch (Throwable $error) {
                $this->report($error, $metadata);
                if ($type === 'filter') {
                    if ($onFilterFailure === null) { throw $error; }
                    $value = $onFilterFailure($value, $hook['pluginCode'], $error);
                }
            }
        }
        return $value;
    }

    private function report(Throwable $error, array $context): void
    {
        try { ($this->reporter)($error, $context); } catch (Throwable) { /* logging must not change dispatch semantics */ }
    }

    public static function ownedFile(string $pluginsPath, string $code, string $relative): string
    {
        if (preg_match('/^[a-z][a-z0-9]*$/D', $code) !== 1 || preg_match('/^hooks\/[A-Za-z0-9_.\/-]+\.php$/D', $relative) !== 1
            || in_array('..', explode('/', $relative), true) || in_array('.', explode('/', $relative), true)) {
            throw new RuntimeException('Invalid private plugin hook path');
        }
        $base = rtrim($pluginsPath, DIRECTORY_SEPARATOR);
        $path = $base;
        foreach (array_merge([$code], explode('/', $relative)) as $part) {
            $path .= DIRECTORY_SEPARATOR . $part;
            if (is_link($path)) { throw new RuntimeException('Plugin hook symlinks are forbidden'); }
        }
        $root = realpath($base);
        $resolved = realpath($path);
        if ($root === false || $resolved === false || !is_file($resolved) || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Plugin hook file is not owned by plugin');
        }
        return $resolved;
    }
}
