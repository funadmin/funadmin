<?php

declare(strict_types=1);

namespace app\identity\oauth\repository;

use app\common\model\identity\OAuthToken;
use app\identity\oauth\entity\ClientEntity;
use app\identity\oauth\entity\RefreshTokenEntity;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

final class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function getNewRefreshToken(): RefreshTokenEntity
    {
        return new RefreshTokenEntity();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $access = $refreshTokenEntity->getAccessToken();
        if (!$access->getClient() instanceof ClientEntity) return;
        $client = $access->getClient();
        $plain = (string) $refreshTokenEntity->getIdentifier();
        OAuthToken::create(['tenant_id' => $client->tenantId, 'client_id' => $client->databaseId, 'user_id' => $access->getUserIdentifier(), 'token_type' => 'refresh', 'token_prefix' => substr($plain, 0, 12), 'token_hash' => hash('sha256', $plain), 'family_id' => self::uuid(), 'generation' => 0, 'subject_type' => 'user', 'expires_at' => $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s')]);
    }

    public function revokeRefreshToken($tokenId): void
    {
        (new OAuthToken())->where('token_hash', hash('sha256', (string) $tokenId))->where('token_type', 'refresh')->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function isRefreshTokenRevoked($tokenId): bool
    {
        $token = (new OAuthToken())->where('token_hash', hash('sha256', (string) $tokenId))->where('token_type', 'refresh')->find();
        return !$token || $token->revoked_at !== null || $token->consumed_at !== null || strtotime((string) $token->expires_at) <= time();
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
