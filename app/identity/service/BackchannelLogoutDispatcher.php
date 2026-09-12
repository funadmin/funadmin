<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\BackchannelLogoutDelivery;
use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;
use Throwable;

/** 可由队列 worker 或测试直接调用的可靠 backchannel 投递器。 */
final class BackchannelLogoutDispatcher
{
    public function enqueue(int $tenantId, int $opSessionId, array $clientSession, string $clientIdentifier, string $uri): ?array
    {
        try {
            (new BackchannelUrlPolicy())->validate($uri);
            $jti = Uuid::uuid4()->toString();
            $token = (new LogoutTokenService())->issue($tenantId, $clientIdentifier, (string) $clientSession['sid'], $jti);
            return BackchannelLogoutDelivery::create([
                'tenant_id' => $tenantId, 'oidc_session_id' => $opSessionId, 'client_session_id' => (int) $clientSession['id'],
                'client_id' => (int) $clientSession['client_id'], 'jti' => $jti, 'logout_uri' => $uri,
                'logout_token' => $token, 'payload_hash' => hash('sha256', $token), 'status' => 'pending',
                'attempts' => 0, 'next_attempt_at' => date('Y-m-d H:i:s'),
            ])->toArray();
        } catch (Throwable $exception) {
            (new IdentityAuditService())->record($tenantId, 'backchannel.enqueue', false, (int) ($clientSession['user_id'] ?? 0), (int) $clientSession['client_id'], ['reason' => $exception->getMessage()]);
            return null;
        }
    }

    /** 兼容旧调用；实际投递始终经过原子 claim。 */
    public function dispatch(int $deliveryId): bool
    {
        return (new BackchannelLogoutWorker())->retry($deliveryId);
    }

    /** 仅供已持有有效 claim 的 worker 调用。 */
    public function send(array $delivery): int
    {
        $target = (new BackchannelUrlPolicy())->validate((string) $delivery['logout_uri']);
        $resolve = array_map(static fn (string $ip): string => $target['host'] . ':443:' . $ip, $target['addresses']);
        $response = (new Client(['timeout' => 3.0, 'connect_timeout' => 1.0, 'allow_redirects' => false, 'http_errors' => false]))->post($target['url'], [
            'form_params' => ['logout_token' => (string) $delivery['logout_token']],
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded'],
            'curl' => [CURLOPT_RESOLVE => $resolve, CURLOPT_MAXFILESIZE => 65536],
            'stream' => true,
        ]);
        $body = $response->getBody()->read(65537);
        $status = $response->getStatusCode();
        if (strlen($body) > 65536 || $status < 200 || $status >= 300) throw new \RuntimeException('rp_http_' . $status);
        return $status;
    }
}
