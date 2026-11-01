<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\ApplicationDomain;
use app\common\model\identity\BackchannelLogoutDelivery;
use app\common\model\identity\ClientSecret;
use app\common\model\identity\EnterpriseApplication;
use app\common\model\identity\IdentityAuditLog;
use app\common\model\identity\IdentityTenant;
use app\common\model\identity\OAuthAuthorizationCode;
use app\common\model\identity\OAuthClient;
use app\common\model\identity\OAuthToken;
use app\common\model\identity\OidcSession;
use RuntimeException;
use think\facade\Db;

/** SSO 日常清理、告警与健康检查；所有数据访问均显式限定租户。 */
final class IdentityOperationsService
{
    public function cleanup(int $tenantId, int $limit, bool $dryRun, int $auditRetentionDays = 180): array
    {
        $this->requireTenant($tenantId);
        $limit = max(1, min(10000, $limit));
        $now = date('Y-m-d H:i:s');
        $auditBefore = date('Y-m-d H:i:s', time() - max(1, $auditRetentionDays) * 86400);
        return Db::transaction(function () use ($tenantId, $limit, $dryRun, $now, $auditBefore): array {
            $targets = [
                'authorization_codes' => [OAuthAuthorizationCode::class, static fn ($query) => $query->where('expires_at', '<=', $now)],
                'tokens' => [OAuthToken::class, static fn ($query) => $query->where('expires_at', '<=', $now)],
                'sessions' => [OidcSession::class, static fn ($query) => $query->where('expires_at', '<=', $now)],
                'audit' => [IdentityAuditLog::class, static fn ($query) => $query->where('created_at', '<=', $auditBefore)],
            ];
            $result = [];
            foreach ($targets as $name => [$model, $scope]) {
                $query = $scope($model::forTenant($tenantId))->order('id')->limit($limit);
                $ids = array_map('intval', $query->column('id'));
                $result[$name] = count($ids);
                if (!$dryRun && $ids !== []) $model::forTenant($tenantId)->whereIn('id', $ids)->delete();
            }
            return ['tenant_id' => $tenantId, 'dry_run' => $dryRun, 'limit' => $limit, 'matched' => $result];
        });
    }

    public function health(int $tenantId, int $secretWarningDays = 30): array
    {
        $this->requireTenant($tenantId);
        $now = date('Y-m-d H:i:s');
        $warningAt = date('Y-m-d H:i:s', time() + max(1, $secretWarningDays) * 86400);
        $secretRows = ClientSecret::forTenant($tenantId)->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', $warningAt)->withoutField('secret_hash')->select()->toArray();
        $dead = BackchannelLogoutDelivery::forTenant($tenantId)->where('status', 'dead')->count();
        $replays = IdentityAuditLog::forTenant($tenantId)->where('event_type', 'oauth.refresh_replay')->where('created_at', '>=', date('Y-m-d H:i:s', time() - 86400))->count();
        $applications = EnterpriseApplication::forTenant($tenantId)->whereNull('deleted_at')->select()->toArray();
        $applicationHealth = [];
        foreach ($applications as $application) {
            $id = (int) $application['id'];
            $domains = ApplicationDomain::forTenant($tenantId)->where('application_id', $id)->where('status', 1)->whereNull('deleted_at')->select()->toArray();
            $clients = OAuthClient::forTenant($tenantId)->where('application_id', $id)->where('status', 'active')->whereNull('deleted_at')->count();
            $domainIssues = [];
            foreach ($domains as $domain) {
                if ((string) $domain['scheme'] !== 'https' && !in_array((string) $domain['host'], ['localhost', '127.0.0.1', '::1'], true)) $domainIssues[] = 'insecure_scheme';
                if ($domain['verified_at'] === null) $domainIssues[] = 'unverified_domain';
            }
            $applicationHealth[] = [
                'id' => $id,
                'code' => (string) $application['code'],
                'healthy' => (string) $application['status'] !== 'published' || ($domains !== [] && $clients > 0 && $domainIssues === []),
                'domains' => count($domains),
                'clients' => $clients,
                'issues' => array_values(array_unique($domainIssues)),
            ];
        }
        $unhealthy = count(array_filter($applicationHealth, static fn (array $row): bool => !$row['healthy']));
        return [
            'tenant_id' => $tenantId,
            'checked_at' => $now,
            'healthy' => $dead === 0 && $replays === 0 && $secretRows === [] && $unhealthy === 0,
            'alerts' => ['refresh_replay_24h' => $replays, 'expiring_secrets' => $secretRows, 'backchannel_dead' => $dead, 'unhealthy_applications' => $unhealthy],
            'applications' => $applicationHealth,
        ];
    }

    public function withLock(int $tenantId, string $operation, callable $callback): mixed
    {
        $name = 'identity:' . $tenantId . ':' . preg_replace('/[^a-z0-9:_-]/i', '', $operation);
        $locked = (int) (Db::query('SELECT GET_LOCK(?, 0) AS acquired', [$name])[0]['acquired'] ?? 0);
        if ($locked !== 1) throw new RuntimeException('同租户运维任务正在执行');
        try {
            return $callback();
        } finally {
            Db::query('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }

    private function requireTenant(int $tenantId): void
    {
        if ($tenantId <= 0 || !IdentityTenant::forTenant($tenantId)->where('status', 1)->whereNull('deleted_at')->find()) {
            throw new RuntimeException('租户不存在或不可用');
        }
    }
}
