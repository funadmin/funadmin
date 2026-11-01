<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\IdentityLoginAttempt;
use app\common\model\identity\IdentityUser;
use app\common\service\identity\IdentityCredentialService as CredentialService;
use think\captcha\facade\Captcha;
use think\facade\Cache;
use think\facade\Session;

final class IdentityCredentialService
{
    public function login(int $tenantId, string $username, string $password, string $captcha, string $ip): array
    {
        // captcha_check 由同一组件提供；Facade 调用便于显式复用当前会话。
        if (!Captcha::check($captcha)) {
            $this->audit($tenantId, null, $username, $ip, false, 'invalid_captcha');
            throw new \DomainException('invalid_captcha');
        }
        $key = 'identity:login:' . hash('sha256', $tenantId . '|' . strtolower(trim($username)) . '|' . $ip);
        $attempts = (int) Cache::get($key, 0);
        if ($attempts >= 5) {
            throw new \DomainException('temporarily_locked');
        }
        $user = IdentityUser::forTenant($tenantId)->where('username', trim($username))->where('status', 1)->find();
        if (!$user || !(new CredentialService())->verifyAndUpgrade($tenantId, (int) $user->id, $password)) {
            Cache::set($key, $attempts + 1, 900);
            $this->audit($tenantId, $user ? (int) $user->id : null, $username, $ip, false, 'invalid_credentials');
            throw new \DomainException('invalid_credentials');
        }
        Cache::delete($key);
        $this->audit($tenantId, (int) $user->id, $username, $ip, true, null);
        Session::regenerate(true);
        $opSession = (new OidcSessionService())->createOrReuse($tenantId, (int) $user->id);
        Session::set('identity.session', [
            'tenant_id' => $tenantId,
            'user_id' => (int) $user->id,
            'op_session_id' => (int) $opSession['session_id'],
            'sid' => (string) $opSession['sid'],
            'session_token' => (string) $opSession['session_token'],
            'auth_time' => (int) $opSession['auth_time'],
            'password_version' => (int) $user->password_version,
            'session_version' => (int) $user->session_version,
        ]);
        return ['user_id' => (int) $user->id, 'session_id' => (string) $opSession['sid']];
    }

    private function audit(int $tenantId, ?int $userId, string $username, string $ip, bool $succeeded, ?string $reason): void
    {
        IdentityLoginAttempt::create(['tenant_id' => $tenantId, 'user_id' => $userId, 'identifier_hash' => hash('sha256', strtolower(trim($username))), 'ip_hash' => hash('sha256', $ip), 'succeeded' => $succeeded ? 1 : 0, 'failure_reason' => $reason]);
    }
}
