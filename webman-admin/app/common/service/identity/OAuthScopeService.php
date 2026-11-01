<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\OAuthScope;
use support\validation\ValidationException;

final class OAuthScopeService
{
    private const MACHINE_FORBIDDEN = ['openid', 'profile', 'email', 'phone', 'offline_access'];

    public static function validateMachineScopes(array $scopes): array
    {
        $normalized = self::normalizeNames($scopes);
        if (array_intersect($normalized, self::MACHINE_FORBIDDEN) !== []) {
            throw new ValidationException('machine client 禁止请求用户身份 scope');
        }
        return $normalized;
    }

    public function resolveIds(int $tenantId, array $names): array
    {
        $names = self::normalizeNames($names);
        if ($names === []) return [];
        $rows = OAuthScope::forTenant($tenantId)->whereIn('name', $names)->where('status', 1)->get()->toArray();
        if (count($rows) !== count($names)) throw new ValidationException('包含不存在或已禁用的 OAuth scope');
        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    private static function normalizeNames(array $names): array
    {
        if (!array_is_list($names) || count(array_filter($names, 'is_string')) !== count($names)) {
            throw new ValidationException('OAuth scope 必须是字符串列表');
        }
        $normalized = array_values(array_unique(array_map('trim', $names)));
        foreach ($normalized as $name) {
            if (preg_match('/^[a-z][a-z0-9._:-]{0,127}$/', $name) !== 1) throw new ValidationException('OAuth scope 名称无效');
        }
        sort($normalized);
        return $normalized;
    }
}
