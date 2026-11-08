<?php

declare(strict_types=1);

namespace Tests\Document;

use app\common\controller\AdminController;
use app\admin\plugins\document\controller\CategoryController;
use app\admin\plugins\document\controller\DocumentController;
use app\common\annotation\Permission;
use support\Context;
use support\Request;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use app\admin\authorization\service\PermissionCode;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;

final class DocumentHttpTest extends DocumentTestCase
{
    /** 代码即权限：每个方法有自己的路由，权限码由控制器与方法推导，方法上不再声明权限注解。 */
    public function test_every_action_declares_its_route_and_derives_its_own_permission(): void
    {
        $expected = [
            DocumentController::class => [
                'index' => [Get::class, '', 'document:document:index'],
                'detail' => [Get::class, '/{id:\d+}', 'document:document:detail'],
                'create' => [Post::class, '', 'document:document:create'],
                'updateMeta' => [Put::class, '/{id:\d+}', 'document:document:update-meta'],
                'saveDraft' => [Put::class, '/{id:\d+}/draft', 'document:document:save-draft'],
                'delete' => [Delete::class, '/{id:\d+}', 'document:document:delete'],
                'publish' => [Post::class, '/{id:\d+}/publish', 'document:document:publish'],
                'archive' => [Post::class, '/{id:\d+}/archive', 'document:document:archive'],
                'revisions' => [Get::class, '/{id:\d+}/revisions', 'document:document:revisions'],
                'revision' => [Get::class, '/{id:\d+}/revisions/{revisionNo:\d+}', 'document:document:revision'],
                'restore' => [Post::class, '/{id:\d+}/revisions/{revisionNo:\d+}/restore', 'document:document:restore'],
            ],
            CategoryController::class => [
                'tree' => [Get::class, '', 'document:category:tree'],
                'create' => [Post::class, '', 'document:category:create'],
                'update' => [Put::class, '/{id:\d+}', 'document:category:update'],
                'delete' => [Delete::class, '/{id:\d+}', 'document:category:delete'],
            ],
        ];
        self::assertSame('/admin/plugins/document/documents', $this->prefix(DocumentController::class));
        self::assertSame('/admin/plugins/document/categories', $this->prefix(CategoryController::class));
        foreach ($expected as $class => $actions) {
            self::assertTrue(is_subclass_of($class, AdminController::class), $class . ' 必须走后台会话、CSRF 和操作日志');
            foreach ($actions as $method => [$verb, $path, $permission]) {
                $reflection = new \ReflectionMethod($class, $method);
                $route = $reflection->getAttributes($verb);
                self::assertCount(1, $route, "$class::$method 缺少路由");
                self::assertSame($path, $route[0]->newInstance()->path, "$class::$method 路径");
                self::assertSame([], $reflection->getAttributes(Permission::class), "$class::$method 不应再声明权限注解");
                self::assertSame($permission, PermissionCode::derive($class, $method), "$class::$method 权限");
            }
        }
    }

    public function test_create_save_and_publish_round_trip_through_the_controller(): void
    {
        [, $created] = $this->call('create', post: ['title' => '接口文档', 'slug' => 'api-doc', 'scope' => 'framework', 'type' => 'api', 'content' => $this->doc('GET /x')]);
        self::assertSame(200, $created['code'], $created['msg']);
        $id = $created['data']['id'];

        [, $saved] = $this->call('saveDraft', [$id], put: ['content' => $this->doc('GET /y'), 'lockVersion' => 1]);
        self::assertSame(200, $saved['code'], $saved['msg']);
        self::assertSame(2, $saved['data']['lockVersion']);

        [, $published] = $this->call('publish', [$id], post: ['changeSummary' => '首版', 'lockVersion' => 2]);
        self::assertSame('published', $published['data']['status'], $published['msg']);
        self::assertSame(422, $this->call('publish', [$id], post: ['changeSummary' => '缺锁'])[1]['code'], '发布必须携带 lockVersion');
        self::assertSame(409, $this->call('archive', [$id], post: ['lockVersion' => 1])[1]['code'], '旧锁不能归档');

        [, $list] = $this->call('index', query: ['keyword' => 'GET /y']);
        self::assertSame(1, $list['data']['total']);
    }

    public function test_domain_errors_map_to_http_status_codes(): void
    {
        [, $created] = $this->call('create', post: ['title' => 'A', 'slug' => 'a', 'scope' => 'framework']);
        $id = $created['data']['id'];
        self::assertSame(422, $this->call('create', post: ['title' => 'B', 'slug' => 'b', 'scope' => 'marketing'])[1]['code']);
        self::assertSame(409, $this->call('create', post: ['title' => 'C', 'slug' => 'a', 'scope' => 'framework'])[1]['code']);
        self::assertSame(404, $this->call('detail', [999])[1]['code']);
        self::assertSame(409, $this->call('saveDraft', [$id], put: ['content' => $this->doc(), 'lockVersion' => 7])[1]['code']);
        self::assertSame(422, $this->call('saveDraft', [$id], put: ['content' => ['type' => 'script'], 'lockVersion' => 1])[1]['code']);
        self::assertSame(422, $this->call('saveDraft', [$id], put: ['content' => $this->doc(), 'lockVersion' => 'x'])[1]['code']);
        self::assertSame(422, $this->call('updateMeta', [$id], put: ['title' => '无版本'])[1]['code'], '修改文档信息必须携带 metaVersion');
        self::assertSame(409, $this->call('updateMeta', [$id], put: ['title' => '旧版本', 'metaVersion' => 9])[1]['code']);
        self::assertSame(200, $this->call('updateMeta', [$id], put: ['title' => '新标题', 'metaVersion' => 1])[1]['code']);
    }

    public function test_unexpected_failures_do_not_leak_internal_messages(): void
    {
        \app\document\service\Schema::drop(); // 模拟数据库异常
        [, $body] = $this->call('index');
        self::assertSame(500, $body['code']);
        self::assertStringNotContainsString('SQL', $body['msg']);
        \app\document\service\Schema::install();
    }

    private function prefix(string $class): string
    {
        return (new \ReflectionClass($class))->getAttributes(RouteGroup::class)[0]->newInstance()->prefix;
    }

    private function call(string $action, array $args = [], array $post = [], array $put = [], array $query = [], string $class = DocumentController::class): array
    {
        $method = $post ? 'POST' : ($put ? 'PUT' : 'GET');
        $body = $post || $put ? json_encode($post ?: $put, JSON_THROW_ON_ERROR) : '';
        $uri = '/admin/plugins/document' . ($query ? '?' . http_build_query($query) : '');
        $request = new Request("$method $uri HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        $response = (new $class())->$action(...$args);
        return [$response, json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)];
    }
}
