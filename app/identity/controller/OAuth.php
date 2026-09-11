<?php

declare(strict_types=1);

namespace app\identity\controller;

use app\common\model\identity\IdentityUser;
use app\common\service\identity\OAuthScopeService;
use app\identity\oauth\AuthorizationRequestValidator;
use app\identity\oauth\entity\ClientEntity;
use app\identity\oauth\repository\ClientRepository;
use app\identity\service\AuthorizationTransactionService;
use app\identity\service\IdentitySessionResolverInterface;
use app\identity\service\IdTokenService;
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
            return $this->secureJson(['transaction_id' => $transactionId, 'interaction_url' => '/identity/authorize/placeholder?transaction_id=' . rawurlencode($transactionId)]);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 400);
        }
    }

    public function decision(Request $request): Response
    {
        $identity = $this->resolveIdentity($request);
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
        try { $this->authenticateClient($request, false); } catch (DomainException) { return $this->error('invalid_client', 401); }
        (new OpaqueTokenService())->revoke((string) $request->post('token', ''));
        return $this->secureJson([]);
    }

    public function introspect(Request $request): Json
    {
        try {
            [$client] = $this->authenticateClient($request, false);
            if (!$client->isConfidential()) throw new DomainException('invalid_client');
            $result = (new OpaqueTokenService())->inspect((string) $request->post('token', ''));
            unset($result['_record']);
            return $this->secureJson(array_filter($result, static fn ($value): bool => $value !== null));
        } catch (DomainException) {
            return $this->error('invalid_client', 401);
        }
    }

    public function userinfo(Request $request): Json
    {
        $plain = preg_replace('/^Bearer\s+/i', '', (string) $request->header('authorization', '')) ?? '';
        $token = (new OpaqueTokenService())->inspect($plain);
        $scopes = explode(' ', (string) ($token['scope'] ?? ''));
        if (!($token['active'] ?? false) || !in_array('openid', $scopes, true) || empty($token['_record']['user_id'])) return $this->error('invalid_token', 401);
        $record = $token['_record'];
        $user = IdentityUser::forTenant((int) $record['tenant_id'])->where('id', (int) $record['user_id'])->where('status', 1)->find();
        if (!$user) return $this->error('invalid_token', 401);
        $claims = ['sub' => (string) $user->public_id];
        if (in_array('profile', $scopes, true)) $claims += ['name' => (string) $user->display_name, 'preferred_username' => (string) $user->username, 'locale' => (string) $user->locale];
        if (in_array('email', $scopes, true) && $user->email) $claims['email'] = (string) $user->email;
        if (in_array('phone', $scopes, true) && $user->mobile) $claims['phone_number'] = (string) $user->mobile;
        return $this->secureJson($claims);
    }

    private function authorizationCode(Request $request, ClientEntity $client, array $scopeIds): array
    {
        $code = (new AuthorizationTransactionService())->consumeCode((string) $request->post('code', ''), $client->databaseId, (string) $request->post('redirect_uri', ''), (string) $request->post('code_verifier', ''));
        $original = (new \app\common\model\identity\OAuthAuthorizationScope())->where('authorization_id', $code['authorization_id'])->column('scope_id');
        if ($scopeIds !== [] && array_diff($scopeIds, $original) !== []) throw new DomainException('invalid_scope');
        $granted = $scopeIds ?: $original;
        $tokens = (new OpaqueTokenService())->issue($client->tenantId, $client->databaseId, (int) $code['user_id'], $granted, (int) $code['authorization_id']);
        if (in_array('openid', $this->scopeNames($granted), true)) {
            $authorization = \app\common\model\identity\OAuthAuthorization::forTenant($client->tenantId)->where('id', $code['authorization_id'])->find();
            if (!$authorization) throw new DomainException('invalid_grant');
            $tokens['id_token'] = (new IdTokenService())->issue($client->tenantId, (int) $code['user_id'], $client->getIdentifier(), strtotime((string) $authorization->auth_time), $code['nonce'] ?: null, (string) $authorization->session_id);
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
        if (str_starts_with($authorization, 'Basic ')) {
            $decoded = base64_decode(substr($authorization, 6), true);
            if (!is_string($decoded) || !str_contains($decoded, ':')) throw new DomainException('invalid_client');
            [$clientId, $secret] = explode(':', $decoded, 2);
        }
        $repository = new ClientRepository(); $client = $repository->getClientEntity($clientId);
        if (!$client || !$repository->validateClient($clientId, $secret, $requireGrant ? $grant : null)) throw new DomainException('invalid_client');
        return [$client, $grant];
    }

    private function requestedScopeIds(ClientEntity $client, string $scope): array
    {
        $names = array_values(array_filter(preg_split('/\s+/', trim($scope)) ?: []));
        if ($names === []) return [];
        if (array_diff($names, $client->allowedScopes) !== []) throw new DomainException('invalid_scope');
        return (new OAuthScopeService())->resolveIds($client->tenantId, $names);
    }

    private function resolveIdentity(Request $request): ?array
    {
        $class = (string) config('oauth.identity_session_resolver', '');
        if ($class === '' || !class_exists($class)) return null;
        $resolver = app()->make($class);
        return $resolver instanceof IdentitySessionResolverInterface ? $resolver->resolve($request) : null;
    }

    private function scopeNames(array $ids): array { return $ids === [] ? [] : (new \app\common\model\identity\OAuthScope())->whereIn('id', $ids)->column('name'); }
    private function appendQuery(string $uri, array $parameters): string { return $uri . (str_contains($uri, '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986); }
    private function redirectError(string $uri, string $error, ?string $state): Response { $params = ['error' => $error]; if ($state !== null) $params['state'] = $state; return redirect($this->appendQuery($uri, $params)); }
    private function error(string $error, int $status): Json { return $this->secureJson(['error' => $error], $status); }
    private function secureJson(array $data, int $status = 200): Json { return json($data, $status)->header(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY']); }
}
