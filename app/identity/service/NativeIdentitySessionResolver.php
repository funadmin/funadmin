<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\IdentityUser;
use think\facade\Session;
use think\Request;

/** 从独立 Identity 会话命名空间解析可信身份。 */
final class NativeIdentitySessionResolver implements IdentitySessionResolverInterface
{
    public function resolve(Request $request): ?array
    {
        $identity = Session::get('identity.session', []);
        $tenantId = is_array($identity) ? (int) ($identity['tenant_id'] ?? 0) : 0;
        $userId = is_array($identity) ? (int) ($identity['user_id'] ?? 0) : 0;
        $sessionId = is_array($identity) ? (string) ($identity['sid'] ?? $identity['session_id'] ?? '') : '';
        $sessionToken = is_array($identity) ? (string) ($identity['session_token'] ?? '') : '';
        if ($tenantId <= 0 || $userId <= 0 || preg_match('/^[A-Za-z0-9_-]{16,128}$/', $sessionId) !== 1) {
            $this->destroy();
            return null;
        }
        $user = IdentityUser::forTenant($tenantId)
            ->where('id', $userId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->find();
        $oidc = $sessionToken !== '' ? (new OidcSessionService())->resolve($tenantId, $sessionToken) : null;
        if (!$user
            || ($sessionToken !== '' && ($oidc === null || (int) $oidc['user_id'] !== $userId))
            || !array_key_exists('password_version', $identity)
            || !array_key_exists('session_version', $identity)
            || (int) $identity['password_version'] !== (int) $user->password_version
            || (int) $identity['session_version'] !== (int) $user->session_version) {
            $this->destroy();
            return null;
        }
        return [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'auth_time' => (int) ($identity['auth_time'] ?? 0),
            'op_session_id' => (int) ($identity['op_session_id'] ?? $oidc['session_id'] ?? 0),
            'session_id' => $sessionId,
        ];
    }

    private function destroy(): void
    {
        Session::delete('identity.session');
    }
}
