<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\IdentityAdminLink;
use app\common\model\identity\IdentityMemberLink;
use app\common\model\identity\IdentityUser;

class LegacyIdentityLinkService
{
    public function ensureAdmin(int $tenantId, object $admin): IdentityUser
    {
        return $this->ensure($tenantId, 'admin', (int) $admin->id, $admin, IdentityAdminLink::class, 'admin_id');
    }

    public function ensureMember(int $tenantId, object $member): IdentityUser
    {
        return $this->ensure($tenantId, 'member', (int) $member->id, $member, IdentityMemberLink::class, 'member_id');
    }

    private function ensure(int $tenantId, string $realm, int $sourceId, object $legacy, string $linkClass, string $sourceField): IdentityUser
    {
        $link = $linkClass::forTenant($tenantId)->where($sourceField, $sourceId)->find();
        if ($link) {
            return IdentityUser::forTenant($tenantId)->where('id', (int) $link->user_id)->findOrFail();
        }

        $user = (new IdentityUserService())->create($tenantId, $realm, $sourceId, [
            'username' => (string) $legacy->username,
            'display_name' => (string) (($legacy->real_name ?? $legacy->nickname ?? '') ?: $legacy->username),
            'email' => $legacy->email ?? null,
            'mobile' => $legacy->mobile ?? null,
            'status' => (int) $legacy->status,
        ]);
        $linkClass::create([
            'tenant_id' => $tenantId,
            'user_id' => (int) $user->id,
            $sourceField => $sourceId,
        ]);
        return $user;
    }
}
