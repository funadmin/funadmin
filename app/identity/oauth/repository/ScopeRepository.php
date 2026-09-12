<?php

declare(strict_types=1);

namespace app\identity\oauth\repository;

use app\common\model\identity\OAuthScope;
use app\common\service\identity\OAuthScopeService;
use app\identity\oauth\entity\ClientEntity;
use InvalidArgumentException;
use app\identity\oauth\entity\ScopeEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

final class ScopeRepository implements ScopeRepositoryInterface
{
    public function getScopeEntityByIdentifier($identifier): ?ScopeEntity
    {
        $scope = (new OAuthScope())->where('name', (string) $identifier)->where('status', 1)->whereNull('deleted_at')->limit(2)->select();
        if ($scope->count() > 1) {
            throw new InvalidArgumentException('OAuth scope identifier 跨租户不唯一，必须通过 client tenant 解析');
        }
        $record = $scope->first();
        return $record ? new ScopeEntity((string) $record->name, (int) $record->id) : null;
    }

    public function finalizeScopes(array $scopes, $grantType, ClientEntityInterface $clientEntity, $userIdentifier = null): array
    {
        if (!$clientEntity instanceof ClientEntity) {
            return [];
        }
        $requested = array_map(static fn ($scope): string => (string) $scope->getIdentifier(), $scopes);
        if (array_diff($requested, $clientEntity->allowedScopes) !== []) {
            return [];
        }
        if ($grantType === 'client_credentials') {
            try {
                OAuthScopeService::validateMachineScopes($requested);
            } catch (\InvalidArgumentException) {
                return [];
            }
        }

        return $scopes;
    }
}
