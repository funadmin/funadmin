<?php

declare(strict_types=1);

namespace Tests\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * 静态核对控制器路由属性与 OpenAPI PHPDoc 操作（swagger-php 未安装，因此不加载控制器、不依赖其 Generator）。
 *
 * 路由按 Webman Route::buildAnnotationRouteDefinitions 的规则还原：类上 RouteGroup 前缀（补前导 /、去尾部 /）
 * 拼接方法路由路径；只看本类声明的 public 非构造方法；路径为 null 的路由属性不注册。
 * OpenAPI path 把 `{name:regex}` 写作 `{name}`。同一路由同时声明 GET 与 HEAD 时，HEAD 由 GET 操作覆盖。
 */
final class OpenApiCoverage
{
    private const ROUTE_NAMESPACE = 'support\\annotation\\route\\';
    private const VERB_ATTRIBUTES = ['Get' => 'GET', 'Post' => 'POST', 'Put' => 'PUT', 'Delete' => 'DELETE', 'Patch' => 'PATCH', 'Head' => 'HEAD', 'Options' => 'OPTIONS'];
    private const OA_VERBS = 'Get|Post|Put|Delete|Patch|Head|Options';
    /** admin/app 下入库的核心应用目录（见 admin/.gitignore 的 ! 例外）；其余顶层目录是插件安装副本。 */
    private const CORE_APPLICATIONS = ['admin', 'api', 'common', 'install', 'middleware', 'process', 'view'];
    /** app/admin 下不入库的目录：插件后台安装副本与代码生成器产出的业务模块。 */
    private const IGNORED_ADMIN_DIRECTORIES = ['plugins', 'business'];

    /**
     * 需要核对的控制器文件：核心 app/**\/controller（不含插件安装副本与生成的业务模块）以及插件源码 plugins/*\/app/**\/controller。
     *
     * @return list<string> 绝对路径，已排序
     */
    public static function controllerFiles(string $adminRoot): array
    {
        $adminRoot = rtrim($adminRoot, '/');
        $roots = [];
        foreach (self::CORE_APPLICATIONS as $application) {
            if (is_dir($adminRoot . '/app/' . $application)) $roots[] = $adminRoot . '/app/' . $application;
        }
        foreach (glob($adminRoot . '/plugins/*/app', GLOB_ONLYDIR) ?: [] as $pluginApp) $roots[] = $pluginApp;
        $files = [];
        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                if ($file->getExtension() !== 'php' || !str_contains($path, '/controller/')) continue;
                $relative = substr($path, strlen($adminRoot) + 1);
                if (preg_match('~^app/admin/(?:' . implode('|', self::IGNORED_ADMIN_DIRECTORIES) . ')/~', $relative) === 1) continue;
                if (preg_match('~^plugins/[^/]+/vendor/~', $relative) === 1) continue;
                $files[] = $path;
            }
        }
        sort($files);
        return $files;
    }

    /**
     * 解析一个控制器源码中的路由方法。
     *
     * @param array<string,array<string,list<array{verbs:list<string>,path:string}>>> $extraRoutes config/route.php 注册的路由，按 类 => 方法 分组
     * @param string|null $adminRoot 用于定位 trait 源码；默认为本仓库 admin/
     * @return list<array{class:string,method:string,routes:list<array{verbs:list<string>,path:string,openApiPath:string}>,doc:string}>
     */
    public static function routedMethods(string $source, array $extraRoutes = [], ?string $adminRoot = null): array
    {
        $adminRoot ??= dirname(__DIR__, 2);
        $nodes = self::parse($source);
        $result = [];
        foreach ((new NodeFinder())->findInstanceOf($nodes, Node\Stmt\Class_::class) as $class) {
            if ($class->isAbstract() || $class->namespacedName === null) continue;
            $className = $class->namespacedName->toString();
            self::assertTraitsHaveNoRoutes($class, $className, $adminRoot, []);
            $prefix = '';
            $grouped = false;
            foreach (self::attributes($class->attrGroups) as [$name, $attribute]) {
                $short = self::routeAttribute($name, $className);
                if ($short === null || $short === 'DisableDefaultRoute') continue;
                if ($short !== 'RouteGroup') throw new \RuntimeException('类上不支持的路由属性 ' . $name . '：' . $className);
                // 与 Webman 一致：只取第一个 RouteGroup。
                if (!$grouped) $prefix = self::normalizePrefix((string) self::argument($attribute, 0, 'prefix', ''));
                $grouped = true;
            }
            foreach ($class->getMethods() as $method) {
                $methodName = $method->name->toString();
                $routes = [];
                foreach ($extraRoutes[$className][$methodName] ?? [] as $route) {
                    $routes[] = ['verbs' => $route['verbs'], 'path' => $route['path'], 'openApiPath' => self::openApiPath($route['path'])];
                }
                $public = $method->isPublic() && !in_array(strtolower($methodName), ['__construct', '__destruct'], true);
                foreach (self::attributes($method->attrGroups) as [$name, $attribute]) {
                    $short = self::routeAttribute($name, $className . '::' . $methodName);
                    if ($short === null || !$public) continue;
                    if ($short === 'Route') {
                        $path = self::argument($attribute, 0, 'path', null);
                        $verbs = self::argument($attribute, 1, 'methods', ['GET']);
                        $verbs = array_map('strtoupper', is_array($verbs) ? $verbs : [$verbs]);
                    } elseif (isset(self::VERB_ATTRIBUTES[$short])) {
                        $path = self::argument($attribute, 0, 'path', null);
                        $verbs = [self::VERB_ATTRIBUTES[$short]];
                    } elseif ($short === 'Any') {
                        throw new \RuntimeException('OpenAPI 覆盖检查不支持 #[Any]，请改为显式请求方法：' . $className . '::' . $methodName);
                    } else {
                        throw new \RuntimeException('方法上不支持的路由属性 ' . $name . '：' . $className . '::' . $methodName);
                    }
                    if ($path === null) continue; // 与 Webman 一致：路径为 null 只限制默认路由的方法，不注册路由。
                    $full = $prefix . trim((string) $path);
                    $routes[] = ['verbs' => array_values($verbs), 'path' => $full, 'openApiPath' => self::openApiPath($full)];
                }
                if ($routes === []) continue;
                $result[] = [
                    'class' => $className,
                    'method' => $methodName,
                    'routes' => $routes,
                    'doc' => (string) $method->getDocComment()?->getText(),
                ];
            }
        }
        return $result;
    }

    /**
     * 静态解析 config/route.php 中指向控制器方法的 Route::get/post/put/delete/patch/head/options/add 调用（含 Route::group 前缀）。
     * 闭包回调（如 /health）没有控制器方法可挂注释，计入 skipped；fallback、disableDefaultRoute 等不是路由声明。
     *
     * @return array{routes:array<string,array<string,list<array{verbs:list<string>,path:string}>>>,skipped:list<string>}
     */
    public static function configRoutes(string $source): array
    {
        $routes = [];
        $skipped = [];
        self::collectConfigRoutes(self::parse($source), '', $routes, $skipped);
        return ['routes' => $routes, 'skipped' => $skipped];
    }

    /** @param array<Node> $statements */
    private static function collectConfigRoutes(array $statements, string $prefix, array &$routes, array &$skipped): void
    {
        foreach ($statements as $statement) {
            if (!$statement instanceof Node\Stmt\Expression || !$statement->expr instanceof Node\Expr\StaticCall) continue;
            $call = $statement->expr;
            if (!$call->class instanceof Node\Name || ltrim($call->class->toString(), '\\') !== 'Webman\\Route' || !$call->name instanceof Node\Identifier) continue;
            $name = strtolower($call->name->toString());
            $args = $call->getArgs();
            if ($name === 'group') {
                $groupPrefix = $prefix . (string) self::value($args[0]->value);
                $closure = $args[1]->value ?? null;
                if (!$closure instanceof Node\Expr\Closure && !$closure instanceof Node\Expr\ArrowFunction) throw new \RuntimeException('Route::group 回调必须为闭包');
                self::collectConfigRoutes($closure instanceof Node\Expr\Closure ? $closure->stmts : [new Node\Stmt\Expression($closure->expr)], $groupPrefix, $routes, $skipped);
                continue;
            }
            if ($name === 'any') throw new \RuntimeException('OpenAPI 覆盖检查不支持 Route::any，请改为显式请求方法');
            if ($name === 'add') {
                $verbs = self::value($args[0]->value);
                $verbs = array_map('strtoupper', is_array($verbs) ? $verbs : [$verbs]);
                $pathArg = $args[1]->value; $callback = $args[2]->value;
            } elseif (in_array($name, ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'], true)) {
                $verbs = [strtoupper($name)];
                $pathArg = $args[0]->value; $callback = $args[1]->value;
            } else {
                continue;
            }
            $path = $prefix . (string) self::value($pathArg);
            if ($callback instanceof Node\Expr\Array_ && count($callback->items) === 2
                && $callback->items[0]?->value instanceof Node\Expr\ClassConstFetch && $callback->items[1]?->value instanceof Node\Scalar\String_) {
                $class = ltrim($callback->items[0]->value->class->toString(), '\\');
                $routes[$class][$callback->items[1]->value->value][] = ['verbs' => array_values($verbs), 'path' => $path];
                continue;
            }
            if ($callback instanceof Node\Expr\Closure || $callback instanceof Node\Expr\ArrowFunction) {
                $skipped[] = implode('|', $verbs) . ' ' . $path;
                continue;
            }
            throw new \RuntimeException('无法静态解析的路由回调：' . implode('|', $verbs) . ' ' . $path);
        }
    }

    /** @return array<Node> */
    private static function parse(string $source): array
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse($source) ?? [];
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        return $traverser->traverse($nodes);
    }

    /** 路由属性的短名；非路由属性返回 null；与路由属性同名但不在 Webman 命名空间的属性视为无法识别。 */
    private static function routeAttribute(string $name, string $where): ?string
    {
        $short = substr($name, (int) strrpos('\\' . $name, '\\'));
        if (str_starts_with($name, self::ROUTE_NAMESPACE)) return substr($name, strlen(self::ROUTE_NAMESPACE));
        if (in_array($short, [...array_keys(self::VERB_ATTRIBUTES), 'Route', 'RouteGroup', 'Any'], true)) {
            throw new \RuntimeException('无法识别的路由属性 ' . $name . '（应为 ' . self::ROUTE_NAMESPACE . $short . '）：' . $where);
        }
        return null;
    }

    /**
     * Webman 把 trait 方法当作控制器自身声明的方法注册路由；检查器不展开 trait 的可见性别名，因此要求 trait 不带路由属性。
     *
     * @param array<string,true> $seen
     */
    private static function assertTraitsHaveNoRoutes(Node\Stmt\ClassLike $class, string $owner, string $adminRoot, array $seen): void
    {
        foreach ($class->getTraitUses() as $use) {
            foreach ($use->traits as $trait) {
                $name = ltrim($trait->toString(), '\\');
                if (isset($seen[$name])) continue;
                $seen[$name] = true;
                $file = self::traitFile($name, $adminRoot);
                if ($file === null) {
                    if (str_starts_with($name, 'app\\')) throw new \RuntimeException('无法定位 trait 源码 ' . $name . '：' . $owner);
                    continue; // 第三方 trait 不属于本仓库路由。
                }
                foreach ((new NodeFinder())->findInstanceOf(self::parse((string) file_get_contents($file)), Node\Stmt\Trait_::class) as $node) {
                    if ($node->namespacedName?->toString() !== $name) continue;
                    foreach ($node->getMethods() as $method) {
                        foreach (self::attributes($method->attrGroups) as [$attributeName]) {
                            if (self::routeAttribute($attributeName, $name . '::' . $method->name) !== null) {
                                throw new \RuntimeException('trait 方法带路由属性，请在控制器中声明路由：' . $name . '::' . $method->name . '（' . $owner . '）');
                            }
                        }
                    }
                    self::assertTraitsHaveNoRoutes($node, $owner, $adminRoot, $seen);
                }
            }
        }
    }

    /** 插件命名空间指向插件源码（不读安装副本），其余 app\ 按 PSR-4 指向 app/，第三方交给 Composer。 */
    private static function traitFile(string $trait, string $adminRoot): ?string
    {
        $adminRoot = rtrim($adminRoot, '/');
        $candidates = [];
        if (preg_match('/^app\\\\admin\\\\plugins\\\\([a-z][a-z0-9]*)\\\\(.+)$/', $trait, $match) === 1) {
            $candidates[] = "{$adminRoot}/plugins/{$match[1]}/app/admin/plugins/{$match[1]}/" . str_replace('\\', '/', $match[2]) . '.php';
        } elseif (preg_match('/^app\\\\([a-z][a-z0-9]*)\\\\(.+)$/', $trait, $match) === 1 && is_dir("{$adminRoot}/plugins/{$match[1]}")) {
            $candidates[] = "{$adminRoot}/plugins/{$match[1]}/app/{$match[1]}/" . str_replace('\\', '/', $match[2]) . '.php';
        }
        if (str_starts_with($trait, 'app\\')) $candidates[] = $adminRoot . '/' . str_replace('\\', '/', $trait) . '.php';
        foreach ($candidates as $candidate) if (is_file($candidate)) return $candidate;
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
            $file = $loader->findFile($trait);
            if (is_string($file) && is_file($file)) return $file;
        }
        return null;
    }

    /**
     * 解析 PHPDoc 中的 OpenAPI 操作。
     *
     * @return list<array{verb:string,path:?string,operationId:?string,body:string}>
     */
    public static function operations(string $doc): array
    {
        $text = (string) preg_replace('~^\s*/?\*+/?~m', '', $doc);
        $operations = [];
        $offset = 0;
        while (preg_match('/@OA\\\\(' . self::OA_VERBS . ')\s*\(/', $text, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $open = $match[0][1] + strlen($match[0][0]) - 1;
            $body = self::balanced($text, $open);
            $offset = $open + strlen($body) + 2;
            $operations[] = [
                'verb' => strtoupper($match[1][0]),
                'path' => preg_match('/(?<![A-Za-z])path\s*=\s*"([^"]*)"/', $body, $path) === 1 ? $path[1] : null,
                'operationId' => preg_match('/\boperationId\s*=\s*"([^"]*)"/', $body, $id) === 1 ? $id[1] : null,
                'body' => $body,
            ];
        }
        return $operations;
    }

    /**
     * 核对一个控制器源码：每个路由方法都有对应动词与完整 path 的操作，且没有多余操作。
     *
     * @return array{errors:list<string>,operations:list<array{operationId:?string,verb:string,path:?string,tags:list<string>,source:string}>}
     */
    public static function check(string $source, string $label, array $extraRoutes = [], ?string $adminRoot = null): array
    {
        $errors = [];
        $found = [];
        foreach (self::routedMethods($source, $extraRoutes, $adminRoot) as $routed) {
            $where = $label . ' ' . $routed['class'] . '::' . $routed['method'];
            $operations = self::operations($routed['doc']);
            $expected = [];
            foreach ($routed['routes'] as $route) {
                foreach ($route['verbs'] as $verb) {
                    if ($verb === 'HEAD' && in_array('GET', $route['verbs'], true)) continue;
                    $expected[$verb . ' ' . $route['openApiPath']] = true;
                }
            }
            $documented = [];
            foreach ($operations as $operation) {
                $key = $operation['verb'] . ' ' . $operation['path'];
                $documented[$key] = true;
                if ($operation['path'] === null) $errors[] = $where . '：@OA\\' . ucfirst(strtolower($operation['verb'])) . ' 缺少 path';
                elseif (!isset($expected[$key])) $errors[] = $where . '：操作 ' . $key . ' 与路由不一致（路由：' . implode('、', array_keys($expected)) . '）';
                if ($operation['operationId'] === null || $operation['operationId'] === '') $errors[] = $where . '：' . $key . ' 缺少 operationId';
                if (!str_contains($operation['body'], '@OA\\Response')) $errors[] = $where . '：' . $key . ' 缺少 @OA\\Response';
                foreach (self::pathParameters((string) $operation['path']) as $parameter) {
                    if (!self::declaresPathParameter($operation['body'], $parameter)) {
                        $errors[] = $where . '：' . $key . ' 的路径参数 ' . $parameter . ' 需要 @OA\\Parameter(in="path", required=true)';
                    }
                }
                $found[] = ['operationId' => $operation['operationId'], 'verb' => $operation['verb'], 'path' => $operation['path'], 'tags' => self::tags($operation['body']), 'source' => $where];
            }
            foreach (array_keys($expected) as $key) {
                if (!isset($documented[$key])) $errors[] = $where . '：路由 ' . $key . ' 缺少 @OA 操作';
            }
        }
        return ['errors' => $errors, 'operations' => $found];
    }

    /**
     * @param list<array{operationId:?string,source:string}> $operations
     * @return list<string> 重复 operationId 的错误说明
     */
    public static function duplicateOperationIds(array $operations): array
    {
        $seen = [];
        foreach ($operations as $operation) {
            if ($operation['operationId'] === null || $operation['operationId'] === '') continue;
            $seen[$operation['operationId']][] = $operation['source'];
        }
        $errors = [];
        foreach ($seen as $id => $sources) {
            if (count($sources) > 1) $errors[] = 'operationId ' . $id . ' 重复：' . implode('；', $sources);
        }
        return $errors;
    }

    /** @return list<string> PHPDoc 中声明的 @OA\Tag 名称 */
    public static function declaredTags(string $source): array
    {
        preg_match_all('/@OA\\\\Tag\(\s*name="([^"]+)"/', $source, $matches);
        return $matches[1];
    }

    /** Webman `{name:regex}`（正则内可含花括号）写作 OpenAPI `{name}`。 */
    public static function openApiPath(string $path): string
    {
        $result = '';
        $length = strlen($path);
        for ($i = 0; $i < $length; $i++) {
            if ($path[$i] !== '{') { $result .= $path[$i]; continue; }
            $name = '';
            for ($i++; $i < $length && $path[$i] !== ':' && $path[$i] !== '}'; $i++) $name .= $path[$i];
            if ($i < $length && $path[$i] === ':') {
                for ($depth = 1, $i++; $i < $length && $depth > 0; $i++) {
                    if ($path[$i] === '\\') { $i++; continue; }
                    if ($path[$i] === '{') $depth++;
                    elseif ($path[$i] === '}') $depth--;
                }
                $i--;
            }
            $result .= '{' . trim($name) . '}';
        }
        return $result;
    }

    /** @return list<string> */
    private static function pathParameters(string $path): array
    {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $path, $matches);
        return $matches[1];
    }

    private static function declaresPathParameter(string $body, string $name): bool
    {
        $offset = 0;
        while (preg_match('/@OA\\\\Parameter\s*\(/', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $open = $match[0][1] + strlen($match[0][0]) - 1;
            $parameter = self::balanced($body, $open);
            $offset = $open + strlen($parameter) + 2;
            if (preg_match('/\bname\s*=\s*"' . preg_quote($name, '/') . '"/', $parameter) === 1
                && preg_match('/\bin\s*=\s*"path"/', $parameter) === 1
                && preg_match('/\brequired\s*=\s*true\b/', $parameter) === 1) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    private static function tags(string $body): array
    {
        if (preg_match('/\btags\s*=\s*\{([^}]*)\}/', $body, $match) !== 1) return [];
        preg_match_all('/"([^"]*)"/', $match[1], $names);
        return $names[1];
    }

    /** 返回 $open 处左括号到匹配右括号之间的内容；跳过双引号字符串（swagger-php 以 "" 转义引号）。 */
    private static function balanced(string $text, int $open): string
    {
        $depth = 0;
        $length = strlen($text);
        $quoted = false;
        for ($i = $open; $i < $length; $i++) {
            $char = $text[$i];
            if ($quoted) {
                if ($char === '"') {
                    if ($i + 1 < $length && $text[$i + 1] === '"') { $i++; continue; }
                    $quoted = false;
                }
                continue;
            }
            if ($char === '"') { $quoted = true; continue; }
            if ($char === '(') $depth++;
            elseif ($char === ')' && --$depth === 0) return substr($text, $open + 1, $i - $open - 1);
        }
        throw new \RuntimeException('@OA 注释括号不匹配：' . substr($text, $open, 80));
    }

    private static function normalizePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') return '';
        if ($prefix[0] !== '/') $prefix = '/' . $prefix;
        return rtrim($prefix, '/');
    }

    /** @return list<array{0:string,1:Node\Attribute}> 已解析为完整类名的属性 */
    private static function attributes(array $groups): array
    {
        $attributes = [];
        foreach ($groups as $group) {
            foreach ($group->attrs as $attribute) $attributes[] = [ltrim($attribute->name->toString(), '\\'), $attribute];
        }
        return $attributes;
    }

    private static function argument(Node\Attribute $attribute, int $position, string $name, mixed $default): mixed
    {
        foreach ($attribute->args as $index => $argument) {
            if ($argument->name !== null ? $argument->name->toString() === $name : $index === $position) return self::value($argument->value);
        }
        return $default;
    }

    private static function value(Node\Expr $expression): mixed
    {
        if ($expression instanceof Node\Scalar\String_) return $expression->value;
        if ($expression instanceof Node\Expr\ConstFetch && strtolower($expression->name->toString()) === 'null') return null;
        if ($expression instanceof Node\Expr\Array_) {
            return array_map(static fn (Node\ArrayItem $item): mixed => self::value($item->value), array_values(array_filter($expression->items)));
        }
        throw new \RuntimeException('路由属性参数必须为静态字符串或字符串数组');
    }
}
