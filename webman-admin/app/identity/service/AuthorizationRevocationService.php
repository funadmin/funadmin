<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\IdentityConsent;
use app\common\model\identity\OAuthAuthorization;
use app\common\model\identity\OAuthAuthorizationCode;
use app\common\model\identity\OAuthToken;
use think\facade\Db;

/** 在租户边界内撤销用户授权及其全部可复用凭据。 */
final class AuthorizationRevocationService
{
    public function revokeUser(int $tenantId, int $userId): void
    {
        Db::transaction(function () use ($tenantId, $userId): void {
            $authorizationIds = array_map('intval', OAuthAuthorization::forTenant($tenantId)
                ->where('user_id', $userId)
                ->lock(true)
                ->column('id'));
            $now = date('Y-m-d H:i:s');
            IdentityConsent::forTenant($tenantId)->where('user_id', $userId)->whereNull('revoked_at')->update(['revoked_at' => $now]);
            if ($authorizationIds === []) return;
            OAuthAuthorization::forTenant($tenantId)->whereIn('id', $authorizationIds)->whereIn('status', ['pending', 'approved', 'completed'])->update(['status' => 'revoked', 'decided_at' => $now]);
            OAuthAuthorizationCode::forTenant($tenantId)->whereIn('authorization_id', $authorizationIds)->whereNull('revoked_at')->update(['revoked_at' => $now]);
            $families = array_values(array_filter(OAuthToken::forTenant($tenantId)->whereIn('authorization_id', $authorizationIds)->whereNotNull('family_id')->column('family_id')));
            OAuthToken::forTenant($tenantId)->whereIn('authorization_id', $authorizationIds)->whereNull('revoked_at')->update(['revoked_at' => $now]);
            if ($families !== []) OAuthToken::forTenant($tenantId)->whereIn('family_id', $families)->whereNull('revoked_at')->update(['revoked_at' => $now]);
        });
    }

    /** @param list<int> $authorizationIds */
    public function revokeAuthorizations(int $tenantId, array $authorizationIds): void
    {
        if ($authorizationIds === []) return;
        $now = date('Y-m-d H:i:s');
        OAuthAuthorization::forTenant($tenantId)->whereIn('id', $authorizationIds)->where('status', '<>', 'revoked')->update(['status' => 'revoked', 'decided_at' => $now]);
        OAuthAuthorizationCode::forTenant($tenantId)->whereIn('authorization_id', $authorizationIds)->whereNull('revoked_at')->update(['revoked_at' => $now]);
        $families = array_values(array_filter(OAuthToken::forTenant($tenantId)->whereIn('authorization_id', $authorizationIds)->whereNotNull('family_id')->column('family_id')));
        OAuthToken::forTenant($tenantId)->whereIn('authorization_id', $authorizationIds)->whereNull('revoked_at')->update(['revoked_at' => $now]);
        if ($families !== []) OAuthToken::forTenant($tenantId)->whereIn('family_id', $families)->whereNull('revoked_at')->update(['revoked_at' => $now]);
    }
}
