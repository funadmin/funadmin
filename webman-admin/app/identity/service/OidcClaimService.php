<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\EnterpriseApplication;
use app\common\model\identity\IdentityTenant;
use app\common\model\identity\IdentityUser;
use app\common\model\identity\OAuthClient;
use DomainException;
use support\Db;

/** 按 scope、应用策略和 client 白名单的交集生成最小 OIDC claims。 */
final class OidcClaimService
{
    private const SCOPE_CLAIMS = [
        'openid' => ['sub'],
        'profile' => ['name', 'preferred_username', 'picture', 'locale'],
        'email' => ['email', 'email_verified'],
        'phone' => ['phone_number', 'phone_number_verified'],
        'organization' => ['tenant', 'departments', 'organization'],
        'roles' => ['roles'],
        'permissions' => ['permissions'],
    ];

    public function __construct(private readonly SubjectService $subjects = new SubjectService())
    {
    }

    public function claims(int $tenantId, int $userId, int $clientId, array $scopes): array
    {
        $scopes = array_values(array_unique(array_map('strval', $scopes)));
        if (!in_array('openid', $scopes, true)) return [];
        $client = OAuthClient::forTenant($tenantId)->where('id', $clientId)->where('status', 'active')->whereNull('deleted_at')->first();
        $user = IdentityUser::forTenant($tenantId)->where('id', $userId)->where('status', 1)->whereNull('deleted_at')->first();
        $tenant = IdentityTenant::where('id', $tenantId)->where('status', 1)->whereNull('deleted_at')->first();
        if (!$client || !$user || !$tenant) throw new DomainException('OIDC claim 主体不可用');
        $application = EnterpriseApplication::forTenant($tenantId)->where('id', (int) $client->application_id)->whereNull('deleted_at')->first();
        if (!$application) throw new DomainException('OIDC claim 应用不可用');
        $allowedClaims = $this->allowedClaims($client->allowed_claims, $application->claim_policy);
        $subject = (string) $client->subject_type === 'pairwise'
            ? $this->subjects->pairwiseSubject((string) $tenant->public_id, (string) $client->sector_identifier, (string) $user->public_id)
            : $this->subjects->publicSubject((string) $tenant->public_id, (string) $user->public_id);
        $available = [
            'sub' => $subject,
            'name' => (string) $user->display_name,
            'preferred_username' => (string) $user->username,
            'picture' => $user->avatar ? (string) $user->avatar : null,
            'locale' => (string) $user->locale,
            'email' => $user->email ? (string) $user->email : null,
            'phone_number' => $user->mobile ? (string) $user->mobile : null,
            'email_verified' => $user->email ? false : null,
            'phone_number_verified' => $user->mobile ? false : null,
        ];
        if (in_array('organization', $scopes, true)) {
            $available['tenant'] = ['public_id' => (string) $tenant->public_id, 'code' => (string) $tenant->code, 'name' => (string) $tenant->name];
            $available['departments'] = $this->organization($tenantId, $userId);
            $available['organization'] = $available['departments'];
        }
        if (in_array('roles', $scopes, true)) $available['roles'] = $this->roles($tenantId, (int) $client->application_id, $userId);
        if (in_array('permissions', $scopes, true)) $available['permissions'] = $this->permissions($tenantId, (int) $client->application_id, $userId);
        $claims = [];
        foreach ($scopes as $scope) {
            foreach (self::SCOPE_CLAIMS[$scope] ?? [] as $claim) {
                if (in_array($claim, $allowedClaims, true) && $available[$claim] !== null) $claims[$claim] = $available[$claim];
            }
        }
        return $claims;
    }

    /** 未知 claim 默认拒绝；sub 始终仅由 openid 产生。 */
    public function allowedClaims(mixed $clientClaims, mixed $claimPolicy): array
    {
        $known = array_values(array_unique(array_merge(...array_values(self::SCOPE_CLAIMS))));
        $client = $this->jsonList($clientClaims, $known);
        $policy = $this->jsonList($claimPolicy, $known);
        return array_values(array_intersect($known, $client, $policy));
    }

    private function jsonList(mixed $value, array $default): array
    {
        if ($value === null || $value === '') return $default;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (is_array($value) && array_key_exists('allowed_claims', $value)) {
            $value = $value['allowed_claims'];
        }
        return array_values(array_unique(array_map('strval', is_array($value) ? $value : [])));
    }

    private function organization(int $tenantId, int $userId): array
    {
        return Db::table('identity_user_department as ud')->join('department as d', 'd.id', '=', 'ud.department_id')->where('ud.tenant_id', $tenantId)->where('ud.user_id', $userId)->where('d.status', 1)->orderByDesc('ud.is_primary')->get(['d.name', 'ud.is_primary'])->map(static fn ($row): array => (array) $row)->all();
    }

    private function roles(int $tenantId, int $applicationId, int $userId): array
    {
        return Db::table('application_user_role as ur')->join('application_role as r', static function ($join): void {
            $join->on('r.id', '=', 'ur.role_id')->on('r.tenant_id', '=', 'ur.tenant_id')->on('r.application_id', '=', 'ur.application_id');
        })->where('ur.tenant_id', $tenantId)->where('ur.application_id', $applicationId)->where('ur.user_id', $userId)->where('r.status', 1)->pluck('r.code')->all();
    }

    private function permissions(int $tenantId, int $applicationId, int $userId): array
    {
        $direct = Db::table('application_user_permission as up')->join('application_permission as p', static function ($join): void {
            $join->on('p.id', '=', 'up.permission_id')->on('p.tenant_id', '=', 'up.tenant_id')->on('p.application_id', '=', 'up.application_id');
        })->where('up.tenant_id', $tenantId)->where('up.application_id', $applicationId)->where('up.user_id', $userId)->where('p.status', 1)->pluck('p.code')->all();
        $role = Db::table('application_user_role as ur')->join('application_role_permission as rp', static function ($join): void {
            $join->on('rp.role_id', '=', 'ur.role_id')->on('rp.tenant_id', '=', 'ur.tenant_id')->on('rp.application_id', '=', 'ur.application_id');
        })->join('application_permission as p', static function ($join): void {
            $join->on('p.id', '=', 'rp.permission_id')->on('p.tenant_id', '=', 'rp.tenant_id')->on('p.application_id', '=', 'rp.application_id');
        })->where('ur.tenant_id', $tenantId)->where('ur.application_id', $applicationId)->where('ur.user_id', $userId)->where('p.status', 1)->pluck('p.code')->all();
        return array_values(array_unique(array_merge($direct, $role)));
    }
}
