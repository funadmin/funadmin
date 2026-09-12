<?php

declare(strict_types=1);

namespace app\identity\controller;

use app\common\service\BearerTokenExtractor;
use app\common\service\identity\OAuthScopeService;
use app\identity\oauth\AuthorizationRequestValidator;
use app\identity\oauth\entity\ClientEntity;
use app\identity\oauth\repository\ClientRepository;
use app\identity\service\AuthorizationTransactionService;
use app\identity\service\IdentitySessionResolverFactory;
use app\identity\service\IdTokenService;
use app\identity\service\OidcClaimService;
use app\identity\service\OpaqueTokenService;
use DomainException;
use InvalidArgumentException;
use think\Request;
use think\Response;
use think\response\Json;

final class OAuth
{
    public function authorize(Request $request): Response
    {
        try {
            $client = (new ClientRepository())->getClientEntity((string) $request->get('client_id', ''));
            if (!$client) return $this->error('invalid_request', 400);
            $protocol = (new AuthorizationRequestValidator())->validateProtocol($request->get());
            $redirect = (string) $request->get('redirect_uri', '');
            if (!in_array($redirect, $client->getRedirectUri(), true) || !in_array('authorization_code', $client->grants, true)) return $this->error('invalid_request', 400);
            if (array_diff($protocol['scopes'], $client->allowedScopes) !== []) return $this->redirectError($redirect, 'invalid_scope', $protocol['state']);
            $scopeIds = (new OAuthScopeService())->resolveIds($client->tenantId, $protocol['scopes']);
            $transactionId = (new AuthorizationTransactionService())->create($client->tenantId, $client->databaseId, $redirect, $protocol, $scopeIds);
            $interactionUrl = '/identity/interaction?transaction_id=' . rawurlencode($transactionId);
            if (str_contains(strtolower((string) $request->header('accept', '')), 'text/html')) return redirect($interactionUrl)->header($this->securityHeaders());
            return $this->secureJson(['transaction_id' => $transactionId, 'interaction_url' => $interactionUrl]);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 400);
        }
    }

    public function decision(Request $request): Response
    {
        $identity = IdentitySessionResolverFactory::make()->resolve($request);
        if ($identity === null) return $this->error('login_required', 401);
        try {
            $result = (new AuthorizationTransactionService())->decide((string) $request->post('transaction_id', ''), $identity, filter_var($request->post('approved', false), FILTER_VALIDATE_BOOL));
            $parameters = $result['approved'] ? ['code' => $result['code']] : ['error' => 'access_denied'];
            if ($result['state'] !== null) $parameters['state'] = $result['state'];
            return redirect($this->appendQuery($result['redirect_uri'], $parameters));
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 400);
        }
    }

    public function token(Request $request): Json
    {
        if (!str_starts_with(strtolower((string) $request->header('content-type', '')), 'application/x-www-form-urlencoded')) return $this->error('invalid_request', 400);
        try {
            [$client, $grant] = $this->authenticateClient($request);
            $scopeIds = $this->requestedScopeIds($client, (string) $request->post('scope', ''));
            $tokens = match ($grant) {
                'client_credentials' => $this->clientCredentials($client, $scopeIds),
                'authorization_code' => $this->authorizationCode($request, $client, $scopeIds),
                'refresh_token' => (new OpaqueTokenService())->rotate((string) $request->post('refresh_token', ''), $client->databaseId, $scopeIds),
                default => throw new DomainException('unsupported_grant_type'),
            };
            return $this->secureJson($tokens);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), $exception->getMessage() === 'invalid_client' ? 401 : 400);
        }
    }

    public function revoke(Request $request): Json
    {
        try { [$client] = $this->authenticateClient($request, false); } catch (DomainException) { return $this->error('invalid_client', 401); }
        (new OpaqueTokenService())->revoke((string) $request->post('token', ''), $client->databaseId);
        return $this->secureJson([]);
    }

    public function introspect(Request $request): Json
    {
        try {
            [$client] = $this->authenticateClient($request, false);
            if (!$client->isConfidential()) throw new DomainException('invalid_client');
            $result = (new OpaqueTokenService())->inspect((string) $request->post('token', ''));
            if (($result['active'] ?? false) && (int) ($result['_record']['client_id'] ?? 0) !== $client->databaseId) $result = ['active' => false];
            unset($result['_record']);
            return $this->secureJson(array_filter($result, static fn ($value): bool => $value !== null));
        } catch (DomainException) {
            return $this->error('invalid_client', 401);
        }
    }

    public function userinfo(Request $request): Json
    {
        $plain = (new BearerTokenExtractor())->extract($request);
        if ($plain === null) return $this->error('invalid_token', 401);
        $token = (new OpaqueTokenService())->inspect($plain);
        $scopes = array_values(array_filter(explode(' ', (string) ($token['scope'] ?? ''))));
        if (!($token['active'] ?? false) || !in_array('openid', $scopes, true) || empty($token['_record']['user_id'])) return $this->error('invalid_token', 401);
        $record = $token['_record'];
        return $this->secureJson((new OidcClaimService())->claims((int) $record['tenant_id'], (int) $record['user_id'], (int) $record['client_id'], $scopes));
    }

    private function authorizationCode(Request $request, ClientEntity $client, array $scopeIds): array
    {
        $code = (new AuthorizationTransactionService())->consumeCode((string) $request->post('code', ''), $client->databaseId, (string) $request->post('redirect_uri', ''), (string) $request->post('code_verifier', ''));
        $original = (new \app\common\model\identity\OAuthAuthorizationScope())->where('authorization_id', $code['authorization_id'])->column('scope_id');
        if ($scopeIds !== [] && array_diff($scopeIds, $original) !== []) throw new DomainException('invalid_scope');
        $granted = $scopeIds ?: $original;
        $authorization = \app\common\model\identity\OAuthAuthorization::forTenant($client->tenantId)->where('id', $code['authorization_id'])->find();
        if (!$authorization) throw new DomainException('invalid_grant');
        $tokens = (new OpaqueTokenService())->issue($client->tenantId, $client->databaseId, (int) $code['user_id'], $granted, (int) $code['authorization_id'], sessionId: (string) $authorization->session_id);
        if (in_array('openid', $this->scopeNames($granted), true)) {
            $tokens['id_token'] = (new IdTokenService())->issue($client->tenantId, (int) $code['user_id'], $client->databaseId, $client->getIdentifier(), $this->scopeNames($granted), strtotime((string) $authorization->auth_time), $code['nonce'] ?: null, (string) $authorization->session_id);
        }
        return $tokens;
    }

    private function clientCredentials(ClientEntity $client, array $scopeIds): array
    {
        if ($client->clientType !== 'machine') throw new DomainException('unauthorized_client');
        \app\common\service\identity\OAuthScopeService::validateMachineScopes($this->scopeNames($scopeIds));
        return (new OpaqueTokenService())->issue($client->tenantId, $client->databaseId, null, $scopeIds);
    }

    private function authenticateClient(Request $request, bool $requireGrant = true): array
    {
        $grant = (string) $request->post('grant_type', ''); $clientId = (string) $request->post('client_id', ''); $secret = null;
        $authorization = (string) $request->header('authorization', '');
        $usedBasic = str_starts_with(strtolower($authorization), 'basic ');
        if ($usedBasic) {
            $decoded = base64_decode(substr($authorization, 6), true);
            if (!is_string($decoded) || !str_contains($decoded, ':')) throw new DomainException('invalid_client');
            [$clientId, $secret] = explode(':', $decoded, 2);
        }
        $repository = new ClientRepository(); $client = $repository->getClientEntity($clientId);
        if (!$client || ($usedBasic && !$client->isConfidential()) || (!$usedBasic && $client->isConfidential()) || !$repository->validateClient($clientId, $secret, $requireGrant ? $grant : null)) throw new DomainException('invalid_client');
        return [$client, $grant];
    }

    private function requestedScopeIds(ClientEntity $client, string $scope): array
    {
        $names = array_values(array_filter(preg_split('/\s+/', trim($scope)) ?: []));
        if ($names === []) return [];
        if (array_diff($names, $client->allowedScopes) !== []) throw new DomainException('invalid_scope');
        return (new OAuthScopeService())->resolveIds($client->tenantId, $names);
    }

    private function scopeNames(array $ids): array { return $ids === [] ? [] : (new \app\common\model\identity\OAuthScope())->whereIn('id', $ids)->column('name'); }
    private function appendQuery(string $uri, array $parameters): string { return $uri . (str_contains($uri, '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986); }
    private function redirectError(string $uri, string $error, ?string $state): Response { $params = ['error' => $error]; if ($state !== null) $params['state'] = $state; return redirect($this->appendQuery($uri, $params)); }
    private function error(string $error, int $status): Json
    {
        $response = $this->secureJson(['error' => $error], $status);
        if ($error === 'invalid_client') $response->header(['WWW-Authenticate' => 'Basic realm="identity"']);
        if ($error === 'invalid_token') $response->header(['WWW-Authenticate' => 'Bearer realm="identity", error="invalid_token"']);
        return $response;
    }
    private function securityHeaders(): array { return ['Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'", 'Cache-Control' => 'no-store', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY']; }
    private function secureJson(array $data, int $status = 200): Json { return json($data, $status)->header($this->securityHeaders()); }
}
