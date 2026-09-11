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
            'jwks_uri' => $issuer . '/jwks',
            'userinfo_endpoint' => $issuer . '/userinfo',
            'revocation_endpoint' => $issuer . '/revoke',
            'introspection_endpoint' => $issuer . '/introspect',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'profile', 'email', 'phone', 'offline_access'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'none'],
            'code_challenge_methods_supported' => ['S256'],
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
            $jwk = json_decode((string) $row['public_jwk'], true);
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
