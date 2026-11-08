<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\PermissionCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermissionCodeTest extends TestCase
{
    public static function derivations(): array
    {
        return [
            'host system controller' => ['app\\admin\\system\\controller\\SystemConfig', 'index', 'system:config:index'],
            'host camelCase action' => ['app\\admin\\authorization\\controller\\SystemAdmin', 'resetPassword', 'authorization:admin:reset-password'],
            'plugin Controller suffix' => ['app\\admin\\plugins\\market\\controller\\OrderController', 'close', 'market:order:close'],
            'plugin camelCase action' => ['app\\admin\\plugins\\cms\\controller\\Sites', 'saveSettings', 'cms:sites:save-settings'],
            'host same-named resource' => ['app\\admin\\ai\\controller\\Ai', 'conversationGroupDelete', 'ai:ai:conversation-group-delete'],
            'host multi-word resource' => ['app\\admin\\identity\\controller\\EnterpriseApplication', 'delete', 'identity:enterprise-application:delete'],
            'acronym class' => ['app\\admin\\identity\\controller\\OAuthClient', 'revokeSecret', 'identity:o-auth-client:revoke-secret'],
            'plugin controller in a sub-namespace' => ['app\\admin\\plugins\\shop\\controller\\admin\\OrderItemController', 'close', 'shop:admin-order-item:close'],
            'host controller in a sub-namespace' => ['app\\admin\\system\\controller\\log\\SystemOperationLog', 'index', 'system:log-operation-log:index'],
            'business module' => ['app\\admin\\business\\shop\\controller\\OrderItemController', 'restoreMany', 'business:order-item:restore-many'],
            'business module is not the development business controller' => ['app\\admin\\development\\controller\\Business', 'generate', 'development:business:generate'],
        ];
    }

    #[DataProvider('derivations')]
    public function test_derives_module_resource_action_in_kebab_case(string $controller, string $action, string $expected): void
    {
        self::assertSame($expected, PermissionCode::derive($controller, $action));
    }

    public function test_non_admin_targets_cannot_be_derived(): void
    {
        self::assertNull(PermissionCode::derive('app\\api\\controller\\v2\\Member', 'show'));
        self::assertNull(PermissionCode::derive('app\\admin\\controller\\base\\Something', 'index'));
        // 不再识别旧的扁平生成布局。
        self::assertNull(PermissionCode::derive('app\\admin\\controller\\generated\\LayoutExampleController', 'index'));
        self::assertNull(PermissionCode::derive('', ''));
    }

    public function test_code_maps_to_casbin_object_and_action(): void
    {
        self::assertSame(['obj' => 'market/order', 'act' => 'close'], PermissionCode::resource('market:order:close'));
        self::assertSame(['obj' => 'authorization/admin', 'act' => 'reset-password'], PermissionCode::resource('authorization:admin:reset-password'));
    }

    public function test_every_registered_admin_route_derives_a_valid_unique_code(): void
    {
        \Webman\Route::load([config_path()]);
        $codes = [];
        foreach (\Webman\Route::getRoutes() as $route) {
            $callback = $route->getCallback();
            if (!is_array($callback) || !str_starts_with((string) $callback[0], 'app\\admin\\')) continue;
            $code = PermissionCode::derive((string) $callback[0], (string) $callback[1]);
            if ($code === null) continue;
            self::assertMatchesRegularExpression('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/', $code);
            $codes[$code][$callback[0] . '::' . $callback[1]] = true;
        }
        $shared = array_filter($codes, static fn (array $methods): bool => count($methods) > 1);
        self::assertSame([], array_map('array_keys', $shared), '每个方法必须得到唯一权限码');
        self::assertGreaterThan(300, count($codes));
    }
}
