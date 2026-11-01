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
            self::assertStringContainsString("#[Group('system/" . strtolower(substr($name, 6)) . "')]", file_get_contents((new \ReflectionClass($class))->getFileName()));
        }
    }
}
