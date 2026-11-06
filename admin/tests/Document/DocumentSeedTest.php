<?php

declare(strict_types=1);

namespace Tests\Document;

use app\document\service\DocumentService;
use app\document\service\DocumentVersionService;
use app\document\service\SeedService;
use support\Db;

final class DocumentSeedTest extends DocumentTestCase
{
    private function seed(): void
    {
        (new SeedService(dirname(__DIR__, 2) . '/plugins/document/resources/seed'))->run();
    }

    public function test_seed_creates_four_scope_roots_and_the_starter_document(): void
    {
        $this->seed();
        $roots = Db::table('document_categories')->where('parent_id', 0)->orderBy('sort_order')->pluck('scope')->all();
        self::assertSame(['framework', 'plugin_dev', 'official_plugin', 'third_party'], $roots);
        $doc = Db::table('documents')->where('slug', 'plugin-development-quick-start')->first();
        self::assertNotNull($doc);
        self::assertSame('seed', $doc->source);
        self::assertSame('published', $doc->status, '内置文档以已发布状态提供');
        self::assertSame(1, Db::table('document_revisions')->where('document_id', $doc->id)->count());
    }

    public function test_reseeding_is_idempotent_and_never_overwrites_admin_edits(): void
    {
        $this->seed();
        $id = (int) Db::table('documents')->where('slug', 'plugin-development-quick-start')->value('id');
        $documents = new DocumentService();
        (new DocumentVersionService())->saveDraft($id, $this->doc('管理员改写的内容'), $documents->find($id)['lockVersion']);
        $documents->updateMeta($id, ['title' => '管理员改过的标题']);

        $this->seed(); // 插件升级或再次启用时重复执行

        self::assertSame(1, Db::table('documents')->where('slug', 'plugin-development-quick-start')->count());
        self::assertSame(10, Db::table('document_categories')->count());
        $after = $documents->find($id);
        self::assertSame('管理员改过的标题', $after['title']);
        self::assertSame($this->doc('管理员改写的内容'), $after['content']);
    }

    public function test_seed_skips_a_slug_that_an_admin_already_used(): void
    {
        (new DocumentService())->create(['title' => '我自己的', 'slug' => 'plugin-development-quick-start', 'scope' => 'framework']);
        $this->seed();
        self::assertSame('我自己的', Db::table('documents')->where('slug', 'plugin-development-quick-start')->value('title'));
    }

    public function test_every_seed_document_passes_the_content_whitelist(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/plugins/document/resources/seed/documents/*.json') as $file) {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($data['content'], \app\document\service\ContentValidator::clean($data['content']), basename($file));
        }
    }
}
