<?php

declare(strict_types=1);

namespace app\common\form\component;

use Closure;
use fun\plugins\Manifest;
use fun\plugins\PluginActivationReader;
use fun\plugins\PluginEntryFactory;
use RuntimeException;

/** 仅暴露可信激活清单中启用插件声明的表单组件。 */
final class PluginFormComponentRegistry
{
    /** @var array<string, object> */
    private array $entries = [];
    private readonly ?Closure $enabledManifests;
    private readonly ?Closure $entryResolver;

    public function __construct(?callable $enabledManifests = null, ?callable $entryResolver = null)
    {
        $this->enabledManifests = $enabledManifests === null ? null : Closure::fromCallable($enabledManifests);
        $this->entryResolver = $entryResolver === null ? null : Closure::fromCallable($entryResolver);
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

    /** 将数据库值解码为组件运行值。 */
    public function decode(string $type, mixed $value): mixed
    {
        return ($this->codec($type, 'decode'))($value);
    }

    /** 将组件运行值编码为数据库值。 */
    public function encode(string $type, mixed $value): mixed
    {
        return ($this->codec($type, 'encode'))($value);
    }

    /** 返回 null 表示合法，否则返回插件提供的错误消息。 */
    public function validateValue(string $type, mixed $value, array $values = [], array $options = []): ?string
    {
        $definition = $this->requiredDefinition($type);
        $validator = $this->trustedCallable($definition, 'validator');
        $result = $validator($value, $values, $options);
        if ($result === true) {
            return null;
        }
        if (is_string($result) && trim($result) !== '') {
            return $result;
        }
        return '插件组件值不合法：' . $type;
    }

    private function codec(string $type, string $operation): callable
    {
        $definition = $this->requiredDefinition($type);
        $entry = $this->entry((string) $definition['namespace']);
        if (!is_callable([$entry, 'formComponentCodecs'])) {
            throw new RuntimeException('插件未提供受信 codec 注册表：' . $type);
        }
        $codecs = $entry->formComponentCodecs();
        $reference = (string) ($definition['codec'] ?? '');
        $callable = is_array($codecs) ? ($codecs[$reference][$operation] ?? null) : null;
        return $this->assertEntryCallable($entry, $callable, 'codec', $reference);
    }

    private function trustedCallable(array $definition, string $capability): callable
    {
        $entry = $this->entry((string) $definition['namespace']);
        $provider = $capability === 'validator' ? 'formComponentValidators' : 'formComponentCodecs';
        if (!is_callable([$entry, $provider])) {
            throw new RuntimeException('插件未提供受信 ' . $capability . ' 注册表：' . $definition['type']);
        }
        $definitions = $entry->{$provider}();
        $reference = (string) ($definition[$capability] ?? '');
        $callable = is_array($definitions) ? ($definitions[$reference] ?? null) : null;
        return $this->assertEntryCallable($entry, $callable, $capability, $reference);
    }

    private function assertEntryCallable(object $entry, mixed $callable, string $capability, string $reference): callable
    {
        if (!is_array($callable) || count($callable) !== 2 || $callable[0] !== $entry
            || !is_string($callable[1]) || !is_callable($callable)) {
            throw new RuntimeException('插件 ' . $capability . ' 标识未解析到插件入口内受信 callable：' . $reference);
        }
        return $callable;
    }

    private function requiredDefinition(string $type): array
    {
        $definition = $this->definition($type);
        if ($definition === null) {
            throw new RuntimeException('插件组件未启用：' . $type);
        }
        return $definition;
    }

    private function entry(string $code): object
    {
        if (isset($this->entries[$code])) {
            return $this->entries[$code];
        }
        $manifest = $this->manifests()[$code] ?? null;
        if (!$manifest instanceof Manifest) {
            throw new RuntimeException('插件组件所属插件未启用：' . $code);
        }
        if ($this->entryResolver instanceof Closure) {
            $entry = ($this->entryResolver)($manifest);
        } else {
            $entry = (new PluginEntryFactory())->create(
                $manifest,
                static fn (string $class): object => function_exists('app') ? app()->make($class) : new $class()
            );
        }
        return $this->entries[$code] = $entry;
    }

    private function manifests(): array
    {
        if ($this->enabledManifests instanceof Closure) {
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
