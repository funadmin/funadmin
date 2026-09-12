<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use InvalidArgumentException;

/** 非 HTTP 插件执行描述符的可信值对象。 */
final class PluginActivationContext
{
    private function __construct(
        public readonly string $pluginCode,
        public readonly string $application,
        public readonly ?string $generation,
        public readonly string $class
    ) {
    }

    public static function fromDescriptor(array $descriptor): self
    {
        foreach (['plugin_code', 'application', 'class'] as $key) {
            if (!is_string($descriptor[$key] ?? null) || trim($descriptor[$key]) === '') {
                throw new InvalidArgumentException('插件执行 descriptor 缺少：' . $key);
            }
        }
        $pluginCode = trim($descriptor['plugin_code']);
        $application = trim($descriptor['application']);
        $class = trim($descriptor['class']);
        $generation = $descriptor['generation'] ?? null;
        if (preg_match('/^[a-z][a-z0-9]*$/', $pluginCode) !== 1
            || !in_array($application, ['app', 'console'], true)
            || preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $class) !== 1
            || ($generation !== null && (!is_string($generation) || preg_match('/^[a-f0-9]{32}$/', $generation) !== 1))) {
            throw new InvalidArgumentException('插件执行 descriptor 无效');
        }
        return new self($pluginCode, $application, $generation, $class);
    }
}
