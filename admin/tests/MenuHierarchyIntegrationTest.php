<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class MenuHierarchyIntegrationTest extends TestCase
{
    public function test_function_modules_expose_second_level_menus_without_changing_page_urls(): void
    {
        (new MigrationService())->run();

        $roots = Db::table('admin_menu')->where('app_name', 'admin')->where('pid', 0)->where('status', 1)->get();
        $rootNames = $roots->pluck('name')->all();
        foreach (['系统管理', '用户管理', '插件管理'] as $name) {
            self::assertContains($name, $rootNames);
        }
        foreach ($roots as $root) {
            parse_str((string) $root->query, $meta);
            self::assertSame('M', $meta['type'] ?? null, '一级菜单必须是目录：' . $root->name);
            self::assertGreaterThan(0, Db::table('admin_menu')->where('pid', $root->id)->where('status', 1)->count(), '一级目录必须有二级菜单：' . $root->name);
        }

        foreach ([
            ['parent' => '插件管理', 'href' => '/plugin', 'name' => '插件中心', 'required' => true],
            ['parent' => '插件管理', 'href' => '/plugin/etst/items', 'name' => 'etst', 'required' => false],
            ['parent' => '开发工具', 'href' => '/business/i18n-demo', 'name' => 'i18n验收演示', 'required' => false],
            ['parent' => '身份与应用', 'href' => '/applications', 'name' => '应用中心', 'required' => false],
        ] as $expected) {
            $parentId = (int) Db::table('admin_menu')->where('app_name', 'admin')->where('pid', 0)->where('name', $expected['parent'])->value('id');
            if ($parentId === 0) {
                if (!$expected['required']) continue;
                self::fail($expected['parent'] . ' 父菜单不存在');
            }
            $menu = Db::table('admin_menu')->where('app_name', 'admin')->where('pid', $parentId)->where('href', $expected['href'])->first();
            if ($menu === null && !$expected['required']) continue;
            self::assertNotNull($menu, $expected['name'] . ' 应归入 ' . $expected['parent']);
            self::assertSame($expected['name'], $menu->name);
        }
    }
}
