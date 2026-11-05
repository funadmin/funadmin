<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\model\Admin;
use app\admin\authorization\model\AdminDepartment;
use app\admin\authorization\model\CasbinRule;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\Permission;
use app\admin\authorization\controller\SystemMenu;
use app\admin\authorization\controller\SystemPermission;
use app\admin\authorization\controller\SystemRole;
use app\admin\authorization\controller\SystemAdmin;
use PHPUnit\Framework\TestCase;
use support\Db;

final class AuthorizationLayoutContractTest extends TestCase
{
    public function test_plugin_permission_middleware_is_owned_by_the_plugin_module(): void
    {
        $class = 'app\\admin\\plugin\\middleware\\CheckPluginPermission';
        self::assertTrue(class_exists($class));
        self::assertSame(realpath(__DIR__ . '/../app/admin/plugin/middleware/CheckPluginPermission.php'), (new \ReflectionClass($class))->getFileName());
        self::assertFalse(class_exists('app\\admin\\middleware\\CheckPluginPermission'));
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/middleware/CheckPluginPermission.php');
        foreach ([
            ...glob(__DIR__ . '/../app/admin/plugins/market/controller/*.php'),
            __DIR__ . '/../app/common/plugin/sdk/PluginRoute.php',
        ] as $file) {
            $source = file_get_contents($file);
            self::assertStringContainsString('use ' . $class . ';', $source);
            self::assertStringNotContainsString('use app\\admin\\middleware\\CheckPluginPermission;', $source);
        }
    }

    public function test_plugin_authorization_injects_the_authorization_module_service(): void
    {
        $class = \app\admin\plugin\service\PluginPermissionAuthorizationService::class;
        $parameter = (new \ReflectionClass($class))->getConstructor()->getParameters()[0];
        self::assertSame(\app\admin\authorization\service\CasbinService::class, $parameter->getType()->getName());
        $casbin = new \app\admin\authorization\service\CasbinService();
        $service = new $class($casbin);
        self::assertFalse($service->authorize(0, 'example', 'example:entry:index'));
        self::assertFalse($service->authorize(2, 'example', 'other:entry:index'));
    }

    public function test_market_plugin_controllers_use_native_webman_route_attributes(): void
    {
        foreach (glob(__DIR__ . '/../app/admin/plugins/market/controller/*.php') ?: [] as $file) {
            $source = file_get_contents($file);
            self::assertStringContainsString('use support\\annotation\\route\\RouteGroup;', $source, $file);
            self::assertStringContainsString('use support\\Response;', $source, $file);
            self::assertStringNotContainsString('think\\annotation\\route', $source, $file);
            self::assertStringNotContainsString('use think\\Response;', $source, $file);
        }
    }

    public function test_market_controller_middleware_entries_are_native_webman_classes(): void
    {
        foreach (['Category', 'Plugin', 'Order', 'Grant', 'Setting', 'Stat'] as $name) {
            $class = 'app\\admin\\plugins\\market\\controller\\' . $name . 'Controller';
            $entries = (new \ReflectionClass($class))->getDefaultProperties()['middleware'];
            foreach ($entries as $entry) self::assertTrue(is_string($entry) && is_subclass_of($entry, \Webman\MiddlewareInterface::class), $class . ' 中间件必须遵循 Webman 契约');
        }
    }

    public function test_market_controllers_use_native_upload_body_and_file_responses(): void
    {
        foreach (glob(app_path('admin/plugins/market/controller/*.php')) ?: [] as $file) {
            $source = file_get_contents($file);
            self::assertStringNotContainsString('->put()', $source, $file);
            self::assertStringNotContainsString('->getOriginalExtension()', $source, $file);
            self::assertStringNotContainsString('return download(', $source, $file);
        }
    }

    public function test_models_use_logical_names_and_connection_prefixes(): void
    {
        $models = [
            Admin::class => 'admin',
            AdminDepartment::class => 'admin_department',
            Permission::class => 'permission',
            AuthGroup::class => 'auth_group',
            AdminMenu::class => 'admin_menu',
            CasbinRule::class => 'casbin_rule',
        ];
        $connection = Db::connection();
        $original = $connection->getTablePrefix();
        try {
            foreach (['fun_', 'custom_', ''] as $prefix) {
                $connection->setTablePrefix($prefix);
                foreach ($models as $class => $table) {
                    $model = new $class();
                    self::assertSame($table, $model->getTable());
                    self::assertStringContainsString($connection->getQueryGrammar()->wrapTable($table), $model->newQuery()->toSql());
                }
            }
        } finally {
            $connection->setTablePrefix($original);
        }
    }

    public function test_database_config_accepts_default_custom_and_empty_prefixes(): void
    {
        $original = getenv('DB_PREFIX');
        try {
            foreach ([false, 'custom_', ''] as $prefix) {
                putenv($prefix === false ? 'DB_PREFIX' : 'DB_PREFIX=' . $prefix);
                $config = require dirname(__DIR__) . '/config/database.php';
                foreach (['mysql', 'sqlite'] as $driver) {
                    self::assertSame($prefix === false ? 'fun_' : $prefix, $config['connections'][$driver]['prefix']);
                }
            }
        } finally {
            putenv($original === false ? 'DB_PREFIX' : 'DB_PREFIX=' . $original);
        }
    }

    public function test_authorization_controllers_are_loaded_only_from_the_business_module(): void
    {
        self::assertSame('permission', (new Permission())->getTable());
        self::assertSame('auth_group', (new AuthGroup())->getTable());
        self::assertSame('admin_menu', (new AdminMenu())->getTable());
        self::assertTrue(class_exists(SystemAdmin::class));
        foreach ([SystemMenu::class, SystemPermission::class, SystemRole::class] as $class) {
            self::assertTrue(class_exists($class), '授权控制器应归入业务模块：' . $class);
            $name = (new \ReflectionClass($class))->getShortName();
            self::assertSame(realpath(__DIR__ . '/../app/admin/authorization/controller/' . $name . '.php'), (new \ReflectionClass($class))->getFileName());
            self::assertFalse(class_exists('app\\admin\\controller\\authorization\\' . $name));
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/authorization/' . $name . '.php');
            $reflection = new \ReflectionClass($class);
            $groups = $reflection->getAttributes(\support\annotation\route\RouteGroup::class);
            $path = 'system/' . strtolower(substr($name, 6));
            if ($groups !== []) {
                self::assertCount(1, $groups);
                self::assertSame('/admin/' . $path, $groups[0]->newInstance()->prefix);
            } else {
                $groups = $reflection->getAttributes('think\\annotation\\route\\Group');
                self::assertCount(1, $groups);
                self::assertSame($path, $groups[0]->getArguments()[0]);
            }
        }
    }
}
