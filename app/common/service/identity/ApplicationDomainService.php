<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\ApplicationDomain;
use app\common\model\identity\EnterpriseApplication;
use DomainException;
use InvalidArgumentException;
use think\facade\Db;

final class ApplicationDomainService
{
    public function __construct(private readonly EnterpriseApplicationUrlPolicy $urlPolicy = new EnterpriseApplicationUrlPolicy())
    {
    }

    public function normalizeCallbacks(array $input, bool $development = false): array
    {
        $identity = $this->urlPolicy->normalizeDomain((string) ($input['identityCallback'] ?? ''), $development);
        $logout = $this->urlPolicy->normalizeDomain((string) ($input['logoutCallback'] ?? ''), $development);
        if ($identity['scheme'] !== $logout['scheme'] || $identity['host'] !== $logout['host'] || $identity['port'] !== $logout['port']) {
            throw new InvalidArgumentException('身份与退出回调必须属于同一精确源');
        }
        $domainType = (string) ($input['domainType'] ?? $input['domain_type'] ?? 'web');
        if (!in_array($domainType, ['web', 'admin', 'api', 'identity_callback', 'logout_callback'], true)) {
            throw new InvalidArgumentException('域名类型无效');
        }
        return $identity + [
            'domain_type' => $domainType,
            'identity_callback_path' => $identity['path'],
            'logout_callback_path' => $logout['path'],
        ];
    }

    public function replace(int $tenantId, int $applicationId, array $domains, bool $development = false, array $expectedDomainIds = []): array
    {
        $normalizedDomains = array_map(
            fn (array $domain): array => $this->normalizeCallbacks($domain, $development),
            array_map(static fn (mixed $domain): array => (array) $domain, $domains)
        );
        $expectedDomainIds = array_values(array_unique(array_map('intval', $expectedDomainIds)));
        sort($expectedDomainIds);

        return Db::transaction(function () use ($tenantId, $applicationId, $normalizedDomains, $expectedDomainIds): array {
            if (!EnterpriseApplication::forTenant($tenantId)->where('id', $applicationId)->lock(true)->find()) {
                throw new DomainException('应用不存在或不属于当前租户');
            }
            $currentDomains = ApplicationDomain::forTenant($tenantId)->where('application_id', $applicationId)->lock(true)->select()->toArray();
            $currentDomainIds = array_map(static fn (array $domain): int => (int) $domain['id'], $currentDomains);
            sort($currentDomainIds);
            if ($currentDomainIds !== $expectedDomainIds) {
                throw new DomainException('域名设置已被其他请求修改，请刷新后重试');
            }
            ApplicationDomain::forTenant($tenantId)->where('application_id', $applicationId)->delete();
            $result = [];
            foreach ($normalizedDomains as $index => $normalized) {
                $row = $normalized + ['tenant_id' => $tenantId, 'application_id' => $applicationId, 'is_primary' => $index === 0 ? 1 : 0, 'status' => 1];
                $result[] = ApplicationDomain::create($row)->toArray();
            }
            return $result;
        });
    }

    public function list(int $tenantId, int $applicationId): array
    {
        if (!EnterpriseApplication::forTenant($tenantId)->where('id', $applicationId)->find()) {
            throw new DomainException('应用不存在或不属于当前租户');
        }
        return ApplicationDomain::forTenant($tenantId)->where('application_id', $applicationId)->select()->toArray();
    }
}
