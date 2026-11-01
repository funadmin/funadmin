<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\IdentityCredential;
use app\common\model\identity\IdentityUser;
use app\identity\service\IdentityAuditService;

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
            'avatar' => $member->avatar ?? null,
            'status' => $member->status,
            'last_login_at' => $this->lastLoginAt($member->last_login ?? null),
        ]);
        $passwordHash = (string) ($member->password ?? '');
        if ($passwordHash !== '') {
            (new IdentityCredentialService())->syncHash(self::TENANT_ID, (int) $user->id, $passwordHash, (int) $member->status);
        }
        return $user;
    }

    private function lastLoginAt(mixed $value): ?string
    {
        $timestamp = (int) $value;
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    /** @return list<string> */
    public function shadowRead(object $member, bool $audit = true): array
    {
        $user = (new LegacyIdentityLinkService())->ensureMember(self::TENANT_ID, $member);
        $expected = [
            'username' => (string) $member->username,
            'display_name' => (string) (($member->nickname ?? '') ?: $member->username),
            'email' => $member->email ?? null,
            'mobile' => $member->mobile ?? null,
            'avatar' => $member->avatar ?? null,
            'status' => (int) $member->status,
        ];
        $differences = [];
        foreach ($expected as $field => $value) {
            if ((string) ($user->{$field} ?? null) !== (string) $value) $differences[] = $field;
        }
        $credential = IdentityCredential::forTenant(self::TENANT_ID)->where('user_id', (int) $user->id)->where('type', 'password')->find();
        if (!$credential || !hash_equals((string) $credential->secret_hash, (string) ($member->password ?? ''))) $differences[] = 'password';
        $differences = array_values(array_unique($differences));
        if ($audit && $differences !== []) {
            (new IdentityAuditService())->record(self::TENANT_ID, 'identity.shadow_read_mismatch', false, (int) $user->id, null, ['source' => 'member', 'realm' => 'member', 'fields' => $differences]);
        }
        return $differences;
    }

    public function compare(object $member): bool
    {
        return $this->shadowRead($member) === [];
    }
}
