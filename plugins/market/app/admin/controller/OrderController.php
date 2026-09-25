<?php

declare(strict_types=1);

namespace app\admin\controller\plugin\market;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckPluginPermission;
use app\admin\middleware\SystemLog;
use app\admin\service\plugin\market\MarketAdminService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\Response;

#[Group('plugin/market/order', ['complete_match' => true])]
final class OrderController extends AdminApiController
{
    protected array $middleware = [
        ['middleware' => [CheckPluginPermission::class, ['market', 'market:order:view']], 'options' => ['only' => ['index', 'detail']]],
        ['middleware' => [CheckPluginPermission::class, ['market', 'market:order:manage']], 'options' => ['except' => ['index', 'detail']]],
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];

    #[Get('')]
    public function index(): Response
    {
        return $this->ok(data: (new MarketAdminService())->orders([
            'status' => (string) $this->request->get('status', ''),
            'keyword' => mb_substr((string) $this->request->get('keyword', ''), 0, 64),
            'pluginId' => (int) $this->request->get('pluginId', 0),
        ], $this->page(), $this->pageSize()));
    }

    #[Get(':no')]
    #[Pattern('no', '\d{20,32}')]
    public function detail(string $no): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->orderDetail($no));
    }

    #[Post(':no/confirm')]
    #[Pattern('no', '\d{20,32}')]
    public function confirm(string $no): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->confirmOrder($no, (string) $this->request->post('remark', ''), (int) session('admin.id')), '已确认收款并开通授权');
    }

    #[Post(':no/close')]
    #[Pattern('no', '\d{20,32}')]
    public function close(string $no): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->closeOrder($no), '订单已关闭');
    }

    private function attempt(callable $operation, string $message = '操作成功'): Response
    {
        try {
            return $this->ok($message, $operation());
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage(), code: 422);
        }
    }
}
