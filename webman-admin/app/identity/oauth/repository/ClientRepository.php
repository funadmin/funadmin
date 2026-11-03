<?php

declare(strict_types=1);

namespace app\identity\oauth\repository;

use app\common\model\identity\OAuthClient;
use app\common\service\identity\ClientSecretService;
use app\identity\oauth\entity\ClientEntity;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

final class ClientRepository implements ClientRepositoryInterface
{
    public function __construct(private readonly ClientSecretService $secrets = new ClientSecretService())
    {
    }

    public function getClientEntity($clientIdentifier): ?ClientEntity
    {
        $client = (new OAuthClient())->where('client_id', (string) $clientIdentifier)->where('status', 'active')->whereNull('deleted_at')->first();
        if (!$client) {
            return null;
        }
        $tenantId = (int) $client->tenant_id;
        $databaseId = (int) $client->id;
        $redirects = (new \app\common\model\identity\RedirectUri())->where('tenant_id', $tenantId)->where('client_id', $databaseId)->where('uri_type', 'authorization_callback')->where('status', 1)->whereNull('deleted_at')->pluck('redirect_uri')->all();
        $grants = (new \app\common\model\identity\ClientGrant())->where('tenant_id', $tenantId)->where('client_id', $databaseId)->whereNull('deleted_at')->pluck('grant_type')->all();
        $scopes = \support\Db::table('client_scope as cs')->join('scope as s', static function ($join): void {
            $join->on('s.id', '=', 'cs.scope_id')->on('s.tenant_id', '=', 'cs.tenant_id');
        })->where('cs.tenant_id', $tenantId)->where('cs.client_id', $databaseId)->whereNull('cs.deleted_at')->whereNull('s.deleted_at')->where('s.status', 1)->pluck('s.name')->all();

        return new ClientEntity((string) $client->client_id, (string) $client->name, $redirects, $client->client_type !== 'public', $tenantId, $databaseId, $client->client_type, $grants, $scopes);
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        $client = $this->getClientEntity((string) $clientIdentifier);
        if (!$client || ($grantType !== null && !in_array((string) $grantType, $client->grants, true))) {
            return false;
        }
        if ($client->clientType === 'public') {
            return $clientSecret === null || $clientSecret === '';
        }

        return is_string($clientSecret) && $this->secrets->verify($client->tenantId, $client->databaseId, $clientSecret);
    }
}
