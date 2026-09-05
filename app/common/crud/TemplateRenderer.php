<?php

declare(strict_types=1);

namespace app\common\crud;

use InvalidArgumentException;
use RuntimeException;

/**
 * 无代码执行能力的受限占位符渲染器。
 */
final class TemplateRenderer
{
    public function __construct(private readonly string $templateRoot)
    {
    }

    public function render(string $template, array $context): string
    {
        $path = PathGuard::resolve($this->templateRoot, $template, '模板');
        if (!is_file($path)) {
            throw new RuntimeException('模板不存在：' . $template);
        }
        $source = file_get_contents($path);
        if ($source === false) {
            throw new RuntimeException('无法读取模板：' . $template);
        }
        $rendered = preg_replace_callback('/\{\{([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function (array $match) use ($context): string {
            if (!array_key_exists($match[1], $context) || !is_scalar($context[$match[1]])) {
                throw new InvalidArgumentException('模板上下文缺少标量值：' . $match[1]);
            }
            return $this->escape((string) $context[$match[1]], $match[1]);
        }, $source);
        if ($rendered === null || preg_match('/\{\{[^}]+\}\}/', $rendered)) {
            throw new InvalidArgumentException('模板包含不受支持的占位符');
        }
        return $rendered;
    }

    private function escape(string $value, string $key): string
    {
        return match (true) {
            in_array($key, ['phpClass', 'modelClass'], true) => $this->assert($value, '/^[A-Z][A-Za-z0-9]*$/', $key),
            in_array($key, ['apiPrefix'], true) => $this->assert($value, '#^/[a-z][a-z0-9/-]*$#', $key),
            in_array($key, ['permissionPrefix'], true) => $this->assert($value, '/^[a-z][a-z0-9:-]*$/', $key),
            in_array($key, ['table', 'field', 'name'], true) => $this->assert($value, '/^[a-z_][a-z0-9_-]*$/', $key),
            str_ends_with($key, 'Json') => $value,
            default => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        };
    }

    private function assert(string $value, string $pattern, string $key): string
    {
        if (!preg_match($pattern, $value)) {
            throw new InvalidArgumentException('模板上下文不安全：' . $key);
        }
        return $value;
    }
}
