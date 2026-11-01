<?php

declare(strict_types=1);

namespace app\identity\controller;

use app\common\service\identity\SigningKeyService;
use app\identity\service\IssuerService;
use think\response\Json;

final class Metadata
{
    public function discovery(): Json
    {
        $issuer = $this->issuer();
        return $this->secureJson([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/authorize',
            'token_endpoint' => $issuer . '/token',
            'jwks_uri' => $issuer . '/.well-known/jwks.json',
            'userinfo_endpoint' => $issuer . '/userinfo',
            'end_session_endpoint' => $issuer . '/logout',
            'backchannel_logout_supported' => true,
            'backchannel_logout_session_supported' => true,
            'revocation_endpoint' => $issuer . '/revoke',
            'introspection_endpoint' => $issuer . '/introspect',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'subject_types_supported' => ['public', 'pairwise'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'profile', 'email', 'phone', 'organization', 'roles', 'permissions', 'offline_access'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'none'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['sub', 'name', 'preferred_username', 'picture', 'locale', 'email', 'email_verified', 'phone_number', 'phone_number_verified', 'tenant', 'departments', 'organization', 'roles', 'permissions'],
            'request_parameter_supported' => false,
            'request_uri_parameter_supported' => false,
        ]);
    }

    public function oauth(): Json
    {
        $issuer = $this->issuer();
        return $this->secureJson([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/authorize',
            'token_endpoint' => $issuer . '/token',
            'revocation_endpoint' => $issuer . '/revoke',
            'introspection_endpoint' => $issuer . '/introspect',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'none'],
            'code_challenge_methods_supported' => ['S256'],
        ]);
    }

    public function jwks(): Json
    {
        $rows = (new SigningKeyService())->listPublishable(1);
        $keys = [];
        foreach ($rows as $row) {
            $jwk = $row['public_jwk'] ?? null;
            if (is_string($jwk)) $jwk = json_decode($jwk, true);
            if (is_array($jwk)) $keys[] = $jwk;
        }
        return $this->secureJson(['keys' => $keys]);
    }

    private function issuer(): string
    {
        return (new IssuerService((string) config('identity.issuer', '')))->getIssuer();
    }

    private function secureJson(array $data, int $status = 200): Json
    {
        return json($data, $status)->header(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY', 'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'"]);
    }
}
