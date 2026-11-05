<?php

declare(strict_types=1);

namespace app\admin\plugins\market\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\plugin\middleware\CheckPluginPermission;
use app\admin\middleware\SystemLog;
use app\admin\plugins\market\service\MarketAdminService;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/admin/plugin/market/category')]
final class CategoryController extends AdminApiController
{
    protected array $middleware = [
        CheckPluginPermission::class,
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];

    #[Get('/')]
    public function index(): Response { return $this->ok(data: (new MarketAdminService())->categories()); }
    #[Post('/')]
    public function create(): Response { return $this->attempt(fn () => (new MarketAdminService())->saveCategory(null, (array) $this->request->post()), '分类已创建'); }
    #[Put('/{id:\\d+}')]
    public function update(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->saveCategory($id, (array) $this->request->post()), '分类已保存'); }
    #[Delete('/{id:\\d+}')]
    public function delete(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->deleteCategory($id), '分类已删除'); }
    private function attempt(callable $operation, string $message): Response
    {
        try { $operation(); return $this->ok($message); } catch (\RuntimeException $exception) { return $this->fail($exception->getMessage(), code: 422); }
    }
}
