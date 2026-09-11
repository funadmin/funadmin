<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\IdentityUser;

class AdminIdentityAdapter
{
    public const TENANT_ID = 1;

    public function sync(object $admin, array $departmentIds = []): IdentityUser
    {
        $user = (new LegacyIdentityLinkService())->ensureAdmin(self::TENANT_ID, $admin);
        $user = (new IdentityUserService())->update(self::TENANT_ID, (int) $user->id, [
            'username' => $admin->username,
            'display_name' => ($admin->real_name ?? '') ?: $admin->username,
            'email' => $admin->email ?? null,
            'mobile' => $admin->mobile ?? null,
            'status' => $admin->status,
        ]);
        $passwordHash = (string) ($admin->password ?? '');
        if ($passwordHash !== '') {
            (new IdentityCredentialService())->syncHash(self::TENANT_ID, (int) $user->id, $passwordHash, (int) $admin->status);
        }
        (new IdentityDepartmentService())->sync(self::TENANT_ID, (int) $user->id, $departmentIds, (int) ($admin->dept_id ?? 0));
        return $user;
    }

    public function compare(object $admin): bool
    {
        $user = (new LegacyIdentityLinkService())->ensureAdmin(self::TENANT_ID, $admin);
        return (string) $user->username === (string) $admin->username
            && (int) $user->status === (int) $admin->status;
    }
}
