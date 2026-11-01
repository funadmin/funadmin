<?php

declare(strict_types=1);

namespace app\identity\service;

use think\Request;

/**
 * Phase 5 登录页或测试适配器通过此可信边界提供当前身份。
 */
interface IdentitySessionResolverInterface
{
    /** @return null|array{tenant_id:int,user_id:int,auth_time:int,session_id:string} */
    public function resolve(Request $request): ?array;
}
