<?php

declare(strict_types=1);

namespace app\admin\plugin\business\market\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\plugin\middleware\CheckPluginPermission;
use app\admin\middleware\SystemLog;
use app\admin\plugin\business\market\service\MarketAdminService;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/admin/plugin/market/grant')]
final class GrantController extends AdminApiController
{
    protected array $middleware = [
        CheckPluginPermission::class,
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];
    #[Get('/')]
    public function index(): Response { return $this->ok(data: (new MarketAdminService())->grants((int) $this->request->get('pluginId', 0), $this->page(), $this->pageSize())); }
    #[Post('/')]
    public function create(): Response { return $this->attempt(fn () => (new MarketAdminService())->saveGrant(null, (array) $this->request->post()), '授权已保存'); }
    #[Put('/{id:\\d+}')]
    public function update(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->saveGrant($id, (array) $this->request->post()), '授权已保存'); }
    #[Delete('/{id:\\d+}')]
    public function delete(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->deleteGrant($id), '授权已删除'); }
    private function attempt(callable $operation, string $message): Response
    {
        try { $operation(); return $this->ok($message); } catch (\RuntimeException $exception) { return $this->fail($exception->getMessage(), code: 422); }
    }
}
