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
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/admin/plugin/market/setting')]
final class SettingController extends AdminApiController
{
    protected array $middleware = [
        CheckPluginPermission::class,
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];
    #[Get('/')]
    public function index(): Response { return $this->ok(data: (new MarketAdminService())->signingStatus()); }
    #[Post('/key')]
    public function generateKey(): Response
    {
        try { return $this->ok('签名密钥已生成', (new MarketAdminService())->generateKey()); } catch (\RuntimeException $exception) { return $this->fail($exception->getMessage(), code: 422); }
    }
    #[Get('/payment')]
    public function payment(): Response { return $this->ok(data: (new MarketAdminService())->paymentSettings()); }
    #[Put('/payment')]
    public function savePayment(): Response
    {
        try { return $this->ok('支付配置已保存', (new MarketAdminService())->savePaymentSettings((array) $this->request->post())); } catch (\RuntimeException $exception) { return $this->fail($exception->getMessage(), code: 422); }
    }
}
