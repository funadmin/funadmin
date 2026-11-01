<?php

declare(strict_types=1);

namespace Tests\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RuntimeException;

/** 静态盘点旧、新注解，不加载尚未完成迁移的控制器。 */
final class AdminRouteInventory
{
    public static function collect(): array
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $finder = new NodeFinder();
        $routes = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('admin'), \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(app_path('admin')) + 1));
            if ($file->getExtension() !== 'php' || !str_contains('/' . $relative, '/controller/')
                || preg_match('~^(?:controller/(?:generated|plugin/)|plugin/business/)~', $relative)) {
                continue;
            }
            $nodes = $parser->parse(file_get_contents($file->getPathname())) ?? [];
            $namespace = $finder->findFirstInstanceOf($nodes, Node\Stmt\Namespace_::class);
            $class = $finder->findFirstInstanceOf($nodes, Node\Stmt\Class_::class);
            if ($namespace === null || $class === null || $class->name === null || $class->isAbstract()) continue;
            $imports = [];
            foreach ($finder->findInstanceOf($nodes, Node\Stmt\Use_::class) as $use) {
                foreach ($use->uses as $item) $imports[$item->getAlias()->toString()] = $item->name->toString();
            }
            $attributes = self::attributes($class->attrGroups);
            $group = $attributes['RouteGroup'][0] ?? $attributes['Group'][0] ?? null;
            if ($group === null) continue;
            $native = isset($attributes['RouteGroup']);
            $prefix = self::string($group->args[0]->value);
            $prefix = $native ? rtrim($prefix, '/') : '/admin' . ($prefix === '' ? '' : '/' . trim($prefix, '/'));
            $module = explode('/', $relative)[0];
            if ($module === 'controller') $module = explode('/', $relative)[1];
            foreach ($class->getMethods() as $method) {
                $methodAttributes = self::attributes($method->attrGroups);
                $patterns = self::patterns($attributes) + self::patterns($methodAttributes);
                foreach (['Get', 'Post', 'Put', 'Patch', 'Delete', 'Head', 'Options', 'Any', 'Route'] as $verb) {
                    foreach ($methodAttributes[$verb] ?? [] as $attribute) {
                        if (!isset($attribute->args[0]) || $attribute->args[0]->value instanceof Node\Expr\ConstFetch) continue;
                        $path = self::string($attribute->args[0]->value);
                        if (!$native) {
                            $path = preg_replace_callback('/(?<![A-Za-z0-9_]):([A-Za-z_][A-Za-z0-9_]*)/', static fn (array $match): string => '{' . $match[1] . ':' . ($patterns[$match[1]] ?? '[^/]+') . '}', $path);
                            foreach ($patterns as $name => $pattern) $path = str_replace('{' . $name . '}', '{' . $name . ':' . $pattern . '}', $path);
                            $path = $path === '' ? '' : '/' . ltrim($path, '/');
                        }
                        $methods = $verb === 'Any' ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'] : [strtoupper($verb)];
                        if ($verb === 'Route') {
                            $argument = $attribute->args[1]->value ?? throw new RuntimeException('Route 缺少请求方法');
                            $methods = $argument instanceof Node\Expr\Array_ ? array_map(static fn ($item): string => strtoupper(self::string($item->value)), $argument->items) : [strtoupper(self::string($argument))];
                        }
                        foreach ($methods as $httpMethod) {
                            $routes[] = [
                                'module' => $module,
                                'method' => $httpMethod,
                                'path' => $prefix . $path,
                                'controller' => $namespace->name->toString() . '\\' . $class->name->toString(),
                                'action' => $method->name->toString(),
                                'native' => $native,
                                'file' => $relative,
                                'dependencies' => array_values($imports),
                            ];
                        }
                    }
                }
            }
        }
        usort($routes, static fn (array $a, array $b): int => [$a['path'], $a['method']] <=> [$b['path'], $b['method']]);
        return $routes;
    }

    private static function attributes(array $groups): array
    {
        $attributes = [];
        foreach ($groups as $group) {
            foreach ($group->attrs as $attribute) $attributes[$attribute->name->getLast()][] = $attribute;
        }
        return $attributes;
    }

    private static function patterns(array $attributes): array
    {
        $patterns = [];
        foreach ($attributes['Pattern'] ?? [] as $attribute) {
            $patterns[self::string($attribute->args[0]->value)] = self::string($attribute->args[1]->value);
        }
        return $patterns;
    }

    private static function string(Node $node): string
    {
        if (!$node instanceof Node\Scalar\String_) throw new RuntimeException('路由参数必须为静态字符串');
        return $node->value;
    }
}
