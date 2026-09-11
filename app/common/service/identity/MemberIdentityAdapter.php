<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\IdentityUser;

class MemberIdentityAdapter
{
    public const TENANT_ID = 1;

    public function sync(object $member): IdentityUser
    {
        $user = (new LegacyIdentityLinkService())->ensureMember(self::TENANT_ID, $member);
        $user = (new IdentityUserService())->update(self::TENANT_ID, (int) $user->id, [
            'username' => $member->username,
            'display_name' => ($member->nickname ?? '') ?: $member->username,
            'email' => $member->email ?? null,
            'mobile' => $member->mobile ?? null,
            'status' => $member->status,
        ]);
        $passwordHash = (string) ($member->password ?? '');
        if ($passwordHash !== '') {
            (new IdentityCredentialService())->syncHash(self::TENANT_ID, (int) $user->id, $passwordHash, (int) $member->status);
        }
        return $user;
    }

    public function compare(object $member): bool
    {
        $user = (new LegacyIdentityLinkService())->ensureMember(self::TENANT_ID, $member);
        return (string) $user->username === (string) $member->username
            && (int) $user->status === (int) $member->status;
    }
}
