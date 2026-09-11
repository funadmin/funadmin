<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\OAuthAuthorization;
use app\common\model\identity\OAuthAuthorizationCode;
use app\common\model\identity\OAuthAuthorizationScope;
use app\common\model\identity\IdentityUser;
use app\identity\oauth\PkceService;
use DomainException;
use think\facade\Db;

final class AuthorizationTransactionService
{
    public function create(int $tenantId, int $clientId, string $redirectUri, array $protocol, array $scopeIds): string
    {
        $plain = self::randomOpaque();
        Db::transaction(function () use ($tenantId, $clientId, $redirectUri, $protocol, $scopeIds, $plain): void {
            $authorization = OAuthAuthorization::create(['tenant_id' => $tenantId, 'client_id' => $clientId, 'transaction_hash' => hash('sha256', $plain), 'redirect_uri' => $redirectUri, 'redirect_uri_hash' => hash('sha256', $redirectUri), 'state' => $protocol['state'], 'nonce' => $protocol['nonce'], 'code_challenge' => $protocol['code_challenge'], 'code_challenge_method' => 'S256', 'expires_at' => date('Y-m-d H:i:s', time() + 600)]);
            foreach ($scopeIds as $scopeId) OAuthAuthorizationScope::create(['tenant_id' => $tenantId, 'authorization_id' => $authorization->id, 'scope_id' => $scopeId]);
        });
        return $plain;
    }

    public function decide(string $transactionId, array $identity, bool $approved): array
    {
        return Db::transaction(function () use ($transactionId, $identity, $approved): array {
            $authorization = (new OAuthAuthorization())->where('transaction_hash', hash('sha256', $transactionId))->lock(true)->find();
            if (!$authorization || $authorization->status !== 'pending' || strtotime((string) $authorization->expires_at) <= time()) throw new DomainException('invalid_request');
            $user = IdentityUser::forTenant((int) $identity['tenant_id'])->where('id', (int) $identity['user_id'])->where('status', 1)->find();
            if (!$user || (int) $authorization->tenant_id !== (int) $identity['tenant_id']) throw new DomainException('access_denied');
            $now = date('Y-m-d H:i:s');
            if (!$approved) {
                $authorization->save(['status' => 'denied', 'user_id' => $user->id, 'session_id' => (string) $identity['session_id'], 'decided_at' => $now]);
                return ['approved' => false, 'redirect_uri' => $authorization->redirect_uri, 'state' => $authorization->state];
            }
            $plain = self::randomOpaque();
            OAuthAuthorizationCode::create(['tenant_id' => $authorization->tenant_id, 'authorization_id' => $authorization->id, 'client_id' => $authorization->client_id, 'user_id' => $user->id, 'code_prefix' => substr($plain, 0, 12), 'code_hash' => hash('sha256', $plain), 'redirect_uri_hash' => $authorization->redirect_uri_hash, 'code_challenge' => $authorization->code_challenge, 'nonce' => $authorization->nonce, 'expires_at' => date('Y-m-d H:i:s', time() + 300)]);
            $authorization->save(['status' => 'approved', 'user_id' => $user->id, 'auth_time' => date('Y-m-d H:i:s', (int) $identity['auth_time']), 'session_id' => (string) $identity['session_id'], 'decided_at' => $now]);
            return ['approved' => true, 'code' => $plain, 'redirect_uri' => $authorization->redirect_uri, 'state' => $authorization->state];
        });
    }

    public function consumeCode(string $code, int $clientId, string $redirectUri, string $verifier): array
    {
        return Db::transaction(function () use ($code, $clientId, $redirectUri, $verifier): array {
            $record = (new OAuthAuthorizationCode())->where('code_hash', hash('sha256', $code))->where('client_id', $clientId)->lock(true)->find();
            if (!$record || $record->consumed_at !== null || $record->revoked_at !== null || strtotime((string) $record->expires_at) <= time() || !hash_equals((string) $record->redirect_uri_hash, hash('sha256', $redirectUri)) || !PkceService::verify($verifier, (string) $record->code_challenge)) throw new DomainException('invalid_grant');
            $updated = (new OAuthAuthorizationCode())->where('id', $record->id)->whereNull('consumed_at')->whereNull('revoked_at')->update(['consumed_at' => date('Y-m-d H:i:s')]);
            if ($updated !== 1) throw new DomainException('invalid_grant');
            return $record->toArray();
        });
    }

    private static function randomOpaque(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
