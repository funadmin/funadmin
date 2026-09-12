<?php

declare(strict_types=1);

namespace app\identity\service;

use think\Request;

/**
 * 仅供 APP_ENV=testing 的真实 HTTP 协议测试注入身份，不在其他环境启用。
 */
final class FixtureIdentitySessionResolver implements IdentitySessionResolverInterface
{
    public function resolve(Request $request): ?array
    {
        if ((string) env('APP_ENV', '') !== 'testing') {
            return null;
        }
        $tenantId = (int) $request->header('x-test-identity-tenant', '0');
        $userId = (int) $request->header('x-test-identity-user', '0');
        $sessionId = (string) $request->header('x-test-identity-session', '');
        if ($tenantId <= 0 || $userId <= 0 || preg_match('/^[A-Za-z0-9_-]{16,128}$/', $sessionId) !== 1) {
            return null;
        }
        return ['tenant_id' => $tenantId, 'user_id' => $userId, 'auth_time' => time(), 'session_id' => $sessionId];
    }
}
