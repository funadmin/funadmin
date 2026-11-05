<?php

declare(strict_types=1);

namespace app\identity\oauth;

use DateInterval;
use app\identity\oauth\repository\AccessTokenRepository;
use app\identity\oauth\repository\AuthCodeRepository;
use app\identity\oauth\repository\ClientRepository;
use app\identity\oauth\repository\RefreshTokenRepository;
use app\identity\oauth\repository\ScopeRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;

/**
 * 仅通过 league/oauth2-server 8.5.5 公开构造器与 grant API 装配服务。
 */
final class AuthorizationServerFactory
{
    public function create(): AuthorizationServer
    {
        $clients = new ClientRepository();
        $scopes = new ScopeRepository();
        $accessTokens = new AccessTokenRepository();
        $refreshTokens = new RefreshTokenRepository();
        $authCodes = new AuthCodeRepository();
        $privateKey = (string) config('oauth.private_key_path', '');
        $encryptionKey = (string) config('oauth.encryption_key', '');
        $server = new AuthorizationServer($clients, $accessTokens, $scopes, $privateKey, $encryptionKey);
        $codeGrant = new AuthCodeGrant($authCodes, $refreshTokens, new DateInterval((string) config('oauth.authorization_code_ttl', 'PT5M')));
        $server->enableGrantType($codeGrant, new DateInterval((string) config('oauth.access_token_ttl', 'PT1H')));
        $server->enableGrantType(new RefreshTokenGrant($refreshTokens), new DateInterval((string) config('oauth.access_token_ttl', 'PT1H')));
        $server->enableGrantType(new ClientCredentialsGrant(), new DateInterval((string) config('oauth.access_token_ttl', 'PT1H')));

        return $server;
    }
}
