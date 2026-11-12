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
     * @param string|null $adminRoot 用于定位 trait、父类与属性类源码；默认为本仓库 admin/
     * @return list<array{class:string,method:string,routes:list<array{verbs:list<string>,path:string,openApiPath:string}>,doc:string}>
     */
    public static function routedMethods(string $source, array $extraRoutes = [], ?string $adminRoot = null): array
    {
        $adminRoot ??= dirname(__DIR__, 2);
        $nodes = self::parse($source);
        $local = [];
        foreach ((new NodeFinder())->findInstanceOf($nodes, Node\Stmt\ClassLike::class) as $node) {
            if ($node->namespacedName !== null) $local[strtolower($node->namespacedName->toString())] = $node;
        }
        $result = [];
        foreach ((new NodeFinder())->findInstanceOf($nodes, Node\Stmt\Class_::class) as $class) {
            if ($class->namespacedName === null) continue;
            $className = $class->namespacedName->toString();
            $extra = $extraRoutes[$className] ?? [];
            if ($class->isAbstract()) {
                if ($extra !== []) throw new \RuntimeException('config/route.php 指向抽象类：' . $className);
                continue;
            }
            self::assertTraitsHaveNoRoutes($class, $className, $adminRoot, $local, []);
            $prefix = '';
            $grouped = false;
            foreach (self::attributes($class->attrGroups) as [$name, $attribute]) {
                $short = self::routeAttribute($name, $className, $adminRoot, $local);
                if ($short === null || $short === 'DisableDefaultRoute') continue;
                if ($short !== 'RouteGroup') throw new \RuntimeException('类上不支持的路由属性 ' . $name . '：' . $className);
                // 与 Webman 一致：只取第一个 RouteGroup。
                if (!$grouped) $prefix = self::normalizePrefix((string) self::argument($attribute, 0, 'prefix', ''));
                $grouped = true;
            }
            $declared = [];
            foreach ($class->getMethods() as $method) {
                $methodName = $method->name->toString();
                $declared[strtolower($methodName)] = true;
                $routes = self::extraRoutes($extra, $methodName);
                if ($routes !== [] && !$method->isPublic()) throw new \RuntimeException('config/route.php 指向非 public 方法：' . $className . '::' . $methodName);
                $public = $method->isPublic() && !in_array(strtolower($methodName), ['__construct', '__destruct'], true);
                foreach (self::attributes($method->attrGroups) as [$name, $attribute]) {
                    $short = self::routeAttribute($name, $className . '::' . $methodName, $adminRoot, $local);
                    if ($short === null || $short === 'DisableDefaultRoute' || !$public) continue;
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
                $result[] = ['class' => $className, 'method' => $methodName, 'routes' => $routes, 'doc' => (string) $method->getDocComment()?->getText()];
            }
            // config/route.php 指向未在本类声明的方法：沿父类查找继承的 public 方法，其注释写在声明它的类上。
            foreach ($extra as $methodName => $unused) {
                if (isset($declared[strtolower((string) $methodName)])) continue;
                $inherited = self::inheritedMethod($class, (string) $methodName, $adminRoot, $local);
                if ($inherited === null) throw new \RuntimeException('config/route.php 指向不存在的方法：' . $className . '::' . $methodName);
                if (!$inherited->isPublic()) throw new \RuntimeException('config/route.php 指向非 public 方法：' . $className . '::' . $methodName);
                $result[] = ['class' => $className, 'method' => (string) $methodName, 'routes' => self::extraRoutes($extra, (string) $methodName), 'doc' => (string) $inherited->getDocComment()?->getText()];
            }
        }
        return $result;
    }

    /** @return list<array{verbs:list<string>,path:string,openApiPath:string}> */
    private static function extraRoutes(array $extra, string $method): array
    {
        $routes = [];
        foreach ($extra as $name => $list) {
            if (strtolower((string) $name) !== strtolower($method)) continue;
            foreach ($list as $route) $routes[] = ['verbs' => $route['verbs'], 'path' => $route['path'], 'openApiPath' => self::openApiPath($route['path'])];
        }
        return $routes;
    }

    /** 沿 extends（含父类使用的 trait）查找方法声明；找不到父类源码时报错。 */
    private static function inheritedMethod(Node\Stmt\Class_ $class, string $method, string $adminRoot, array $local): ?Node\Stmt\ClassMethod
    {
        $seen = [];
        while ($class->extends !== null) {
            $parent = ltrim($class->extends->toString(), '\\');
            if (isset($seen[strtolower($parent)])) return null;
            $seen[strtolower($parent)] = true;
            $node = self::classLike($parent, $adminRoot, $local);
            if (!$node instanceof Node\Stmt\Class_) throw new \RuntimeException('无法定位父类源码 ' . $parent);
            foreach ([$node, ...self::usedTraits($node, $adminRoot, $local)] as $candidate) {
                foreach ($candidate->getMethods() as $declared) {
                    if (strtolower($declared->name->toString()) === strtolower($method)) return $declared;
                }
            }
            $class = $node;
        }
        return null;
    }

    /** @return list<Node\Stmt\Trait_> */
    private static function usedTraits(Node\Stmt\ClassLike $class, string $adminRoot, array $local): array
    {
        $traits = [];
        foreach ($class->getTraitUses() as $use) {
            foreach ($use->traits as $trait) {
                $node = self::classLike(ltrim($trait->toString(), '\\'), $adminRoot, $local);
                if ($node instanceof Node\Stmt\Trait_) $traits = [...$traits, $node, ...self::usedTraits($node, $adminRoot, $local)];
            }
        }
        return $traits;
    }

    /** Webman Route 上不注册路由、可以出现在任意位置的静态方法（config/route.php 现有用法）。 */
    private const CONFIG_NON_REGISTERING = ['disabledefaultroute', 'fallback', 'dispatch'];
    /** 可以挂在路由调用链上的方法：只修改已注册的路由，不新增路由。 */
    private const CONFIG_CHAIN_METHODS = ['name', 'middleware'];
    private const CONFIG_VERBS = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];

    /**
     * 静态解析 config/route.php：Route::get/post/put/delete/patch/head/options/add 以及 Route::group（嵌套前缀），
     * 允许调用链 ->name()/->middleware()。闭包回调（如 /health）没有控制器方法可挂注释，计入 skipped。
     * 其他任何 Route:: 调用、出现在顶层语句或 group 闭包语句之外（if、foreach、赋值、函数参数等）的路由注册，
     * 以及 group 闭包中的非路由语句，一律报错，避免静默漏检。
     *
     * @return array{routes:array<string,array<string,list<array{verbs:list<string>,path:string}>>>,skipped:list<string>}
     */
    public static function configRoutes(string $source): array
    {
        $nodes = self::parse($source);
        $routes = [];
        $skipped = [];
        $accounted = [];
        self::collectConfigRoutes($nodes, '', $routes, $skipped, $accounted, true);
        foreach ((new NodeFinder())->find($nodes, static fn (Node $node): bool => self::isRouteStaticCall($node)) as $call) {
            $name = strtolower($call->name->toString());
            if (isset($accounted[spl_object_id($call)]) || in_array($name, self::CONFIG_NON_REGISTERING, true)) continue;
            throw new \RuntimeException('无法静态解析的 Route::' . $call->name->toString() . ' 调用（第 ' . $call->getStartLine() . ' 行）：路由注册必须是顶层语句或 Route::group 闭包中的语句');
        }
        foreach ((new NodeFinder())->find($nodes, static fn (Node $node): bool => $node instanceof Node\Expr\MethodCall
            && $node->var instanceof Node\Expr\Variable && $node->var->name === 'this') as $call) {
            throw new \RuntimeException('config/route.php 不支持 $this->' . ($call->name instanceof Node\Identifier ? $call->name->toString() : '?') . '() 注册路由（第 ' . $call->getStartLine() . ' 行）');
        }
        return ['routes' => $routes, 'skipped' => $skipped];
    }

    private static function isRouteStaticCall(Node $node): bool
    {
        return $node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name
            && strtolower(ltrim($node->class->toString(), '\\')) === 'webman\\route' && $node->name instanceof Node\Identifier;
    }

    /** @param array<Node> $statements */
    private static function collectConfigRoutes(array $statements, string $prefix, array &$routes, array &$skipped, array &$accounted, bool $topLevel): void
    {
        foreach ($statements as $statement) {
            $call = $statement instanceof Node\Stmt\Expression ? $statement->expr : null;
            while ($call instanceof Node\Expr\MethodCall && $call->name instanceof Node\Identifier
                && in_array(strtolower($call->name->toString()), self::CONFIG_CHAIN_METHODS, true)) {
                $call = $call->var;
            }
            if ($call === null || !self::isRouteStaticCall($call)) {
                // 顶层允许 use、注释、其他非路由语句；其中混入的 Route:: 调用由 configRoutes() 的全量扫描报错。group 闭包内只能是路由语句。
                if (!$topLevel && !$statement instanceof Node\Stmt\Nop) throw new \RuntimeException('Route::group 闭包中只能包含路由语句（第 ' . $statement->getStartLine() . ' 行）');
                continue;
            }
            $name = strtolower($call->name->toString());
            if (in_array($name, self::CONFIG_NON_REGISTERING, true)) { $accounted[spl_object_id($call)] = true; continue; }
            $accounted[spl_object_id($call)] = true;
            if ($name === 'group') {
                $first = self::callArgument($call, 0, 'path');
                if ($first instanceof Node\Expr\Closure || $first instanceof Node\Expr\ArrowFunction) {
                    [$groupPrefix, $closure] = [$prefix, $first];
                } else {
                    [$groupPrefix, $closure] = [$prefix . self::configString($first, $call), self::callArgument($call, 1, 'callback')];
                }
                if ($closure instanceof Node\Expr\ArrowFunction) $body = [new Node\Stmt\Expression($closure->expr, $closure->expr->getAttributes())];
                elseif ($closure instanceof Node\Expr\Closure) $body = $closure->stmts;
                else throw new \RuntimeException('Route::group 回调必须为闭包（第 ' . $call->getStartLine() . ' 行）');
                self::collectConfigRoutes($body, $groupPrefix, $routes, $skipped, $accounted, false);
                continue;
            }
            if ($name === 'add') {
                $verbs = self::value(self::callArgument($call, 0, 'methods') ?? throw new \RuntimeException('Route::add 缺少请求方法'));
                $verbs = array_map('strtoupper', is_array($verbs) ? $verbs : [$verbs]);
                $pathArg = self::callArgument($call, 1, 'path');
                $callback = self::callArgument($call, 2, 'callback');
            } elseif (in_array($name, self::CONFIG_VERBS, true)) {
                $verbs = [strtoupper($name)];
                $pathArg = self::callArgument($call, 0, 'path');
                $callback = self::callArgument($call, 1, 'callback');
            } else {
                throw new \RuntimeException('OpenAPI 覆盖检查不支持 Route::' . $call->name->toString() . '（第 ' . $call->getStartLine() . ' 行），请改为显式的 Route::get/post 等');
            }
            $path = $prefix . self::configString($pathArg, $call);
            if ($callback instanceof Node\Expr\Closure || $callback instanceof Node\Expr\ArrowFunction) {
                $skipped[] = implode('|', $verbs) . ' ' . $path;
                continue;
            }
            [$class, $method] = self::configCallback($callback, $call);
            $routes[$class][$method][] = ['verbs' => array_values($verbs), 'path' => $path];
        }
    }

    private static function callArgument(Node\Expr\StaticCall $call, int $position, string $name): ?Node\Expr
    {
        $positional = 0;
        foreach ($call->getArgs() as $argument) {
            if ($argument->unpack) throw new \RuntimeException('Route 调用不支持参数展开（第 ' . $call->getStartLine() . ' 行）');
            if ($argument->name !== null) {
                if ($argument->name->toString() === $name) return $argument->value;
                continue;
            }
            if ($positional++ === $position) return $argument->value;
        }
        return null;
    }

    private static function configString(?Node\Expr $expression, Node $call): string
    {
        if (!$expression instanceof Node\Scalar\String_) throw new \RuntimeException('路由路径必须为静态字符串（第 ' . $call->getStartLine() . ' 行）');
        return $expression->value;
    }

    /** @return array{0:string,1:string} */
    private static function configCallback(?Node\Expr $callback, Node $call): array
    {
        if ($callback instanceof Node\Expr\Array_ && count($callback->items) === 2
            && $callback->items[0]?->key === null && $callback->items[1]?->key === null
            && $callback->items[0]->value instanceof Node\Expr\ClassConstFetch
            && $callback->items[0]->value->class instanceof Node\Name
            && $callback->items[0]->value->name instanceof Node\Identifier
            && strtolower($callback->items[0]->value->name->toString()) === 'class'
            && $callback->items[1]->value instanceof Node\Scalar\String_) {
            return [ltrim($callback->items[0]->value->class->toString(), '\\'), $callback->items[1]->value->value];
        }
        throw new \RuntimeException('路由回调必须为 [控制器::class, \'方法\'] 或闭包（第 ' . $call->getStartLine() . ' 行）');
    }

    /** @return array<Node> */
    private static function parse(string $source): array
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse($source) ?? [];
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        return $traverser->traverse($nodes);
    }

    /**
     * 路由属性的规范短名；非路由属性返回 null。类名不区分大小写。
     * 继承 Webman 路由属性的自定义属性同样会被 Webman 注册（IS_INSTANCEOF），检查器不展开其参数，直接报错；
     * 与路由属性同名但不在 Webman 命名空间、且不继承它们的属性也视为无法识别。
     */
    private static function routeAttribute(string $name, string $where, string $adminRoot, array $local): ?string
    {
        $known = [...array_keys(self::VERB_ATTRIBUTES), 'Route', 'RouteGroup', 'Any', 'DisableDefaultRoute'];
        $lower = strtolower($name);
        if (str_starts_with($lower, strtolower(self::ROUTE_NAMESPACE))) {
            $short = substr($name, strlen(self::ROUTE_NAMESPACE));
            foreach ($known as $candidate) if (strtolower($candidate) === strtolower($short)) return $candidate;
            throw new \RuntimeException('无法识别的路由属性 ' . $name . '：' . $where);
        }
        $short = substr($name, (int) strrpos('\\' . $name, '\\'));
        foreach ($known as $candidate) {
            if (strtolower($candidate) === strtolower($short)) throw new \RuntimeException('无法识别的路由属性 ' . $name . '（应为 ' . self::ROUTE_NAMESPACE . $candidate . '）：' . $where);
        }
        if (self::extendsRouteAttribute($name, $adminRoot, $local, [])) {
            throw new \RuntimeException('继承 Webman 路由属性的自定义属性 ' . $name . ' 不受 OpenAPI 覆盖检查支持，请改用 ' . self::ROUTE_NAMESPACE . '* ：' . $where);
        }
        return null;
    }

    /** @var array<string,bool> */
    private static array $routeAttributeCache = [];

    private static function extendsRouteAttribute(string $name, string $adminRoot, array $local, array $seen): bool
    {
        $key = strtolower($name);
        if (isset(self::$routeAttributeCache[$key])) return self::$routeAttributeCache[$key];
        if (isset($seen[$key])) return false;
        $seen[$key] = true;
        if (str_starts_with($key, strtolower(self::ROUTE_NAMESPACE))) return true;
        $node = self::classLike($name, $adminRoot, $local);
        if ($node === null) {
            // 无法定位源码时只接受 PHP 内置类（如 Override、SensitiveParameter）；用户类必须可解析。
            if ((class_exists($name, false) || interface_exists($name, false)) && (new \ReflectionClass($name))->isInternal()) return self::$routeAttributeCache[$key] = false;
            throw new \RuntimeException('无法定位属性类源码 ' . $name);
        }
        $parent = $node instanceof Node\Stmt\Class_ && $node->extends !== null ? ltrim($node->extends->toString(), '\\') : null;
        return self::$routeAttributeCache[$key] = $parent !== null && self::extendsRouteAttribute($parent, $adminRoot, $local, $seen);
    }

    /**
     * Webman 把 trait 方法当作控制器自身声明的方法注册路由；检查器不展开可见性别名，因此要求 trait 不带路由属性。
     *
     * @param array<string,true> $seen
     */
    private static function assertTraitsHaveNoRoutes(Node\Stmt\ClassLike $class, string $owner, string $adminRoot, array $local, array $seen): void
    {
        foreach ($class->getTraitUses() as $use) {
            foreach ($use->traits as $trait) {
                $name = ltrim($trait->toString(), '\\');
                if (isset($seen[strtolower($name)])) continue;
                $seen[strtolower($name)] = true;
                $node = self::classLike($name, $adminRoot, $local);
                if (!$node instanceof Node\Stmt\Trait_) throw new \RuntimeException('无法定位 trait 源码 ' . $name . '：' . $owner);
                foreach ($node->getMethods() as $method) {
                    foreach (self::attributes($method->attrGroups) as [$attributeName]) {
                        if (self::routeAttribute($attributeName, $name . '::' . $method->name, $adminRoot, $local) !== null) {
                            throw new \RuntimeException('trait 方法带路由属性，请在控制器中声明路由：' . $name . '::' . $method->name . '（' . $owner . '）');
                        }
                    }
                }
                self::assertTraitsHaveNoRoutes($node, $owner, $adminRoot, $local, $seen);
            }
        }
    }

    /** @var array<string,array<Node>> */
    private static array $fileCache = [];

    /** 按名称定位类、trait 或接口声明：先看同一源码，再看插件源码 / app 源码 / Composer。 */
    private static function classLike(string $name, string $adminRoot, array $local): ?Node\Stmt\ClassLike
    {
        $key = strtolower($name);
        if (isset($local[$key])) return $local[$key];
        $file = self::classFile($name, $adminRoot);
        if ($file === null) return null;
        self::$fileCache[$file] ??= self::parse((string) file_get_contents($file));
        foreach ((new NodeFinder())->findInstanceOf(self::$fileCache[$file], Node\Stmt\ClassLike::class) as $node) {
            if ($node->namespacedName !== null && strtolower($node->namespacedName->toString()) === $key) return $node;
        }
        return null;
    }

    /** 插件命名空间指向插件源码（不读安装副本），其余 app\ 按 PSR-4 指向 app/，第三方交给 Composer。 */
    private static function classFile(string $class, string $adminRoot): ?string
    {
        $adminRoot = rtrim($adminRoot, '/');
        $candidates = [];
        if (preg_match('/^app\\\\admin\\\\plugins\\\\([a-z][a-z0-9]*)\\\\(.+)$/i', $class, $match) === 1) {
            $candidates[] = "{$adminRoot}/plugins/{$match[1]}/app/admin/plugins/{$match[1]}/" . str_replace('\\', '/', $match[2]) . '.php';
        } elseif (preg_match('/^app\\\\([a-z][a-z0-9]*)\\\\(.+)$/i', $class, $match) === 1 && is_dir("{$adminRoot}/plugins/{$match[1]}")) {
            $candidates[] = "{$adminRoot}/plugins/{$match[1]}/app/{$match[1]}/" . str_replace('\\', '/', $match[2]) . '.php';
        }
        if (stripos($class, 'app\\') === 0) $candidates[] = $adminRoot . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        foreach ($candidates as $candidate) if (is_file($candidate)) return $candidate;
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
            $file = $loader->findFile($class);
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
            try {
                $operations = self::operations($routed['doc']);
            } catch (\RuntimeException $error) {
                $errors[] = $where . '：注解字符串语法错误：' . $error->getMessage();
                continue;
            }
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
                if (($syntax = self::stringSyntaxError($operation['body'])) !== null) $errors[] = $where . '：' . $key . ' 注解字符串语法错误：' . $syntax;
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

    /** @return list<string> tags={...} 中的标签名（按 doctrine 规则还原 "" 转义） */
    private static function tags(string $body): array
    {
        if (preg_match('/\btags\s*=\s*\{/', $body, $match, PREG_OFFSET_CAPTURE) !== 1) return [];
        $names = [];
        $length = strlen($body);
        for ($i = $match[0][1] + strlen($match[0][0]); $i < $length && $body[$i] !== '}'; $i++) {
            if ($body[$i] !== '"') continue;
            $name = '';
            for ($i++; $i < $length; $i++) {
                if ($body[$i] === '"') {
                    if ($i + 1 < $length && $body[$i + 1] === '"') { $name .= '"'; $i++; continue; }
                    break;
                }
                $name .= $body[$i];
            }
            $names[] = $name;
        }
        return $names;
    }

    /**
     * doctrine 注解字符串检查：字符串以 " 开始、以 " 结束，内部 " 写作 ""；结束引号后只能是 , ) } = : 或空白。
     * 未转义的引号会让后续文字落到字符串之外，swagger-php 会解析失败。
     */
    private static function stringSyntaxError(string $body): ?string
    {
        $length = strlen($body);
        for ($i = 0; $i < $length; $i++) {
            if ($body[$i] !== '"') continue;
            $start = $i;
            for ($i++; $i < $length; $i++) {
                if ($body[$i] !== '"') continue;
                if ($i + 1 < $length && $body[$i + 1] === '"') { $i++; continue; }
                break;
            }
            if ($i >= $length) return '字符串未结束：' . substr($body, $start, 60);
            $next = $i + 1;
            while ($next < $length && ctype_space($body[$next])) $next++;
            if ($next < $length && !in_array($body[$next], [',', ')', '}', '=', ':'], true)) {
                return '字符串结束后出现 ' . substr($body, $next, 20) . '（双引号需写作 ""）：' . substr($body, $start, 60);
            }
        }
        return null;
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
