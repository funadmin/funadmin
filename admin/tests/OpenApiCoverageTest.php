<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\Support\OpenApiCoverage;

/**
 * 防回退：每个带路由属性的核心与插件源码控制器方法都有动词、完整 path 与路由一致的 @OA 操作，operationId 全局唯一。
 * swagger-php 未作为依赖安装，这里做静态检查（规则见 docs/openapi/README.md）。
 * 插件只检查 plugins/<code>/ 源码；app/<code>/、app/admin/plugins/ 安装副本与 app/admin/business/ 生成模块不入库，不在范围内。
 * config/route.php 中指向控制器方法的 Route 调用（含 group 前缀）同样核对；闭包路由（/health、/）没有控制器方法，不在检查范围。
 */
final class OpenApiCoverageTest extends TestCase
{
    private const DOCUMENTATION = __DIR__ . '/../../docs/openapi/ApiDocumentation.php';

    /** @return array{errors:list<string>,operations:list<array<string,mixed>>,files:list<string>,config:array<string,mixed>} */
    private static function scan(): array
    {
        $root = dirname(__DIR__);
        $config = OpenApiCoverage::configRoutes((string) file_get_contents($root . '/config/route.php'));
        $errors = [];
        $operations = [];
        $files = [];
        $classes = [];
        foreach (OpenApiCoverage::controllerFiles($root) as $file) {
            $relative = substr($file, strlen($root) + 1);
            $files[] = $relative;
            $source = (string) file_get_contents($file);
            foreach (OpenApiCoverage::routedMethods($source, $config['routes'], $root) as $routed) $classes[$routed['class']] = true;
            $result = OpenApiCoverage::check($source, $relative, $config['routes'], $root);
            $errors = [...$errors, ...$result['errors']];
            $operations = [...$operations, ...$result['operations']];
        }
        foreach (array_keys($config['routes']) as $class) {
            if (!isset($classes[$class])) $errors[] = 'config/route.php 指向的控制器不在扫描范围内：' . $class;
        }
        return ['errors' => $errors, 'operations' => $operations, 'files' => $files, 'config' => $config];
    }

    public function test_scan_roots_cover_core_and_bundled_plugin_sources_only(): void
    {
        $files = self::scan()['files'];
        foreach (['app/admin/system/controller/SystemDashboard.php', 'app/install/controller/Index.php',
            'plugins/cms/app/admin/plugins/cms/controller/Content.php', 'plugins/cms/app/cms/controller/FormEndpoint.php',
            'plugins/document/app/document/controller/Reader.php', 'plugins/oss/app/admin/plugins/oss/controller/Storage.php',
            'plugins/qa/app/admin/plugins/qa/controller/Questions.php', 'plugins/qa/app/qa/controller/Pages.php'] as $expected) {
            self::assertContains($expected, $files);
        }
        foreach ($files as $file) {
            self::assertDoesNotMatchRegularExpression('~^app/admin/(?:plugins|business)/|^app/(?:cms|document|qa|oss)/~', $file, '安装副本与生成模块不入库，不应扫描');
        }
    }

    public function test_every_routed_controller_method_has_a_matching_operation(): void
    {
        $scan = self::scan();
        self::assertSame([], $scan['errors'], implode("\n", $scan['errors']));
        self::assertGreaterThan(400, count($scan['operations']));
    }

    public function test_operation_ids_are_globally_unique(): void
    {
        $errors = OpenApiCoverage::duplicateOperationIds(self::scan()['operations']);
        self::assertSame([], $errors, implode("\n", $errors));
    }

    public function test_operation_tags_are_registered(): void
    {
        $declared = OpenApiCoverage::declaredTags((string) file_get_contents(self::DOCUMENTATION));
        foreach (self::scan()['operations'] as $operation) {
            self::assertNotSame([], $operation['tags'], $operation['source'] . ' 缺少 tags');
            foreach ($operation['tags'] as $tag) self::assertContains($tag, $declared, $operation['source'] . ' 的标签需在 ApiDocumentation.php 登记');
        }
    }

    public function test_checker_reports_missing_mismatched_and_duplicate_operations(): void
    {
        $source = <<<'PHP'
<?php
namespace app\demo\controller;
use support\annotation\route\{Get, Post, RouteGroup as Group};
use support\annotation\route\Route;
#[Group('demo/')]
final class Sample
{
    /**
     * @OA\Get(path="/demo/{id}", operationId="dup", @OA\Parameter(name="id", in="path", required=true), @OA\Response(response=200, description="ok"))
     */
    #[Get('/{id:\d{1,3}}')]
    public function read(int $id) {}

    /**
     * @OA\Post(path="/demo/wrong", operationId="dup", @OA\Response(response=200, description="ok"))
     */
    #[Post('/save')]
    public function save() {}

    #[Route('', ['GET', 'HEAD'])]
    public function index() {}

    /** @OA\Get(path="/demo/{slug}", operationId="page", @OA\Response(response=200, description="ok")) */
    #[Get('/{slug}')]
    public function page(string $slug) {}

    #[Get]
    public function defaultRouteOnly() {}

    private function helper() {}
}
PHP;
        $routed = OpenApiCoverage::routedMethods($source);
        self::assertSame(['read', 'save', 'index', 'page'], array_column($routed, 'method'));
        self::assertSame('/demo/{id}', $routed[0]['routes'][0]['openApiPath']);
        self::assertSame('/demo', $routed[2]['routes'][0]['path']);
        $result = OpenApiCoverage::check($source, 'fixture');
        $errors = implode("\n", $result['errors']);
        self::assertStringContainsString('POST /demo/wrong 与路由不一致', $errors);
        self::assertStringContainsString('路由 POST /demo/save 缺少 @OA 操作', $errors);
        self::assertStringContainsString('路由 GET /demo 缺少 @OA 操作', $errors);
        self::assertStringNotContainsString('HEAD', $errors, 'HEAD 由同一路由的 GET 操作覆盖');
        self::assertStringContainsString('路径参数 slug 需要', $errors);
        self::assertStringNotContainsString('路径参数 id', $errors);
        self::assertCount(1, OpenApiCoverage::duplicateOperationIds($result['operations']));
    }

    public function test_config_routes_are_parsed_and_only_closures_are_skipped(): void
    {
        $config = self::scan()['config'];
        self::assertSame([['verbs' => ['GET'], 'path' => '/plugins/hooks/frontend'], ['verbs' => ['GET'], 'path' => '/admin/plugins/hooks/frontend']],
            $config['routes']['app\\common\\plugin\\controller\\FrontendHooks']['index']);
        self::assertSame([['verbs' => ['POST'], 'path' => '/api/v2/token/refresh']], $config['routes']['app\\api\\controller\\v2\\Token']['refresh']);
        self::assertSame([['verbs' => ['GET'], 'path' => '/admin/auth/csrf']], $config['routes']['app\\admin\\authentication\\controller\\AdminAuth']['csrf']);
        self::assertSame(['GET /health', 'GET /'], $config['skipped']);
        $fixture = OpenApiCoverage::configRoutes(<<<'PHP'
<?php
use Webman\Route;
Route::group('/v9', static function (): void {
    Route::group('/inner', static function (): void {
        Route::add(['GET', 'POST'], '/x', [\app\demo\controller\Sample::class, 'save']);
    });
});
PHP);
        self::assertSame(['app\\demo\\controller\\Sample' => ['save' => [['verbs' => ['GET', 'POST'], 'path' => '/v9/inner/x']]]], $fixture['routes']);
    }

    public function test_checker_rejects_trait_routes_and_unrecognised_route_attributes(): void
    {
        $trait = <<<'PHP'
<?php
namespace app\demo\controller;
final class UsesTrait { use \app\common\traits\Crud; use \Tests\Support\OpenApiCoverageRoutedTraitFixture; }
PHP;
        try {
            OpenApiCoverage::routedMethods($trait);
            self::fail('trait 路由应被拒绝');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('trait 方法带路由属性', $error->getMessage());
        }
        $unknown = <<<'PHP'
<?php
namespace app\demo\controller;
use support\annotation\route\Get;
final class Unknown { #[\app\demo\Post('/x')] public function save() {} }
PHP;
        try {
            OpenApiCoverage::routedMethods($unknown);
            self::fail('无法识别的路由属性应被拒绝');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('无法识别的路由属性', $error->getMessage());
        }
        $unsupported = "<?php\nnamespace app\\demo;\nfinal class Odd { #[\\support\\annotation\\route\\Middleware] public function x() {} }";
        $this->expectException(\RuntimeException::class);
        OpenApiCoverage::routedMethods($unsupported);
    }

    /** @return array<string,array{0:string,1:?array}> 路由写法 => [config 片段, 期望解析结果（null 表示必须报错）] */
    public static function configForms(): array
    {
        $a = '[\app\demo\controller\A::class, \'m\']';
        $routes = static fn (string $verb, string $path): array => ['app\\demo\\controller\\A' => ['m' => [['verbs' => [$verb], 'path' => $path]]]];
        return [
            'plain' => ["Route::get('/x', {$a});", $routes('GET', '/x')],
            'chained name' => ["Route::get('/x', {$a})->name('x');", $routes('GET', '/x')],
            'chained middleware' => ["Route::post('/x', {$a})->middleware([\\app\\M::class])->name('x');", $routes('POST', '/x')],
            'group chained middleware' => ["Route::group('/g', static function (): void { Route::get('/x', {$a}); })->middleware([\\app\\M::class]);", $routes('GET', '/g/x')],
            'group without prefix' => ["Route::group(static function (): void { Route::put('/x', {$a}); });", $routes('PUT', '/x')],
            'nested group arrow' => ["Route::group('/a', static fn () => Route::group('/b', static function (): void { Route::delete('/x', {$a}); }));", $routes('DELETE', '/a/b/x')],
            'named arguments' => ["Route::get(callback: {$a}, path: '/x');", $routes('GET', '/x')],
            'add' => ["Route::add(['PATCH'], '/x', {$a});", $routes('PATCH', '/x')],
            'fallback with dispatch' => ["Route::fallback(static function (\$r) { return Route::dispatch('GET', '/'); })->middleware([]);", []],
            'in if' => ["if (true) { Route::get('/x', {$a}); }", null],
            'in foreach' => ["foreach (['a'] as \$p) Route::get('/x', {$a});", null],
            'assigned' => ["\$route = Route::get('/x', {$a});", null],
            'argument of function' => ["register(Route::get('/x', {$a}));", null],
            'unknown chain' => ["Route::get('/x', {$a})->setParams([]);", null],
            'resource' => ["Route::resource('/r', \\app\\demo\\controller\\A::class);", null],
            'any' => ["Route::any('/x', {$a});", null],
            'this in group' => ["Route::group('/g', function () { \$this->get('/x', ['A', 'm']); });", null],
            'non route statement in group' => ["Route::group('/g', static function (): void { \$x = 1; });", null],
            'string callback' => ["Route::get('/x', 'app\\\\demo\\\\A@m');", null],
            'class constant callback' => ["Route::get('/x', [\\app\\demo\\controller\\A::FOO, 'm']);", null],
            'first class callable' => ["Route::get('/x', \\app\\demo\\controller\\A::m(...));", null],
            'concatenated path' => ["Route::get('/x' . 'y', {$a});", null],
            // 顶层白名单：只允许 use、分组 use、declare、空语句与可识别的 Route:: 语句。
            'use function and group use' => ["use function strlen;\nuse app\\demo\\{B, C};\nRoute::get('/x', {$a});", $routes('GET', '/x')],
            'declare and trailing comment' => ["declare(ticks=1);\nRoute::get('/x', {$a});\n// trailing", $routes('GET', '/x')],
            'require' => ["require __DIR__ . '/more-routes.php';", null],
            'include once' => ["include_once 'routes.php';", null],
            'include inside group' => ["Route::group('/g', static function (): void { require 'g.php'; });", null],
            'include in chain argument' => ["Route::get('/x', {$a})->middleware(require 'middleware.php');", null],
            'dynamic method name' => ["Route::{'get'}('/x', {$a});", null],
            'variable method name' => ["Route::\$verb('/x', {$a});", null],
            'expression class' => ["(Route::class)::get('/x', {$a});", null],
            'call_user_func' => ["call_user_func([Route::class, 'get'], '/x', {$a});", null],
            'call_user_func_array' => ["\\call_user_func_array('Webman\\Route::get', ['/x', {$a}]);", null],
            'call_user_func in chain argument' => ["Route::get('/x', {$a})->middleware(call_user_func('m'));", null],
            'plugin route loader' => ["\\app\\common\\plugin\\sdk\\PluginRouteLoader::get('/x', {$a});", null],
            'new route' => ["new Route();", null],
            'new route in chain argument' => ["Route::get('/x', {$a})->middleware([new Route()]);", null],
            'other static call' => ["\\app\\demo\\Routes::register();", null],
            'function call' => ["registerRoutes();", null],
            'echo' => ["echo 'x';", null],
            'return' => ["return [];", null],
            'function declaration' => ["function routes(): void {}", null],
            'declare block' => ["declare(ticks=1) { Route::get('/x', {$a}); }", null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('configForms')]
    public function test_config_route_forms_are_parsed_or_rejected(string $snippet, ?array $expected): void
    {
        $source = "<?php\nuse Webman\\Route;\n{$snippet}\n";
        if ($expected === null) $this->expectException(\RuntimeException::class);
        self::assertSame($expected, OpenApiCoverage::configRoutes($source)['routes']);
    }

    public function test_config_route_targets_must_exist_and_may_be_inherited(): void
    {
        $source = <<<'PHP'
<?php
namespace app\demo\controller;
abstract class Base
{
    /** @OA\Get(path="/inherited", operationId="inherited", @OA\Response(response=200, description="ok")) */
    public function shared() {}
}
final class A extends Base
{
    /** @OA\Get(path="/own", operationId="own", @OA\Response(response=200, description="ok")) */
    public function own() {}
}
PHP;
        $routes = ['app\\demo\\controller\\A' => ['shared' => [['verbs' => ['GET'], 'path' => '/inherited']], 'own' => [['verbs' => ['GET'], 'path' => '/own']]]];
        $result = OpenApiCoverage::check($source, 'fixture', $routes);
        self::assertSame([], $result['errors']);
        self::assertSame(['own', 'inherited'], array_column($result['operations'], 'operationId'));
        $this->expectExceptionMessage('config/route.php 指向不存在的方法');
        OpenApiCoverage::check($source, 'fixture', ['app\\demo\\controller\\A' => ['noSuchMethod' => [['verbs' => ['GET'], 'path' => '/x']]]]);
    }

    public function test_method_disable_default_route_is_allowed_and_custom_route_attributes_are_rejected(): void
    {
        $allowed = "<?php\nnamespace app\\demo;\nfinal class A { #[\\support\\annotation\\route\\DisableDefaultRoute] public function x() {} }";
        self::assertSame([], OpenApiCoverage::routedMethods($allowed));
        $custom = "<?php\nnamespace app\\demo;\nfinal class A { #[\\Tests\\Support\\OpenApiCoverageCustomGetFixture('/x')] public function x() {} }";
        try {
            OpenApiCoverage::routedMethods($custom);
            self::fail('继承路由属性的自定义属性应被拒绝');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('继承 Webman 路由属性的自定义属性', $error->getMessage());
        }
        $this->expectExceptionMessage('无法定位 trait 源码');
        OpenApiCoverage::routedMethods("<?php\nnamespace app\\demo;\nfinal class A { use \\vendorless\\Missing; }");
    }

    public function test_annotation_strings_must_escape_quotes(): void
    {
        $bad = <<<'PHP'
<?php
namespace app\demo;
use support\annotation\route\Get;
final class A
{
    /** @OA\Get(path="/x", operationId="x", tags={"t"x"}, summary="a "q" b", @OA\Response(response=200, description="ok")) */
    #[Get('/x')]
    public function x() {}
}
PHP;
        self::assertStringContainsString('注解字符串语法错误', implode("\n", OpenApiCoverage::check($bad, 'fixture')['errors']));
        $good = str_replace(['{"t"x"}', '"a "q" b"'], ['{"t""x", "u"}', '"a ""q"" b"'], $bad);
        $result = OpenApiCoverage::check($good, 'fixture');
        self::assertSame([], $result['errors']);
        self::assertSame(['t"x', 'u'], $result['operations'][0]['tags']);
    }

    public function test_open_api_path_strips_nested_route_constraints(): void
    {
        self::assertSame('/admin/{entry}/auth', OpenApiCoverage::openApiPath('/admin/{entry:[a-z][a-z0-9-]{3,31}}/auth'));
        self::assertSame('/cms/{path}', OpenApiCoverage::openApiPath('/cms/{path:(?!(?:member|forms)(?:/|$)).+}'));
        self::assertSame('/docs/{id}/revisions/{revisionNo}', OpenApiCoverage::openApiPath('/docs/{id:\d+}/revisions/{revisionNo:\d+}'));
    }
}
