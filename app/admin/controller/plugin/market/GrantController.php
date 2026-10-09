<?php

declare(strict_types=1);

namespace app\admin\controller\plugin\market;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckPluginPermission;
use app\admin\middleware\SystemLog;
use app\admin\service\plugin\market\MarketAdminService;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;

#[Group('plugin/market/grant', ['complete_match' => true])]
final class GrantController extends AdminApiController
{
    protected array $middleware = [
        ['middleware' => [CheckPluginPermission::class, ['market', 'market:grant:manage']]],
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];

    #[Get('')]
    public function index(): Response
    {
        return $this->ok(data: (new MarketAdminService())->grants((int) $this->request->get('pluginId', 0), $this->page(), $this->pageSize()));
    }

    #[Post('')]
    public function create(): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->saveGrant(null, (array) $this->request->post()), '授权已保存');
    }

    #[Put(':id')]
    #[Pattern('id', '\d+')]
    public function update(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->saveGrant($id, (array) $this->request->put()), '授权已保存');
    }

    #[Delete(':id')]
    #[Pattern('id', '\d+')]
    public function delete(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->deleteGrant($id), '授权已删除');
    }

    private function attempt(callable $operation, string $message): Response
    {
        try {
            $operation();
            return $this->ok($message);
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage(), code: 422);
        }
    }
}
