<?php

declare(strict_types=1);

namespace app\admin\plugin\business\market\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\plugin\middleware\CheckPluginPermission;
use app\admin\middleware\SystemLog;
use app\admin\plugin\business\market\service\MarketAdminService;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/admin/plugin/market/stat')]
final class StatController extends AdminApiController
{
    protected array $middleware = [
        ['middleware' => [CheckPluginPermission::class, ['market', 'market:stat:view']]],
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];
    #[Get('')]
    public function index(): Response { return $this->ok(data: (new MarketAdminService())->stats((int) $this->request->get('days', 30))); }
}
