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

#[Group('plugin/market/category', ['complete_match' => true])]
final class CategoryController extends AdminApiController
{
    protected array $middleware = [
        ['middleware' => [CheckPluginPermission::class, ['market', 'market:plugin:view']], 'options' => ['only' => ['index']]],
        ['middleware' => [CheckPluginPermission::class, ['market', 'market:category:manage']], 'options' => ['except' => ['index']]],
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];

    #[Get('')]
    public function index(): Response
    {
        return $this->ok(data: (new MarketAdminService())->categories());
    }

    #[Post('')]
    public function create(): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->saveCategory(null, (array) $this->request->post()), '分类已创建');
    }

    #[Put(':id')]
    #[Pattern('id', '\d+')]
    public function update(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->saveCategory($id, (array) $this->request->put()), '分类已保存');
    }

    #[Delete(':id')]
    #[Pattern('id', '\d+')]
    public function delete(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->deleteCategory($id), '分类已删除');
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
