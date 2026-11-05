<?php

declare(strict_types=1);

namespace app\admin\plugins\market\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\plugin\middleware\CheckPluginPermission;
use app\admin\middleware\SystemLog;
use app\admin\plugins\market\service\MarketAdminService;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/admin/plugin/market/order')]
final class OrderController extends AdminApiController
{
    protected array $middleware = [
        CheckPluginPermission::class,
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];
    #[Get('/')]
    public function index(): Response { return $this->ok(data: (new MarketAdminService())->orders(['status' => (string) $this->request->get('status', ''), 'keyword' => mb_substr((string) $this->request->get('keyword', ''), 0, 64), 'pluginId' => (int) $this->request->get('pluginId', 0)], $this->page(), $this->pageSize())); }
    #[Get('/{no:\\d{20,32}}')]
    public function detail(string $no): Response { return $this->attempt(fn () => (new MarketAdminService())->orderDetail($no)); }
    #[Post('/{no:\\d{20,32}}/confirm')]
    public function confirm(string $no): Response { return $this->attempt(fn () => (new MarketAdminService())->confirmOrder($no, (string) $this->request->post('remark', ''), (int) ($this->request->context['admin']['id'] ?? 0)), '已确认收款并开通授权'); }
    #[Post('/{no:\\d{20,32}}/close')]
    public function close(string $no): Response { return $this->attempt(fn () => (new MarketAdminService())->closeOrder($no), '订单已关闭'); }
    private function attempt(callable $operation, string $message = '操作成功'): Response
    {
        try { return $this->ok($message, $operation()); } catch (\RuntimeException $exception) { return $this->fail($exception->getMessage(), code: 422); }
    }
}
