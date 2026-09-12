<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\IdentityCredential;
use app\common\model\identity\IdentityUser;
use app\common\model\identity\IdentityUserDepartment;
use app\console\authorization\model\AdminDepartment;
use app\identity\service\IdentityAuditService;

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
            'avatar' => $admin->avatar ?? null,
            'status' => $admin->status,
        ]);
        $passwordHash = (string) ($admin->password ?? '');
        if ($passwordHash !== '') {
            (new IdentityCredentialService())->syncHash(self::TENANT_ID, (int) $user->id, $passwordHash, (int) $admin->status);
        }
        (new IdentityDepartmentService())->sync(self::TENANT_ID, (int) $user->id, $departmentIds, (int) ($admin->dept_id ?? 0));
        return $user;
    }

    /** @return list<string> */
    public function shadowRead(object $admin, bool $audit = true): array
    {
        $user = (new LegacyIdentityLinkService())->ensureAdmin(self::TENANT_ID, $admin);
        $expected = [
            'username' => (string) $admin->username,
            'display_name' => (string) (($admin->real_name ?? '') ?: $admin->username),
            'email' => $admin->email ?? null,
            'mobile' => $admin->mobile ?? null,
            'avatar' => $admin->avatar ?? null,
            'status' => (int) $admin->status,
        ];
        $differences = [];
        foreach ($expected as $field => $value) {
            if ((string) ($user->{$field} ?? null) !== (string) $value) $differences[] = $field;
        }
        $legacyDepartments = array_values(array_unique(array_filter(array_merge(
            [(int) ($admin->dept_id ?? 0)],
            array_map('intval', AdminDepartment::where('admin_id', (int) $admin->id)->column('dept_id'))
        ))));
        $identityDepartments = array_values(array_unique(array_map('intval', IdentityUserDepartment::forTenant(self::TENANT_ID)->where('user_id', (int) $user->id)->column('department_id'))));
        sort($legacyDepartments);
        sort($identityDepartments);
        if ($legacyDepartments !== $identityDepartments) $differences[] = 'departments';
        $credential = IdentityCredential::forTenant(self::TENANT_ID)->where('user_id', (int) $user->id)->where('type', 'password')->find();
        if (!$credential || !hash_equals((string) $credential->secret_hash, (string) ($admin->password ?? ''))) $differences[] = 'password';
        $differences = array_values(array_unique($differences));
        if ($audit && $differences !== []) {
            (new IdentityAuditService())->record(self::TENANT_ID, 'identity.shadow_read_mismatch', false, (int) $user->id, null, ['source' => 'admin', 'realm' => 'admin', 'fields' => $differences]);
        }
        return $differences;
    }

    public function compare(object $admin): bool
    {
        return $this->shadowRead($admin) === [];
    }
}
