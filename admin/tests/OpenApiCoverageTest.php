<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\Support\OpenApiCoverage;

/**
 * 防回退：每个带路由属性的核心与插件源码控制器方法都有动词、完整 path 与路由一致的 @OA 操作，operationId 全局唯一。
 * swagger-php 未作为依赖安装，这里做静态检查（规则见 docs/openapi/README.md）。
 * 插件只检查 plugins/<code>/ 源码；app/<code>/、app/admin/plugins/ 安装副本与 app/admin/business/ 生成模块不入库，不在范围内。
 */
final class OpenApiCoverageTest extends TestCase
{
    private const DOCUMENTATION = __DIR__ . '/../../docs/openapi/ApiDocumentation.php';

    /** @return array{errors:list<string>,operations:list<array<string,mixed>>,files:list<string>} */
    private static function scan(): array
    {
        $root = dirname(__DIR__);
        $errors = [];
        $operations = [];
        $files = [];
        foreach (OpenApiCoverage::controllerFiles($root) as $file) {
            $relative = substr($file, strlen($root) + 1);
            $files[] = $relative;
            $result = OpenApiCoverage::check((string) file_get_contents($file), $relative);
            $errors = [...$errors, ...$result['errors']];
            $operations = [...$operations, ...$result['operations']];
        }
        return ['errors' => $errors, 'operations' => $operations, 'files' => $files];
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

    public function test_open_api_path_strips_nested_route_constraints(): void
    {
        self::assertSame('/admin/{entry}/auth', OpenApiCoverage::openApiPath('/admin/{entry:[a-z][a-z0-9-]{3,31}}/auth'));
        self::assertSame('/cms/{path}', OpenApiCoverage::openApiPath('/cms/{path:(?!(?:member|forms)(?:/|$)).+}'));
        self::assertSame('/docs/{id}/revisions/{revisionNo}', OpenApiCoverage::openApiPath('/docs/{id:\d+}/revisions/{revisionNo:\d+}'));
    }
}
