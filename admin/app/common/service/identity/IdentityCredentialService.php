<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\IdentityCredential;
use app\common\model\identity\IdentityUser;
use InvalidArgumentException;

class IdentityCredentialService
{
    public function syncHash(int $tenantId, int $userId, string $secretHash, int $status = 1): IdentityCredential
    {
        if ($secretHash === '' || password_get_info($secretHash)['algo'] === null) {
            throw new InvalidArgumentException('secret_hash 必须是 password_hash 生成的强哈希');
        }

        $credential = IdentityCredential::forTenant($tenantId)
            ->where('user_id', $userId)->where('type', 'password')->first();
        $data = ['secret_hash' => $secretHash, 'status' => $status === 1 ? 1 : 0];
        if ($credential) {
            $hashChanged = !hash_equals((string) $credential->secret_hash, $secretHash);
            $credential->forceFill($data)->save();
            if ($hashChanged) {
                $this->incrementPasswordVersion($tenantId, $userId);
            }
            return $credential;
        }

        $credential = new IdentityCredential();
        $credential->forceFill([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => 'password',
            ...$data,
        ])->save();
        return $credential;
    }

    public function hash(string $plainSecret): string
    {
        $hash = password_hash($plainSecret, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new \RuntimeException('密码哈希失败');
        }
        return $hash;
    }

    public function verifyAndUpgrade(int $tenantId, int $userId, string $plainSecret): bool
    {
        $credential = IdentityCredential::forTenant($tenantId)
            ->where('user_id', $userId)->where('type', 'password')->where('status', 1)->first();
        if (!$credential || !password_verify($plainSecret, (string) $credential->secret_hash)) {
            return false;
        }
        $data = ['last_verified_at' => date('Y-m-d H:i:s')];
        if (password_needs_rehash((string) $credential->secret_hash, PASSWORD_DEFAULT)) {
            $data['secret_hash'] = $this->hash($plainSecret);
        }
        $credential->forceFill($data)->save();
        if (isset($data['secret_hash'])) {
            $this->incrementPasswordVersion($tenantId, $userId);
        }
        return true;
    }

    private function incrementPasswordVersion(int $tenantId, int $userId): void
    {
        $user = IdentityUser::forTenant($tenantId)->where('id', $userId)->firstOrFail();
        $user->forceFill(['password_version' => (int) $user->password_version + 1])->save();
    }
}
