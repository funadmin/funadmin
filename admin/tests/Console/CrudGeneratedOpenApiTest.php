<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudDefinition;
use app\common\crud\PluginTemplateContext;
use app\common\crud\ProductionTemplateContext;
use PHPUnit\Framework\TestCase;
use Tests\Support\OpenApiCoverage;

/** 代码生成器产出的控制器带 OpenAPI 注释，并通过与仓库控制器相同的静态覆盖检查。 */
final class CrudGeneratedOpenApiTest extends TestCase
{
    public function test_generated_core_controller_documents_every_route(): void
    {
        $data = CrudDefinitionFixture::make('openapi-item')->toArray();
        $data['features']['status'] = true;
        $context = ProductionTemplateContext::build(CrudDefinition::fromArray($data));
        $result = $this->assertDocumented($context['controllerContent'], 'core');
        $paths = array_map(static fn (array $operation): string => $operation['verb'] . ' ' . $operation['path'], $result['operations']);
        foreach (['GET /admin/business/openapi-item', 'GET /admin/business/openapi-item/{id}', 'POST /admin/business/openapi-item',
            'PUT /admin/business/openapi-item/{id}', 'POST /admin/business/openapi-item/{id}/status', 'DELETE /admin/business/openapi-item',
            'POST /admin/business/openapi-item/restore', 'GET /admin/business/openapi-item/export'] as $expected) {
            self::assertContains($expected, $paths);
        }
        self::assertContains('admin_business_openapi_item_controller_OpenapiItemController_index_get', array_column($result['operations'], 'operationId'));
        self::assertStringContainsString('use OpenApi\\Annotations as OA;', $context['controllerContent']);
        self::assertStringContainsString('security={{"AdminSession"={}, "CsrfToken"={}}}', $context['controllerContent']);
    }

    public function test_generated_plugin_and_member_controllers_document_every_route_with_unique_ids(): void
    {
        $operations = [];
        foreach (['openapi-alpha', 'openapi-beta'] as $entity) {
            $data = CrudDefinitionFixture::make($entity)->toArray();
            $data['module'] = 'oafixture';
            $data['target'] = ['type' => 'plugin', 'plugin' => 'oafixture', 'scope' => 'both'];
            $data['permissionPrefix'] = 'oafixture:' . $entity;
            $data['apiPrefix'] = '/' . $entity;
            $data['routePath'] = '/plugins/oafixture/' . $entity;
            $data['tableIdentity'] = ['source' => 'created', 'kind' => 'physical'];
            unset($data['generationTargets']);
            $console = PluginTemplateContext::build(CrudDefinition::fromArray($data), 'oafixture', true);
            $application = PluginTemplateContext::build(CrudDefinition::fromArray($data), 'oafixture', false);
            $data['memberApi'] = ['enabled' => true, 'ownerField' => 'member_id'];
            $data['fields'][] = ['name' => 'member_id', 'dbType' => 'bigint', 'nullable' => false, 'component' => 'input'];
            $member = PluginTemplateContext::build(CrudDefinition::fromArray($data), 'oafixture', false);
            $consoleResult = $this->assertDocumented($console['controllerContent'], $entity . ' console');
            self::assertContains('/admin/plugins/oafixture/' . $entity . '/{id}', array_column($consoleResult['operations'], 'path'));
            $applicationResult = $this->assertDocumented($application['controllerContent'], $entity . ' application');
            self::assertStringContainsString('security={{"MemberBearer"={}}}', $application['controllerContent']);
            $memberResult = $this->assertDocumented($member['memberControllerContent'], $entity . ' member');
            self::assertContains('/oafixture/' . $entity, array_column($memberResult['operations'], 'path'));
            // 插件应用控制器与会员控制器渲染到同一个文件路径（二选一），只把实际产出的一份计入全局唯一性。
            $operations = [...$operations, ...$consoleResult['operations'], ...$memberResult['operations']];
            self::assertSame([], OpenApiCoverage::duplicateOperationIds([...$consoleResult['operations'], ...$applicationResult['operations']]));
        }
        self::assertSame([], OpenApiCoverage::duplicateOperationIds($operations), '不同实体生成的 operationId 不得重复');
        $repository = [];
        foreach (OpenApiCoverage::controllerFiles(dirname(__DIR__, 2)) as $file) {
            $repository = [...$repository, ...OpenApiCoverage::check((string) file_get_contents($file), $file)['operations']];
        }
        self::assertSame([], OpenApiCoverage::duplicateOperationIds([...$repository, ...$operations]), '生成的 operationId 不得与仓库已有接口重复');
    }

    /** @return array{errors:list<string>,operations:list<array<string,mixed>>} */
    private function assertDocumented(string $source, string $label): array
    {
        $routed = OpenApiCoverage::routedMethods($source);
        self::assertNotSame([], $routed, $label . ' 应包含路由方法');
        $result = OpenApiCoverage::check($source, $label);
        self::assertSame([], $result['errors'], implode("\n", $result['errors']));
        self::assertSame([], OpenApiCoverage::duplicateOperationIds($result['operations']));
        self::assertCount(array_sum(array_map(static fn (array $method): int => count($method['routes']), $routed)), $result['operations']);
        return $result;
    }
}
