<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\EnterpriseApplication;
use app\common\model\identity\OAuthClient;
use DomainException;
use InvalidArgumentException;

/** 管理应用与 client 的 OIDC claim 白名单，未知 claim 默认拒绝。 */
final class OidcClaimPolicyService
{
    private const CLAIMS = ['sub', 'name', 'preferred_username', 'picture', 'locale', 'email', 'email_verified', 'phone_number', 'phone_number_verified', 'organization', 'tenant', 'departments', 'roles', 'permissions'];

    public function saveApplicationPolicy(int $tenantId, int $applicationId, array $allowedClaims): array
    {
        $application = EnterpriseApplication::forTenant($tenantId)->where('id', $applicationId)->find();
        if (!$application) throw new DomainException('应用不存在或跨租户');
        $policy = ['allowed_claims' => $this->allowedClaims($allowedClaims)];
        $application->save(['claim_policy' => json_encode($policy, JSON_THROW_ON_ERROR)]);
        return $policy;
    }

    public function saveClientPolicy(int $tenantId, int $clientId, array $input): array
    {
        $client = OAuthClient::forTenant($tenantId)->where('id', $clientId)->find();
        if (!$client) throw new DomainException('OAuth client 不存在或跨租户');
        $subjectType = (string) ($input['subjectType'] ?? 'public');
        $sectorIdentifier = trim((string) ($input['sectorIdentifier'] ?? ''));
        if (!in_array($subjectType, ['public', 'pairwise'], true)) throw new InvalidArgumentException('subjectType 无效');
        if ($subjectType === 'pairwise' && ($sectorIdentifier === '' || strlen($sectorIdentifier) > 253)) throw new InvalidArgumentException('pairwise sectorIdentifier 无效');
        $allowedClaims = $this->allowedClaims((array) ($input['allowedClaims'] ?? []));
        $client->save(['subject_type' => $subjectType, 'sector_identifier' => $subjectType === 'pairwise' ? $sectorIdentifier : null, 'allowed_claims' => json_encode($allowedClaims, JSON_THROW_ON_ERROR)]);
        return ['subjectType' => $subjectType, 'sectorIdentifier' => $subjectType === 'pairwise' ? $sectorIdentifier : null, 'allowedClaims' => $allowedClaims];
    }

    public function allowedClaims(array $claims): array
    {
        $claims = array_values(array_unique(array_map('strval', $claims)));
        if (array_diff($claims, self::CLAIMS) !== []) throw new InvalidArgumentException('包含未知 OIDC claim');
        return $claims;
    }
}
