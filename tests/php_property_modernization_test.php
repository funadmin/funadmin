<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\BaseController;
use app\ExceptionHandle;
use app\common\crud\AtomicWriter;
use app\common\crud\ConfirmationToken;
use app\common\crud\SchemaInspector;
use app\common\service\DbCacheService;
use app\common\service\McpService;
use app\common\service\PredisService;
use app\common\service\UploadService;
use app\common\model\UpgradeManifest;
use app\common\validate\MemberValidate;
use app\console\controller\development\DevCrud;
use app\console\controller\system\SystemPlugin;
use app\console\service\AdminAuthorizationService;
use app\console\service\DevCrudService;
use app\console\service\PluginCenterService;
use app\console\service\PluginMarketplaceService;
use app\console\service\PluginPackagePipeline;
use app\console\service\PluginPackageService;
use app\console\service\PluginService;
use fun\helper\CtrHelper;
use fun\Plugins;
use PhpMcp\Server\Server;
use Psr\Log\LoggerInterface;
use think\App;
use think\Request;
use think\view\driver\Think as ThinkViewDriver;

$failures = [];

function modernizationCheck(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function modernizationProperty(string $class, string $property): ?ReflectionProperty
{
    $reflection = new ReflectionClass($class);
    if (!$reflection->hasProperty($property)) {
        modernizationCheck(false, "{$class} 缺少属性 \${$property}");
        return null;
    }
    return $reflection->getProperty($property);
}

function modernizationTypedProperty(
    string $class,
    string $property,
    string $type,
    ?string $visibility = null,
    ?bool $static = null,
    ?bool $readonly = null
): void {
    $reflection = modernizationProperty($class, $property);
    if ($reflection === null) {
        return;
    }
    modernizationCheck((string) $reflection->getType() === $type, "{$class}::\${$property} 类型必须为 {$type}");
    if ($visibility !== null) {
        modernizationCheck(
            ($visibility === 'private' && $reflection->isPrivate()) || ($visibility === 'protected' && $reflection->isProtected()) || ($visibility === 'public' && $reflection->isPublic()),
            "{$class}::\${$property} 可见性必须为 {$visibility}"
        );
    }
    if ($static !== null) {
        modernizationCheck($reflection->isStatic() === $static, "{$class}::\${$property} static 声明不符合契约");
    }
    if ($readonly !== null) {
        modernizationCheck($reflection->isReadOnly() === $readonly, "{$class}::\${$property} readonly 声明不符合契约");
    }
}

function modernizationUntypedProperty(string $class, string $property): void
{
    $reflection = modernizationProperty($class, $property);
    if ($reflection === null) {
        return;
    }
    modernizationCheck(!$reflection->hasType(), "{$class}::\${$property} 必须保持无类型以兼容框架");
}

function modernizationPropertyValue(object|string $target, string $property): mixed
{
    $reflection = new ReflectionProperty(is_object($target) ? $target::class : $target, $property);
    $reflection->setAccessible(true);
    return $reflection->getValue(is_object($target) ? $target : null);
}

function modernizationMethod(string $class, string $method, array $parameters, string $returnType): void
{
    $reflection = new ReflectionMethod($class, $method);
    $actual = [];
    foreach ($reflection->getParameters() as $parameter) {
        $actual[] = [$parameter->getName(), (string) $parameter->getType(), $parameter->isOptional()];
    }
    modernizationCheck($actual === $parameters, "{$class}::{$method} 参数签名不符合契约");
    modernizationCheck((string) $reflection->getReturnType() === $returnType, "{$class}::{$method} 返回类型必须为 {$returnType}");
}

function modernizationRemoveTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

/** @return list<string> */
function modernizationPhpFiles(array $directories): array
{
    $files = [];
    foreach ($directories as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }
    sort($files);
    return $files;
}

function modernizationTokenText(array|string $token): string
{
    return is_array($token) ? $token[1] : $token;
}

function modernizationPreviousSignificantToken(array $tokens, int $offset): array|string|null
{
    for ($index = $offset - 1; $index >= 0; $index--) {
        $token = $tokens[$index];
        if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $token;
        }
    }
    return null;
}

function modernizationQualifiedName(array $tokens, int &$offset): string
{
    $name = '';
    $count = count($tokens);
    for (; $offset < $count; $offset++) {
        $token = $tokens[$offset];
        if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            $name .= $token[1];
            continue;
        }
        if ($token === '\\') {
            $name .= '\\';
            continue;
        }
        if (is_array($token) && $token[0] === T_WHITESPACE) {
            continue;
        }
        break;
    }
    return trim($name, '\\');
}

/**
 * @return array{
 *   classes: array<string, array{file: string, line: int, parent: string, properties: array<string, true>}>,
 *   untyped: list<array{class: string, property: string, file: string, line: int}>,
 *   assignments: list<array{class: string, property: string, file: string, line: int}>,
 *   files: int,
 *   properties: int,
 *   promoted: int
 * }
 */
function modernizationScanProperties(array $files): array
{
    $classes = [];
    $untyped = [];
    $assignments = [];
    $propertyCount = 0;
    $promotedCount = 0;

    foreach ($files as $file) {
        $tokens = token_get_all((string) file_get_contents($file));
        $namespace = '';
        $class = null;
        $pendingClass = null;
        $braceDepth = 0;
        $classDepth = null;
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_NAMESPACE && $class === null) {
                $index++;
                $namespace = modernizationQualifiedName($tokens, $index);
                $index--;
                continue;
            }
            if (is_array($token) && in_array($token[0], [T_CLASS, T_TRAIT], true)) {
                $previous = modernizationPreviousSignificantToken($tokens, $index);
                if ((is_array($previous) && $previous[0] === T_NEW) || $previous === '::') {
                    continue;
                }
                $cursor = $index + 1;
                while ($cursor < $count && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) {
                    $cursor++;
                }
                if (!isset($tokens[$cursor]) || !is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_STRING) {
                    continue;
                }
                $shortName = $tokens[$cursor][1];
                $parent = '';
                for ($lookahead = $cursor + 1; $lookahead < $count && $tokens[$lookahead] !== '{'; $lookahead++) {
                    if (is_array($tokens[$lookahead]) && $tokens[$lookahead][0] === T_EXTENDS) {
                        $lookahead++;
                        $parent = modernizationQualifiedName($tokens, $lookahead);
                        break;
                    }
                }
                $pendingClass = [
                    'name' => ltrim($namespace . '\\' . $shortName, '\\'),
                    'parent' => $parent,
                    'line' => $token[2],
                ];
                continue;
            }
            if ($token === '{') {
                $braceDepth++;
                if ($pendingClass !== null) {
                    $class = $pendingClass['name'];
                    $classDepth = $braceDepth;
                    $classes[$class] = [
                        'file' => $file,
                        'line' => $pendingClass['line'],
                        'parent' => $pendingClass['parent'],
                        'properties' => [],
                    ];
                    $pendingClass = null;
                }
                continue;
            }
            if ($token === '}') {
                if ($class !== null && $braceDepth === $classDepth) {
                    $class = null;
                    $classDepth = null;
                }
                $braceDepth--;
                continue;
            }
            if ($class === null || !is_array($token)) {
                continue;
            }

            if ($token[0] === T_VARIABLE && $token[1] === '$this') {
                $cursor = $index + 1;
                while ($cursor < $count && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) {
                    $cursor++;
                }
                if (!isset($tokens[$cursor]) || !is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_OBJECT_OPERATOR) {
                    continue;
                }
                $cursor++;
                while ($cursor < $count && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) {
                    $cursor++;
                }
                $propertyToken = $tokens[$cursor] ?? null;
                if (!is_array($propertyToken) || $propertyToken[0] !== T_STRING) {
                    continue;
                }
                $cursor++;
                while ($cursor < $count && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) {
                    $cursor++;
                }
                if (($tokens[$cursor] ?? null) === '=') {
                    $assignments[] = [
                        'class' => $class,
                        'property' => $propertyToken[1],
                        'file' => $file,
                        'line' => $propertyToken[2],
                    ];
                }
                continue;
            }

            if (!in_array($token[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR], true)) {
                continue;
            }
            $isPromoted = false;
            for ($lookbehind = $index - 1; $lookbehind >= 0; $lookbehind--) {
                $previousToken = $tokens[$lookbehind];
                if (in_array($previousToken, ['{', '}', ';'], true)) {
                    break;
                }
                if (is_array($previousToken) && $previousToken[0] === T_FUNCTION) {
                    $isPromoted = true;
                    break;
                }
            }
            $hasType = false;
            $sawFunction = false;
            for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                $candidate = $tokens[$cursor];
                if ($candidate === ';' || $candidate === '{' || ($isPromoted && in_array($candidate, [',', ')'], true))) {
                    break;
                }
                if (is_array($candidate) && $candidate[0] === T_FUNCTION) {
                    $sawFunction = true;
                    break;
                }
                if (is_array($candidate) && in_array($candidate[0], [T_STATIC, T_READONLY, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if (is_array($candidate) && $candidate[0] === T_VARIABLE) {
                    if (!$sawFunction) {
                        $property = substr($candidate[1], 1);
                        $classes[$class]['properties'][$property] = true;
                        $propertyCount++;
                        $promotedCount += (int) $isPromoted;
                        if (!$hasType) {
                            $untyped[] = ['class' => $class, 'property' => $property, 'file' => $file, 'line' => $candidate[2]];
                        }
                    }
                    if ($isPromoted) {
                        break;
                    }
                    continue;
                }
                if ($candidate === '=' || $candidate === '&' || $candidate === '...') {
                    continue;
                }
                $hasType = true;
            }
        }
    }

    return [
        'classes' => $classes,
        'untyped' => $untyped,
        'assignments' => $assignments,
        'files' => count($files),
        'properties' => $propertyCount,
        'promoted' => $promotedCount,
    ];
}

function modernizationClassHasProperty(string $class, string $property, array $classes): bool
{
    if (isset($classes[$class]['properties'][$property])) {
        return true;
    }
    if (class_exists($class)) {
        return (new ReflectionClass($class))->hasProperty($property);
    }
    return false;
}

// 第一批：回调统一归一为不可变 Closure，同时覆盖默认值和自定义回调行为。
foreach ([
    [AtomicWriter::class, 'afterWrite', '?Closure'],
    [AtomicWriter::class, 'beforeReplace', '?Closure'],
    [ConfirmationToken::class, 'clock', 'Closure'],
    [SchemaInspector::class, 'query', 'Closure'],
    [DevCrudService::class, 'inspectorFactory', 'Closure'],
    [DevCrudService::class, 'tableReader', 'Closure'],
    [DevCrudService::class, 'auditWriter', 'Closure'],
    [DevCrudService::class, 'auditReader', 'Closure'],
] as [$class, $property, $type]) {
    modernizationTypedProperty($class, $property, $type, 'private', false, true);
}

$root = sys_get_temp_dir() . '/funadmin-property-modernization-' . bin2hex(random_bytes(5));
mkdir($root, 0755, true);
try {
    $now = 1_800_000_000;
    $tokens = new ConfirmationToken($root, 'property-modernization-secret', 60, static fn (): int => $now);
    modernizationCheck(modernizationPropertyValue($tokens, 'clock') instanceof Closure, 'ConfirmationToken 自定义 clock 必须归一为 Closure');
    $digest = hash('sha256', 'behavior');
    $issued = $tokens->issue($digest);
    modernizationCheck($tokens->verify($issued, $digest)['issuedAt'] === $now, 'ConfirmationToken 自定义 clock 行为必须保持不变');

    $defaultTokens = new ConfirmationToken($root, 'property-modernization-secret');
    modernizationCheck(modernizationPropertyValue($defaultTokens, 'clock') instanceof Closure, 'ConfirmationToken 默认 clock 必须初始化为 Closure');

    $queries = [];
    $inspector = new SchemaInspector(static function (string $sql, array $bindings) use (&$queries): array {
        $queries[] = [$sql, $bindings];
        return match (true) {
            str_contains($sql, 'TABLES') => [['TABLE_NAME' => 'fun_demo', 'TABLE_COMMENT' => 'Demo']],
            default => [],
        };
    });
    modernizationCheck(modernizationPropertyValue($inspector, 'query') instanceof Closure, 'SchemaInspector 自定义 query 必须归一为 Closure');
    modernizationCheck($inspector->inspect('fun_demo')['table'] === 'fun_demo' && count($queries) === 4, 'SchemaInspector 自定义 query 行为必须保持不变');
    modernizationCheck(modernizationPropertyValue(new SchemaInspector(), 'query') instanceof Closure, 'SchemaInspector 默认 query 必须初始化为 Closure');

    $before = [];
    $after = [];
    $writer = new AtomicWriter(
        $root,
        static function (array $file) use (&$after): void { $after[] = $file['path']; },
        $tokens,
        static function (array $file) use (&$before): void { $before[] = $file['path']; }
    );
    modernizationCheck(modernizationPropertyValue($writer, 'afterWrite') instanceof Closure, 'AtomicWriter 自定义 afterWrite 必须归一为 Closure');
    modernizationCheck(modernizationPropertyValue($writer, 'beforeReplace') instanceof Closure, 'AtomicWriter 自定义 beforeReplace 必须归一为 Closure');
    $content = "modernized\n";
    $plan = [
        'definitionHash' => hash('sha256', 'definition'),
        'files' => [[
            'path' => 'generated/property.txt',
            'status' => 'create',
            'content' => $content,
            'hash' => hash('sha256', $content),
            'previousHash' => null,
        ]],
    ];
    $plan['planDigest'] = ConfirmationToken::planDigest($plan);
    $result = $writer->write($plan, $tokens->issue($plan['planDigest']));
    modernizationCheck($result['written'] === 1 && $before === ['generated/property.txt'] && $after === ['generated/property.txt'], 'AtomicWriter 自定义回调执行顺序和写入行为必须保持不变');
    $defaultWriter = new AtomicWriter($root, null, $tokens);
    modernizationCheck(modernizationPropertyValue($defaultWriter, 'afterWrite') === null, 'AtomicWriter 默认 afterWrite 必须保持 null');
    modernizationCheck(modernizationPropertyValue($defaultWriter, 'beforeReplace') === null, 'AtomicWriter 默认 beforeReplace 必须保持 null');

    $auditRows = [];
    $devCrud = new DevCrudService(
        $root,
        ['mysql'],
        static fn (string $connection): SchemaInspector => $inspector,
        static function (array $row) use (&$auditRows): int { $auditRows[] = $row; return count($auditRows); },
        static fn (int $id): ?array => $auditRows[$id - 1] ?? null,
        static fn (string $connection): array => [['TABLE_NAME' => 'fun_demo', 'TABLE_COMMENT' => $connection]]
    );
    foreach (['inspectorFactory', 'tableReader', 'auditWriter', 'auditReader'] as $property) {
        modernizationCheck(modernizationPropertyValue($devCrud, $property) instanceof Closure, "DevCrudService 自定义 {$property} 必须归一为 Closure");
    }
    modernizationCheck($devCrud->tables('mysql') === [['name' => 'fun_demo', 'comment' => 'mysql']], 'DevCrudService 自定义 tableReader 行为必须保持不变');
    $defaultDevCrud = new DevCrudService($root, ['mysql']);
    foreach (['inspectorFactory', 'tableReader', 'auditWriter', 'auditReader'] as $property) {
        modernizationCheck(modernizationPropertyValue($defaultDevCrud, $property) instanceof Closure, "DevCrudService 默认 {$property} 必须初始化为 Closure");
    }
} catch (Throwable $exception) {
    $failures[] = '第一批回调行为测试异常：' . $exception->getMessage();
} finally {
    modernizationRemoveTree($root);
}

foreach (['cache', 'stats'] as $property) {
    modernizationTypedProperty(DbCacheService::class, $property, 'array', 'private', true, false);
}
DbCacheService::flush();
$calls = 0;
$first = DbCacheService::remember('modernization', static function () use (&$calls): string { $calls++; return 'cached'; });
$second = DbCacheService::remember('modernization', static function () use (&$calls): string { $calls++; return 'changed'; });
modernizationCheck($first === 'cached' && $second === 'cached' && $calls === 1, 'DbCacheService 静态缓存读写行为必须保持不变');
modernizationCheck(DbCacheService::getStats()['hits'] === 1 && DbCacheService::getStats()['misses'] === 1, 'DbCacheService 静态统计行为必须保持不变');
DbCacheService::flush();
modernizationTypedProperty(CtrHelper::class, 'controllerList', 'array', 'private', true, false);

// 第二批：服务属性和动态属性全部显式声明。
modernizationTypedProperty(PredisService::class, 'app', App::class, 'protected', false, false);
foreach ([
    ['app', App::class], ['request', Request::class], ['driver', 'string'], ['fileExt', 'string'],
    ['fileMaxsize', 'int'], ['saveFilePath', 'string'], ['duration', 'int'], ['width', 'int'],
    ['height', 'int'], ['rule', 'string'],
] as [$property, $type]) {
    modernizationTypedProperty(UploadService::class, $property, $type, 'protected', false, false);
}

foreach ([
    ['server', '?' . Server::class], ['logger', LoggerInterface::class], ['timeout', 'int'],
    ['connectTimeout', 'int'], ['readTimeout', 'int'], ['retryAttempts', 'int'], ['retryDelay', 'int'],
    ['debug', 'bool'], ['bufferSize', 'int'], ['heartbeatEnabled', 'bool'], ['heartbeatInterval', 'int'],
] as [$property, $type]) {
    modernizationTypedProperty(McpService::class, $property, $type, 'protected', false, false);
}
$mcpReflection = new ReflectionClass(McpService::class);
modernizationCheck(!$mcpReflection->hasProperty('memoryLimit'), 'McpService 必须删除零引用 memoryLimit 属性');
modernizationCheck(!$mcpReflection->hasProperty('name') && !$mcpReflection->hasProperty('version'), 'McpService 必须删除固定 name/version 实例属性');
modernizationCheck($mcpReflection->hasConstant('NAME') && $mcpReflection->getConstant('NAME') === 'mcp', 'McpService 必须以 NAME 常量保存服务名');
modernizationCheck($mcpReflection->hasConstant('VERSION') && $mcpReflection->getConstant('VERSION') === '1.0.0', 'McpService 必须以 VERSION 常量保存服务版本');
$mcpSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/service/McpService.php');
modernizationCheck(str_contains($mcpSource, "'name' => self::NAME") && str_contains($mcpSource, "'version' => self::VERSION"), 'McpService 服务信息必须读取 NAME/VERSION 常量');

$uploadSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/service/UploadService.php');
modernizationCheck(
    str_contains($uploadSource, "root_path() . 'public/assets/fonts/simhei.ttf'")
    && str_contains($uploadSource, 'is_file($fontPath)')
    && str_contains($uploadSource, '文字水印字体文件不存在'),
    'UploadService 必须使用 public 下的绝对字体路径，并在字体缺失时给出清晰异常'
);
modernizationCheck(is_file(dirname(__DIR__) . '/public/assets/fonts/simhei.ttf'), '公共字体资源 simhei.ttf 必须存在');

foreach ([['app', App::class], ['request', Request::class], ['batchValidate', 'bool'], ['middleware', 'array']] as [$property, $type]) {
    modernizationTypedProperty(BaseController::class, $property, $type, 'protected', false, false);
}
$baseController = new ReflectionClass(BaseController::class);
modernizationCheck(!$baseController->hasProperty('noNeedLogin'), 'BaseController 必须删除 noNeedLogin');
modernizationCheck(!$baseController->hasProperty('onlyNeedLogin'), 'BaseController 必须删除 onlyNeedLogin');
modernizationTypedProperty(fun\plugins\Service::class, 'plugins_path', 'string', 'protected', false, false);
modernizationMethod(fun\plugins\Service::class, 'getPluginsPath', [], 'string');
modernizationMethod(fun\plugins\Service::class, 'getPluginsNamePath', [['name', 'string', false]], 'string');
modernizationCheck(!(new ReflectionClass(fun\plugins\Service::class))->hasMethod('getCheckDirs'), 'Service 必须删除零调用 getCheckDirs');

// 第三批：插件基类的扩展面保持 protected，并以准确类型和生命周期签名约束子类。
$pipelineReflection = new ReflectionClass(PluginPackagePipeline::class);
modernizationCheck(!$pipelineReflection->hasProperty('captureState'), 'PluginPackagePipeline 必须删除零引用 captureState 属性');
$pipelineConstructor = $pipelineReflection->getConstructor();
$pipelineParameters = $pipelineConstructor === null
    ? []
    : array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $pipelineConstructor->getParameters());
modernizationCheck(!in_array('captureState', $pipelineParameters, true), 'PluginPackagePipeline 构造参数必须删除 captureState');

foreach ([
    ['app', App::class], ['request', Request::class], ['name', 'string'], ['layout', 'bool'],
    ['plugin_path', 'string'], ['view', ThinkViewDriver::class], ['plugin_config', 'string'],
    ['info', 'array'], ['plugin_info', 'string'],
] as [$property, $type]) {
    modernizationTypedProperty(Plugins::class, $property, $type, 'protected', false, false);
}
$pluginsReflection = new ReflectionClass(Plugins::class);
modernizationCheck(!$pluginsReflection->hasProperty('config'), 'Plugins 必须删除零引用 config 属性');
try {
    $app = new App(dirname(__DIR__));
    $app->initialize();
    $runtimeView = $app->make('view')->engine('Think');
    modernizationCheck($runtimeView instanceof ThinkViewDriver, 'View::engine(Think) 运行时类型必须为 think\\view\\driver\\Think');
    $viewProperty = $pluginsReflection->getProperty('view');
    $viewType = $viewProperty->getType();
    modernizationCheck($viewType instanceof ReflectionNamedType && is_a($runtimeView, $viewType->getName()), 'Plugins::$view 声明必须接受实际运行时视图对象');
} catch (Throwable $exception) {
    $failures[] = 'Plugins view 运行时类型验证异常：' . $exception->getMessage();
}

modernizationMethod(Plugins::class, 'getName', [], 'string');
modernizationMethod(Plugins::class, 'fetch', [['template', 'string', true], ['vars', 'array', true]], 'void');
modernizationMethod(Plugins::class, 'display', [['content', 'string', true], ['vars', 'array', true]], 'void');
modernizationMethod(Plugins::class, 'assign', [['name', 'mixed', false], ['value', 'mixed', true]], 'static');
modernizationMethod(Plugins::class, 'engine', [['engine', 'array|string', false]], 'static');
modernizationMethod(Plugins::class, 'getInfo', [], 'array');
modernizationMethod(Plugins::class, 'getConfig', [['type', 'bool', true]], 'array');
modernizationMethod(Plugins::class, 'setInfo', [['name', 'string', true], ['value', 'array', true]], 'never');
foreach (['install', 'uninstall', 'enabled', 'disabled'] as $method) {
    modernizationMethod(Plugins::class, $method, [], 'bool');
    modernizationCheck((new ReflectionMethod(Plugins::class, $method))->isAbstract(), "Plugins::{$method} 必须保持 abstract");
}

// 第四批：收紧 Redis 与旧注解属性，同时锁定框架扩展点的无类型兼容契约。
modernizationTypedProperty(PredisService::class, 'redisObj', 'mixed', 'protected', false, false);
$predisReflection = new ReflectionClass(PredisService::class);
modernizationCheck(!$predisReflection->hasProperty('instance'), 'PredisService 必须删除零引用 instance 属性');

modernizationUntypedProperty(ExceptionHandle::class, 'ignoreReport');
foreach (['rule', 'message'] as $property) {
    modernizationUntypedProperty(MemberValidate::class, $property);
}
foreach (['name', 'json', 'jsonAssoc'] as $property) {
    modernizationUntypedProperty(UpgradeManifest::class, $property);
}

// 第五批：仅构造期注入且运行期不变的控制器与认证服务依赖必须不可变。
foreach ([
    ['plugins', PluginService::class],
    ['marketplace', PluginMarketplaceService::class],
    ['center', PluginCenterService::class],
    ['pipeline', PluginPackagePipeline::class],
    ['packages', PluginPackageService::class],
] as [$property, $type]) {
    modernizationTypedProperty(SystemPlugin::class, $property, $type, 'private', false, true);
}
modernizationCheck((new ReflectionClass(SystemPlugin::class))->isFinal(), 'SystemPlugin 必须保持 final');
modernizationTypedProperty(DevCrud::class, 'crud', DevCrudService::class, 'private', false, true);
modernizationCheck((new ReflectionClass(DevCrud::class))->isFinal(), 'DevCrud 必须保持 final');
foreach ([['request', Request::class], ['app', 'string'], ['requestUrl', 'string']] as [$property, $type]) {
    modernizationTypedProperty(AdminAuthorizationService::class, $property, $type, 'private', false, true);
}

// 全仓静态门禁：普通属性（含构造器提升）必须有类型，框架兼容点只允许精确到类和属性的豁免。
$scannerFixture = tempnam(sys_get_temp_dir(), 'funadmin-property-scanner-');
modernizationCheck($scannerFixture !== false, '属性扫描器测试夹具必须可创建');
if ($scannerFixture !== false) {
    file_put_contents($scannerFixture, <<<'PHP'
<?php
namespace ModernizationFixture;
trait PropertyTrait
{
    protected $traitGap;
}
class PropertyFixture
{
    private $ordinaryGap;
    public function __construct(private $promotedGap, private string $typedPromoted)
    {
        $this->dynamicGap = true;
        $template = '$this->templateGap = true';
    }
}
PHP);
    $fixtureScan = modernizationScanProperties([$scannerFixture]);
    $fixtureUntyped = array_map(
        static fn (array $property): string => $property['class'] . '::$' . $property['property'],
        $fixtureScan['untyped']
    );
    modernizationCheck(
        $fixtureUntyped === [
            'ModernizationFixture\\PropertyTrait::$traitGap',
            'ModernizationFixture\\PropertyFixture::$ordinaryGap',
            'ModernizationFixture\\PropertyFixture::$promotedGap',
        ],
        '属性扫描器必须识别 trait、普通属性和无类型构造器提升，且不得误报有类型提升属性'
    );
    modernizationCheck(
        count($fixtureScan['assignments']) === 1
        && $fixtureScan['assignments'][0]['property'] === 'dynamicGap',
        '属性扫描器必须识别直接动态赋值，并排除模板字符串'
    );
    unlink($scannerFixture);
}

$ormPropertyExemptions = [
    \app\common\model\DictItem::class => ['name'],
    \app\common\model\DictType::class => ['name'],
    \app\common\model\FieldVerify::class => ['pk'],
    \app\common\model\Language::class => ['name'],
    \app\common\model\MemberGroupRelation::class => ['name', 'pk', 'autoWriteTimestamp'],
    \app\common\model\MemberTag::class => ['name'],
    \app\common\model\MemberTagRelation::class => ['name', 'pk', 'autoWriteTimestamp'],
    \app\common\model\PluginOperation::class => ['name', 'autoWriteTimestamp'],
    \app\common\model\PluginResource::class => ['name'],
    \app\common\model\PluginVersionHistory::class => ['name', 'autoWriteTimestamp'],
    \app\common\model\Region::class => ['name'],
    \app\common\model\SystemMigration::class => ['name', 'autoWriteTimestamp'],
    UpgradeManifest::class => ['name', 'json', 'jsonAssoc'],
    \app\common\model\UpgradeTask::class => ['name', 'json', 'jsonAssoc'],
    \app\console\model\AdminMenu::class => ['name'],
    \app\console\model\AuthGroupDepartment::class => ['name', 'autoWriteTimestamp'],
    \app\console\model\AuthGroupInherit::class => ['name', 'autoWriteTimestamp'],
    \app\console\model\CasbinRule::class => ['name', 'autoWriteTimestamp'],
    \app\console\model\CrudGeneration::class => ['name', 'json', 'jsonAssoc'],
    \app\console\model\Department::class => ['name'],
    \app\console\model\MemberGroupRelation::class => ['name', 'pk', 'autoWriteTimestamp'],
    \app\console\model\MemberTag::class => ['name'],
    \app\console\model\MemberTagRelation::class => ['name', 'pk', 'autoWriteTimestamp'],
    \app\console\model\Permission::class => ['name'],
    \app\console\model\Form::class => ['name', 'json', 'jsonAssoc'],
    \app\console\model\FormField::class => ['name', 'json', 'jsonAssoc'],
];
$propertyExemptions = [
    ExceptionHandle::class . '::$ignoreReport' => '父类 think\\exception\\Handle::isIgnoreReport() 直接读取该无类型扩展点',
    MemberValidate::class . '::$rule' => '父类 think\\Validate 以无类型 protected $rule 提供验证规则扩展点',
    MemberValidate::class . '::$message' => '父类 think\\Validate 以无类型 protected $message 提供验证消息扩展点',
];
foreach ($ormPropertyExemptions as $class => $properties) {
    foreach ($properties as $property) {
        $propertyExemptions[$class . '::$' . $property] = 'ThinkORM 传统配置属性，由 Model::getOption() 兼容读取';
    }
}
$scan = modernizationScanProperties(modernizationPhpFiles([
    dirname(__DIR__) . '/app',
    dirname(__DIR__) . '/extend',
]));
foreach ($scan['untyped'] as $property) {
    $key = $property['class'] . '::$' . $property['property'];
    modernizationCheck(
        isset($propertyExemptions[$key]),
        "未类型化属性 {$key}（{$property['file']}:{$property['line']}）不在精确豁免清单"
    );
}
foreach (array_keys($propertyExemptions) as $key) {
    [$class, $property] = explode('::$', $key, 2);
    modernizationCheck(
        (new ReflectionClass($class))->hasProperty($property),
        "属性豁免 {$key} 已失效，必须从清单删除"
    );
}
modernizationCheck(
    (new ReflectionClass(ExceptionHandle::class))->getParentClass()?->hasProperty('ignoreReport') === true,
    'ExceptionHandle::$ignoreReport 豁免必须由父类同名可读属性支撑'
);
modernizationCheck(
    (new ReflectionClass(MemberValidate::class))->getParentClass()?->hasProperty('rule') === true
    && (new ReflectionClass(MemberValidate::class))->getParentClass()?->hasProperty('message') === true,
    'MemberValidate 规则豁免必须由父类同名可读属性支撑'
);
$ormGetOption = new ReflectionMethod(think\Model::class, 'getOption');
$ormSourceLines = file($ormGetOption->getFileName()) ?: [];
$ormSource = implode('', array_slice(
    $ormSourceLines,
    $ormGetOption->getStartLine() - 1,
    $ormGetOption->getEndLine() - $ormGetOption->getStartLine() + 1
));
modernizationCheck(
    str_contains($ormSource, 'property_exists($this, $name)') && str_contains($ormSource, 'return $this->$name;'),
    'ThinkORM 属性豁免必须由 Model::getOption() 的传统属性兼容读取支撑'
);
foreach ($ormPropertyExemptions as $class => $properties) {
    $model = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    foreach ($properties as $property) {
        $reflection = new ReflectionProperty($class, $property);
        $reflection->setAccessible(true);
        modernizationCheck(
            $model->getOption($property) === $reflection->getValue($model),
            "ThinkORM 必须实际读取 {$class}::\${$property} 的传统配置值"
        );
    }
}
try {
    $memberValidate = new MemberValidate();
    modernizationCheck(!$memberValidate->check([]), 'MemberValidate 专项行为必须实际读取 rule/message 并拒绝空数据');
} catch (Throwable $exception) {
    $failures[] = 'MemberValidate 豁免行为验证异常：' . $exception->getMessage();
}
foreach ($scan['assignments'] as $assignment) {
    if (is_a($assignment['class'], think\Model::class, true)) {
        continue;
    }
    modernizationCheck(
        modernizationClassHasProperty($assignment['class'], $assignment['property'], $scan['classes']),
        "未声明实例属性 {$assignment['class']}::\${$assignment['property']}（{$assignment['file']}:{$assignment['line']}）"
    );
}

$deadProperties = [
    PluginPackagePipeline::class . '::$captureState',
    PredisService::class . '::$instance',
    Plugins::class . '::$config',
    McpService::class . '::$memoryLimit',
    McpService::class . '::$name',
    McpService::class . '::$version',
    BaseController::class . '::$noNeedLogin',
    BaseController::class . '::$onlyNeedLogin',
];
foreach ($deadProperties as $key) {
    [$class, $property] = explode('::$', $key, 2);
    modernizationCheck(!(new ReflectionClass($class))->hasProperty($property), "已知死属性 {$key} 必须不存在");
}

echo sprintf(
    "PHP property scan: files=%d properties=%d promoted=%d assignments=%d exemptions=%d\n",
    $scan['files'],
    $scan['properties'],
    $scan['promoted'],
    count($scan['assignments']),
    count($propertyExemptions)
);
foreach ($propertyExemptions as $property => $reason) {
    echo "PHP property exemption: {$property} — {$reason}\n";
}

if ($failures !== []) {
    fwrite(STDERR, "PHP 属性现代化契约尚未满足：\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PHP property modernization tests: PASS\n";
