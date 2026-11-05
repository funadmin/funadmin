<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\ManagementService;
use app\admin\authorization\controller\Management;
use PHPUnit\Framework\TestCase;

final class ManagementLayoutContractTest extends TestCase
{
    public function test_prototype_implementation_is_removed_and_native_controllers_remain(): void
    {
        self::assertFalse(class_exists(ManagementService::class));
        self::assertFalse(class_exists(Management::class));
        foreach (['SystemAdmin', 'SystemRole', 'SystemPermission', 'SystemMenu'] as $controller) {
            self::assertTrue(class_exists('app\\admin\\authorization\\controller\\' . $controller));
        }
        $installer = file_get_contents(base_path() . '/app/common/service/install/InstallSupport.php');
        self::assertStringNotContainsString('/admin/management', $installer);
        self::assertStringNotContainsString('system:management:view', $installer);
    }
}
