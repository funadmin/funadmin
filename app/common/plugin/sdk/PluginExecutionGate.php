<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use RuntimeException;

/** CLI、Event、Queue 与 Provider 的显式插件执行代理。 */
final class PluginExecutionGate
{
    public function __construct(private readonly PluginActivationReader $reader)
    {
    }

    public function executeCommand(array $descriptor, array $payload = []): mixed
    {
        return $this->invoke($descriptor, 'command', [$payload]);
    }

    public function dispatchListener(array $descriptor, mixed $event): mixed
    {
        return $this->invoke($descriptor, 'listener', [$event]);
    }

    public function runJob(array $descriptor, array $payload = []): mixed
    {
        return $this->invoke($descriptor, 'job', [$payload]);
    }

    public function registerProvider(array $descriptor, mixed $application): mixed
    {
        return $this->invoke($descriptor, 'provider', [$application]);
    }

    private function invoke(array $descriptor, string $method, array $arguments): mixed
    {
        $context = PluginActivationContext::fromDescriptor($descriptor);
        $snapshot = $this->reader->read();
        (new ActivationGate($snapshot))->assertEnabled($context->pluginCode, $context->application);
        if ($context->generation !== null && $this->reader->activeGeneration() !== $context->generation) {
            throw new RuntimeException('插件执行 generation 已失效');
        }
        $applicationPrefix = 'app\\' . $context->pluginCode . '\\';
        $consoleMarker = '\\plugin\\' . $context->pluginCode . '\\';
        $entryPrefix = 'plugins\\' . $context->pluginCode . '\\';
        $owned = str_starts_with($context->class, $applicationPrefix)
            || (str_starts_with($context->class, 'app\\console\\') && str_contains($context->class, $consoleMarker))
            || str_starts_with($context->class, $entryPrefix);
        if (!$owned) {
            throw new RuntimeException('插件执行类不属于当前插件');
        }
        if (!class_exists($context->class)) {
            throw new RuntimeException('插件执行类不存在：' . $context->class);
        }
        $instance = new $context->class();
        if (!is_callable([$instance, $method])) {
            throw new RuntimeException('插件执行类缺少边界方法：' . $method);
        }
        return $instance->{$method}(...$arguments);
    }
}
