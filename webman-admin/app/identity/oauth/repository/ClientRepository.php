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
        $client = (new OAuthClient())->where('client_id', (string) $clientIdentifier)->where('status', 'active')->whereNull('deleted_at')->find();
        if (!$client) {
            return null;
        }
        $tenantId = (int) $client->tenant_id;
        $databaseId = (int) $client->id;
        $redirects = (new \app\common\model\identity\RedirectUri())->where('tenant_id', $tenantId)->where('client_id', $databaseId)->where('uri_type', 'authorization_callback')->where('status', 1)->whereNull('deleted_at')->column('redirect_uri');
        $grants = (new \app\common\model\identity\ClientGrant())->where('tenant_id', $tenantId)->where('client_id', $databaseId)->whereNull('deleted_at')->column('grant_type');
        $scopes = (new \app\common\model\identity\ClientScope())->alias('cs')->join('scope s', 's.id=cs.scope_id AND s.tenant_id=cs.tenant_id')->where('cs.tenant_id', $tenantId)->where('cs.client_id', $databaseId)->whereNull('cs.deleted_at')->where('s.status', 1)->column('s.name');

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
