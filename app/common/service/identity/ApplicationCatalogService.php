<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\ApplicationAssignment;
use app\common\model\identity\EnterpriseApplication;
use app\common\model\identity\IdentityUser;
use DomainException;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final class ApplicationCatalogService
{
    public function __construct(private readonly EnterpriseApplicationUrlPolicy $urlPolicy = new EnterpriseApplicationUrlPolicy())
    {
    }

    public function list(int $tenantId, int $page = 1, int $pageSize = 20, string $keyword = ''): array
    {
        $query = EnterpriseApplication::forTenant($tenantId)->withoutField('oauth_config')->order('id', 'desc');
        if ($keyword !== '') {
            $query->whereLike('name|code', '%' . addcslashes($keyword, '%_') . '%');
        }
        $total = (clone $query)->count();
        return ['list' => $query->page(max(1, $page), min(100, max(1, $pageSize)))->select()->toArray(), 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    public function detail(int $tenantId, int $applicationId): array
    {
        return $this->application($tenantId, $applicationId)->hidden(['oauth_config'])->toArray();
    }

    public function save(int $tenantId, array $input, ?int $applicationId = null, bool $development = false, ?string $sameOriginHost = null): array
    {
        $runtimeType = (string) ($input['runtimeType'] ?? $input['runtime_type'] ?? '');
        $databaseMode = (string) ($input['databaseMode'] ?? $input['database_mode'] ?? 'shared');
        $visibility = (string) ($input['visibility'] ?? 'tenant');
        if (!in_array($databaseMode, ['shared', 'dedicated', 'external'], true) || !in_array($visibility, ['private', 'tenant', 'public'], true)) {
            throw new InvalidArgumentException('数据模式或可见性无效');
        }
        $ownerIdentityUserId = $input['ownerIdentityUserId'] ?? $input['owner_identity_user_id'] ?? null;
        if ($ownerIdentityUserId !== null && (!is_numeric($ownerIdentityUserId) || (int) $ownerIdentityUserId <= 0
            || !IdentityUser::forTenant($tenantId)->where('id', (int) $ownerIdentityUserId)->where('status', 1)->find())) {
            throw new InvalidArgumentException('应用所有者统一身份无效');
        }
        $row = [
            'tenant_id' => $tenantId,
            'code' => strtolower(trim((string) ($input['code'] ?? ''))),
            'name' => trim((string) ($input['name'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'icon' => ($icon = trim((string) ($input['icon'] ?? ''))) === '' ? null : $icon,
            'sort_order' => (int) ($input['sortOrder'] ?? $input['sort_order'] ?? 0),
            'visibility' => $visibility,
            'runtime_type' => $runtimeType,
            'database_mode' => $databaseMode,
            'launch_url' => $this->urlPolicy->normalizeLaunchUrl($runtimeType, (string) ($input['launchUrl'] ?? $input['launch_url'] ?? ''), $development, $sameOriginHost),
            'base_url' => ($baseUrl = trim((string) ($input['baseUrl'] ?? $input['base_url'] ?? ''))) === '' ? null : $this->urlPolicy->normalizeLaunchUrl($runtimeType, $baseUrl, $development, $sameOriginHost),
            'owner' => ($owner = trim((string) ($input['owner'] ?? ''))) === '' ? null : $owner,
            'owner_identity_user_id' => $ownerIdentityUserId === null ? null : (int) $ownerIdentityUserId,
            'logo_url' => ($logo = trim((string) ($input['logoUrl'] ?? $input['logo_url'] ?? ''))) === '' ? null : $logo,
            'brand_config' => json_encode((array) ($input['brandConfig'] ?? $input['brand_config'] ?? []), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
        if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $row['code']) || $row['name'] === '') {
            throw new InvalidArgumentException('应用 code 或名称无效');
        }
        $application = $applicationId ? $this->application($tenantId, $applicationId) : new EnterpriseApplication();
        if (!$applicationId) {
            $row['public_id'] = Uuid::uuid4()->toString();
            $row['status'] = 'draft';
        }
        $application->save($row);
        return $application->hidden(['oauth_config'])->toArray();
    }

    public function publish(int $tenantId, int $applicationId): array
    {
        $application = $this->application($tenantId, $applicationId);
        $application->save(['status' => 'published', 'published_at' => date('Y-m-d H:i:s')]);
        return $application->hidden(['oauth_config'])->toArray();
    }

    public function disable(int $tenantId, int $applicationId): array
    {
        $application = $this->application($tenantId, $applicationId);
        $application->save(['status' => 'disabled']);
        return $application->hidden(['oauth_config'])->toArray();
    }

    public function delete(int $tenantId, int $applicationId): void
    {
        $application = $this->application($tenantId, $applicationId);
        if ($application->status === 'published') {
            throw new DomainException('已发布应用必须先停用再删除');
        }
        $application->delete();
    }

    public function launch(int $tenantId, int $applicationId, int $userId, array $departmentIds, array $roleIds): array
    {
        $application = $this->application($tenantId, $applicationId);
        if ($application->status !== 'published') {
            throw new DomainException('草稿或已禁用应用不可进入');
        }

        $assignments = ApplicationAssignment::forTenant($tenantId)
            ->where('application_id', $applicationId)
            ->where('status', 1)
            ->select()
            ->toArray();
        if (!ApplicationAssignmentService::canLaunch(
            (string) $application->visibility,
            $application->owner_identity_user_id === null ? null : (int) $application->owner_identity_user_id,
            $assignments,
            $userId,
            $departmentIds,
            $roleIds
        )) {
            throw new DomainException('当前账号未获准进入该应用');
        }

        return [
            'applicationId' => (int) $application->id,
            'code' => (string) $application->code,
            'runtimeType' => (string) $application->runtime_type,
            'launchUrl' => (string) $application->launch_url,
        ];
    }

    private function application(int $tenantId, int $applicationId): EnterpriseApplication
    {
        $application = EnterpriseApplication::forTenant($tenantId)->where('id', $applicationId)->find();
        if (!$application) {
            throw new DomainException('应用不存在或不属于当前租户');
        }
        return $application;
    }
}
