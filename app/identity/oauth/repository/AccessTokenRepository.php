<?php

declare(strict_types=1);

namespace app\identity\oauth\repository;

use app\common\model\identity\OAuthToken;
use app\common\model\identity\OAuthTokenScope;
use app\identity\oauth\entity\AccessTokenEntity;
use app\identity\oauth\entity\ClientEntity;
use app\identity\oauth\entity\ScopeEntity;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use think\facade\Db;

final class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null): AccessTokenEntity
    {
        $token = new AccessTokenEntity();
        $token->setClient($clientEntity);
        $token->setUserIdentifier($userIdentifier);
        foreach ($scopes as $scope) $token->addScope($scope);
        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        if (!$accessTokenEntity->getClient() instanceof ClientEntity) return;
        $client = $accessTokenEntity->getClient();
        Db::transaction(function () use ($accessTokenEntity, $client): void {
            $plain = (string) $accessTokenEntity->getIdentifier();
            $token = OAuthToken::create(['tenant_id' => $client->tenantId, 'client_id' => $client->databaseId, 'user_id' => $accessTokenEntity->getUserIdentifier(), 'token_type' => 'access', 'token_prefix' => substr($plain, 0, 12), 'token_hash' => hash('sha256', $plain), 'subject_type' => $accessTokenEntity->getUserIdentifier() === null ? 'client' : 'user', 'expires_at' => $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s')]);
            foreach ($accessTokenEntity->getScopes() as $scope) if ($scope instanceof ScopeEntity) OAuthTokenScope::create(['tenant_id' => $client->tenantId, 'token_id' => $token->id, 'scope_id' => $scope->databaseId]);
        });
    }

    public function revokeAccessToken($tokenId): void
    {
        (new OAuthToken())->where('token_hash', hash('sha256', (string) $tokenId))->where('token_type', 'access')->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function isAccessTokenRevoked($tokenId): bool
    {
        $token = (new OAuthToken())->where('token_hash', hash('sha256', (string) $tokenId))->where('token_type', 'access')->find();
        return !$token || $token->revoked_at !== null || strtotime((string) $token->expires_at) <= time();
    }
}
