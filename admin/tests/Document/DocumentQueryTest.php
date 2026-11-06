<?php

declare(strict_types=1);

namespace Tests\Document;

use app\document\service\DocumentCategoryService;
use app\document\service\DocumentService;

final class DocumentQueryTest extends DocumentTestCase
{
    private DocumentService $documents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documents = new DocumentService();
    }

    private function make(array $overrides): array
    {
        return $this->documents->create(array_merge([
            'title' => '文档', 'slug' => 'doc-' . bin2hex(random_bytes(4)), 'scope' => 'framework',
            'type' => 'guide', 'content' => $this->doc(),
        ], $overrides));
    }

    public function test_create_returns_camel_case_dto_with_draft_status_and_plain_text(): void
    {
        $doc = $this->make(['title' => '权限模型', 'slug' => 'rbac', 'pluginCode' => '', 'appliesTo' => 'v2.0', 'content' => $this->doc('Casbin 策略说明')]);
        self::assertSame('权限模型', $doc['title']);
        self::assertSame('rbac', $doc['slug']);
        self::assertSame('draft', $doc['status']);
        self::assertSame('v2.0', $doc['appliesTo']);
        self::assertSame(1, $doc['lockVersion']);
        self::assertSame(0, $doc['currentRevisionNo']);
        self::assertSame($this->doc('Casbin 策略说明'), $doc['content']);
        self::assertStringContainsString('Casbin 策略说明', $this->documents->find($doc['id'])['plainText']);
    }

    public function test_rejects_duplicate_slug_and_unknown_scope_type(): void
    {
        $this->make(['slug' => 'same']);
        foreach ([['slug' => 'same'], ['scope' => 'marketing'], ['type' => 'novel']] as $bad) {
            try {
                $this->make($bad);
                self::fail('应拒绝：' . json_encode($bad, JSON_UNESCAPED_UNICODE));
            } catch (\InvalidArgumentException $e) {
                self::assertContains($e->getCode(), [409, 422]);
            }
        }
    }

    public function test_list_filters_by_scope_plugin_type_status_category_and_keyword(): void
    {
        $category = (new DocumentCategoryService())->create(['scope' => 'official_plugin', 'name' => '官方插件', 'slug' => 'official']);
        $this->make(['title' => '框架部署', 'scope' => 'framework', 'type' => 'deployment']);
        $mail = $this->make(['title' => '邮箱插件指南', 'scope' => 'official_plugin', 'pluginCode' => 'mail', 'type' => 'guide', 'categoryIds' => [$category['id']], 'content' => $this->doc('IMAP 同步')]);
        $this->make(['title' => '社区插件', 'scope' => 'third_party', 'pluginCode' => 'foo']);

        $titles = fn (array $q) => array_column($this->documents->list($q)['list'], 'title');
        self::assertSame(['邮箱插件指南'], $titles(['scope' => 'official_plugin']));
        self::assertSame(['邮箱插件指南'], $titles(['pluginCode' => 'mail']));
        self::assertSame(['框架部署'], $titles(['type' => 'deployment']));
        self::assertSame(['邮箱插件指南'], $titles(['categoryId' => $category['id']]));
        self::assertSame(['邮箱插件指南'], $titles(['keyword' => 'IMAP']), '关键字应命中正文纯文本');
        self::assertSame(3, $this->documents->list([])['total']);
        self::assertSame([$category['id']], $this->documents->find($mail['id'])['categoryIds']);
    }

    public function test_soft_deleted_documents_leave_the_default_list(): void
    {
        $doc = $this->make(['title' => '待删除']);
        $this->documents->delete($doc['id']);
        self::assertSame(0, $this->documents->list([])['total']);
        $this->expectException(\InvalidArgumentException::class);
        $this->documents->find($doc['id']);
    }

    public function test_keyword_wildcards_are_matched_literally(): void
    {
        $this->make(['title' => '普通文档']);
        self::assertSame(0, $this->documents->list(['keyword' => '%'])['total']);
    }
}
