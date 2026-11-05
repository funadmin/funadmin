<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use app\common\service\identity\AdminIdentityAdapter;
use app\common\service\identity\SigningKeyService;
use app\admin\controller\base\AdminApiController;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\Response;

#[RouteGroup('/admin/identity/signing-keys')]
final class OidcSigningKey extends AdminApiController
{
    private readonly SigningKeyService $keys;

    protected function initialize(): void
    {
        parent::initialize();
        $this->keys = new SigningKeyService();
    }

    #[Get('')]
    public function index(): Response { return $this->ok(data: $this->keys->list($this->tenantId())); }

    #[Post('/rotate')]
    public function rotate(): Response
    {
        $window = $this->request->post('publishWindowSeconds', 86400);
        if ((!is_int($window) && !(is_string($window) && ctype_digit($window))) || (int) $window < 300 || (int) $window > 31536000) {
            return $this->fail(msg: '旧签名 key publish 窗口必须为 300 至 31536000 秒', code: 422);
        }
        return $this->ok(data: $this->keys->rotate($this->tenantId(), (int) $window), msg: '签名 key rotation 完成');
    }

    private function tenantId(): int
    {
        return AdminIdentityAdapter::TENANT_ID;
    }
}
