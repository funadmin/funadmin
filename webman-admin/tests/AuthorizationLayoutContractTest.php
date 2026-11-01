<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\model\Admin;
use app\admin\authorization\model\AdminDepartment;
use app\admin\authorization\model\CasbinRule;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\Permission;
use app\admin\controller\authorization\SystemMenu;
use app\admin\controller\authorization\SystemPermission;
use app\admin\controller\authorization\SystemRole;
use app\admin\controller\system\SystemAdmin;
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

    public function test_tp8_authorization_tree_classes_are_loadable_from_mirrored_paths(): void
    {
        self::assertSame('permission', (new Permission())->getTable());
        self::assertSame('auth_group', (new AuthGroup())->getTable());
        self::assertSame('admin_menu', (new AdminMenu())->getTable());
        self::assertTrue(class_exists(SystemAdmin::class));
        self::assertTrue(class_exists(SystemRole::class));
        self::assertTrue(class_exists(SystemPermission::class));
        self::assertTrue(class_exists(SystemMenu::class));
    }
}
