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
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;

#[Group('plugin/market/setting', ['complete_match' => true])]
final class SettingController extends AdminApiController
{
    protected array $middleware = [
        ['middleware' => [CheckPluginPermission::class, ['market', 'market:setting:manage']]],
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];

    #[Get('')]
    public function index(): Response
    {
        return $this->ok(data: (new MarketAdminService())->signingStatus());
    }

    #[Post('key')]
    public function generateKey(): Response
    {
        try {
            return $this->ok('签名密钥已生成', (new MarketAdminService())->generateKey());
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage(), code: 422);
        }
    }

    #[Get('payment')]
    public function payment(): Response
    {
        return $this->ok(data: (new MarketAdminService())->paymentSettings());
    }

    #[Put('payment')]
    public function savePayment(): Response
    {
        try {
            return $this->ok('支付配置已保存', (new MarketAdminService())->savePaymentSettings((array) $this->request->put()));
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage(), code: 422);
        }
    }
}
