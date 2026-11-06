<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\model\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermissionAliasesTest extends TestCase
{
    public static function pluginDevelopmentActions(): array
    {
        return [
            ['previewcreate', 'create-preview'],
            ['create', 'create'],
            ['validate', 'validate'],
            ['package', 'package'],
            ['packagedownload', 'download'],
            ['options', 'options'],
        ];
    }

    #[DataProvider('pluginDevelopmentActions')]
    public function test_plugin_development_route_grants_only_its_corresponding_frontend_action(string $route, string $action): void
    {
        $code = 'development.devplugin:' . $route;
        self::assertEqualsCanonicalizing(
            [$code, 'development:plugin:' . $action],
            Permission::webPermissions([$code], false)
        );
    }
}
