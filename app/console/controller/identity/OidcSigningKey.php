<?php

declare(strict_types=1);

namespace app\console\controller\identity;

use app\common\service\identity\AdminIdentityAdapter;
use app\common\service\identity\SigningKeyService;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use InvalidArgumentException;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\Response;

#[Group('identity/signing-keys')]
final class OidcSigningKey extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];
    private readonly SigningKeyService $keys;

    protected function initialize(): void
    {
        parent::initialize();
        $this->keys = new SigningKeyService();
    }

    #[Get('')]
    public function index(): Response { return $this->ok(data: $this->keys->list($this->tenantId())); }

    #[Post('rotate')]
    public function rotate(): Response
    {
        return $this->ok(data: $this->keys->rotate($this->tenantId(), (int) $this->request->post('publishWindowSeconds', 86400)), msg: '签名 key rotation 完成');
    }

    private function tenantId(): int
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        if ($tenantId <= 0) throw new InvalidArgumentException('租户 ID 无效');
        return $tenantId;
    }
}
