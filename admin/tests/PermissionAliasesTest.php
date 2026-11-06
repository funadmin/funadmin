<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\model\Permission;
use PHPUnit\Framework\TestCase;

/** 前后端同码：用户信息直接返回后端权限码，不再有任何别名或对照映射。 */
final class PermissionAliasesTest extends TestCase
{
    public function test_web_permissions_are_the_backend_codes_without_aliases(): void
    {
        $codes = ['plugin:dev-plugin:create-preview', 'authorization:role:index', 'development:business:generate'];
        self::assertSame($codes, Permission::webPermissions($codes, false));
        self::assertSame(['*'], Permission::webPermissions($codes, true));
        self::assertSame(['authorization:role:index'], Permission::webPermissions(['Authorization:Role:Index', 'authorization:role:index', ''], false));
    }
}
