<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\ManagementService;
use app\admin\authorization\controller\Management;
use PHPUnit\Framework\TestCase;

final class ManagementLayoutContractTest extends TestCase
{
    public function test_management_uses_authorization_module_paths(): void
    {
        self::assertTrue(class_exists(ManagementService::class));
        self::assertTrue(method_exists(Management::class, 'listing'));
        self::assertTrue(method_exists(Management::class, 'create'));
        self::assertTrue(method_exists(Management::class, 'update'));
        self::assertTrue(method_exists(Management::class, 'remove'));
        self::assertSame('admin', ManagementService::tableFor('users'));
        self::assertSame('auth_group', ManagementService::tableFor('roles'));
        self::assertSame('permission', ManagementService::tableFor('permissions'));
        self::assertSame('admin_menu', ManagementService::tableFor('menus'));
    }
}
