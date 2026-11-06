<?php

declare(strict_types=1);

namespace Tests\Document;

use app\document\service\DocumentCategoryService;

final class DocumentCategoryServiceTest extends DocumentTestCase
{
    public function test_tree_nests_children_and_orders_by_sort_order(): void
    {
        $service = new DocumentCategoryService();
        $root = $service->create(['scope' => 'framework', 'name' => '主框架', 'slug' => 'framework', 'sortOrder' => 10]);
        $service->create(['scope' => 'framework', 'name' => '后端', 'slug' => 'framework-backend', 'parentId' => $root['id'], 'sortOrder' => 20]);
        $service->create(['scope' => 'framework', 'name' => '架构', 'slug' => 'framework-arch', 'parentId' => $root['id'], 'sortOrder' => 10]);
        $service->create(['scope' => 'plugin_dev', 'name' => '插件开发', 'slug' => 'plugin-dev', 'sortOrder' => 20]);

        $tree = $service->tree();
        self::assertSame(['framework', 'plugin-dev'], array_column($tree, 'slug'));
        self::assertSame(['framework-arch', 'framework-backend'], array_column($tree[0]['children'], 'slug'));
    }

    public function test_rejects_unknown_scope_and_duplicate_slug(): void
    {
        $service = new DocumentCategoryService();
        $service->create(['scope' => 'framework', 'name' => '主框架', 'slug' => 'framework']);
        foreach ([
            ['scope' => 'marketing', 'name' => 'x', 'slug' => 'x'],
            ['scope' => 'framework', 'name' => '重复', 'slug' => 'framework'],
        ] as $payload) {
            try {
                $service->create($payload);
                self::fail('应拒绝：' . json_encode($payload, JSON_UNESCAPED_UNICODE));
            } catch (\InvalidArgumentException $e) {
                self::assertSame(422, $e->getCode());
            }
        }
    }

    public function test_parent_must_share_the_scope_and_cannot_be_itself(): void
    {
        $service = new DocumentCategoryService();
        $framework = $service->create(['scope' => 'framework', 'name' => '主框架', 'slug' => 'framework']);
        $this->expectException(\InvalidArgumentException::class);
        $service->create(['scope' => 'plugin_dev', 'name' => '跨范围', 'slug' => 'cross', 'parentId' => $framework['id']]);
    }

    public function test_delete_refuses_a_category_with_children_or_documents(): void
    {
        $service = new DocumentCategoryService();
        $root = $service->create(['scope' => 'framework', 'name' => '主框架', 'slug' => 'framework']);
        $service->create(['scope' => 'framework', 'name' => '子目录', 'slug' => 'child', 'parentId' => $root['id']]);
        try {
            $service->delete($root['id']);
            self::fail('非空目录不能删除');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(409, $e->getCode());
        }
        $leaf = $service->create(['scope' => 'framework', 'name' => '空目录', 'slug' => 'empty']);
        $service->delete($leaf['id']);
        self::assertNotContains('empty', array_column($service->tree(), 'slug'));
    }
}
