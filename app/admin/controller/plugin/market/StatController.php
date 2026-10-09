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
use think\Response;

#[Group('plugin/market/stat', ['complete_match' => true])]
final class StatController extends AdminApiController
{
    protected array $middleware = [
        ['middleware' => [CheckPluginPermission::class, ['market', 'market:stat:view']]],
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];

    #[Get('')]
    public function index(): Response
    {
        return $this->ok(data: (new MarketAdminService())->stats((int) $this->request->get('days', 30)));
    }
}
