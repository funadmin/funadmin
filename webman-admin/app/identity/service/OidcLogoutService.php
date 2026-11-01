<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\OAuthAuthorization;
use app\common\model\identity\OAuthClient;
use app\common\model\identity\OidcClientSession;
use think\facade\Db;

/** 本地退出优先完成，远端通知仅持久化入队且不影响本地结果。 */
final class OidcLogoutService
{
    public function logout(int $tenantId, string $sid, bool $global = true): array
    {
        $targets = [];
        $result = Db::transaction(function () use ($tenantId, $sid, $global, &$targets): array {
            $hinted = OidcClientSession::forTenant($tenantId)->where('sid', $sid)->lock(true)->find();
            if (!$hinted) return [];
            $query = OidcClientSession::forTenant($tenantId)->where('oidc_session_id', (int) $hinted->oidc_session_id);
            if (!$global) $query->where('id', (int) $hinted->id);
            $allTargets = $query->select()->toArray();
            $targets = array_values(array_filter($allTargets, static fn (array $target): bool => (string) ($target['status'] ?? '') === 'active'));
            $result = (new OidcSessionService())->end($tenantId, $sid, $global);
            $clientSessionIds = array_map('intval', array_column($allTargets, 'id'));
            if ($clientSessionIds === []) return $result;
            $now = date('Y-m-d H:i:s');
            $authorizationIds = array_map('intval', OAuthAuthorization::forTenant($tenantId)->whereIn('client_session_id', $clientSessionIds)->column('id'));
            (new AuthorizationRevocationService())->revokeAuthorizations($tenantId, $authorizationIds);
            return $result;
        });
        if ($result === []) return [];
        if ((new IdentitySsoConfigService())->allowsBackchannelLogout($tenantId)) {
            $dispatcher = new BackchannelLogoutDispatcher();
            foreach ($targets as $target) {
                $client = OAuthClient::forTenant($tenantId)->where('id', (int) $target['client_id'])->find();
                $uri = trim((string) ($client?->backchannel_logout_uri ?? ''));
                if ($client && $uri !== '') $dispatcher->enqueue($tenantId, (int) $result['oidc_session_id'], $target, (string) $client->client_id, $uri);
            }
        }
        (new IdentityAuditService())->record($tenantId, 'logout', true, (int) $result['user_id'], null, ['global' => $global]);
        return $result + ['targets' => count($targets)];
    }
}
