<?php

declare(strict_types=1);

namespace Tests\Document;

use app\document\service\DocumentService;
use app\document\service\DocumentVersionService;
use app\document\service\PublicDocumentService;
use support\Db;

final class PublicDocumentTest extends DocumentTestCase
{
    private function published(string $slug, array $extra = []): array
    {
        $row = (new DocumentService())->create($extra + ['title'=>'公开标题 '.$slug, 'summary'=>'发布摘要', 'slug'=>$slug, 'scope'=>'framework', 'visibility'=>'public', 'content'=>$this->doc('发布正文')]);
        return (new DocumentVersionService())->publish($row['id'], '', 1);
    }

    public function test_only_public_published_revisions_are_visible_and_private_defaults_are_preserved(): void
    {
        $public = $this->published('visible');
        $private = $this->published('private', ['visibility'=>'admin']);
        $archived = $this->published('archived');
        (new DocumentVersionService())->archive($archived['id'], 1);
        $deleted = $this->published('deleted'); (new DocumentService())->delete($deleted['id']);
        (new DocumentService())->create(['title'=>'草稿', 'slug'=>'draft', 'scope'=>'framework', 'visibility'=>'public']);
        $result = (new PublicDocumentService())->listing([]);
        self::assertSame(1, $result['total']);
        self::assertSame([$public['slug']], array_column($result['list'], 'slug'));
        foreach (['private','archived','deleted','draft','missing'] as $slug) self::assertNull((new PublicDocumentService())->find($slug), $slug);
        self::assertSame('admin', (new DocumentService())->find($private['id'])['visibility']);
        self::assertSame('public', (new DocumentService())->create(['title'=>'默认', 'slug'=>'default', 'scope'=>'framework'])['visibility']);
    }

    public function test_draft_content_and_metadata_never_replace_the_public_revision_or_enter_search(): void
    {
        $row = $this->published('stable');
        (new DocumentVersionService())->saveDraft($row['id'], $this->doc('秘密草稿'), 1);
        (new DocumentService())->updateMeta($row['id'], ['title'=>'秘密标题', 'summary'=>'秘密摘要', 'metaVersion'=>1]);
        $read = (new PublicDocumentService())->find('stable');
        self::assertSame('公开标题 stable', $read['title']);
        self::assertSame('发布摘要', $read['summary']);
        self::assertSame('发布正文', \app\document\service\ContentValidator::plainText($read['content']));
        self::assertSame(0, (new PublicDocumentService())->listing(['keyword'=>'秘密'])['total']);
        self::assertSame(1, (new PublicDocumentService())->listing(['keyword'=>'发布摘要'])['total']);
        self::assertArrayNotHasKey('ownerId', $read);
        self::assertArrayNotHasKey('lockVersion', $read);
    }

    public function test_search_treats_percent_and_underscore_as_literal_text(): void
    {
        $this->published('special', ['title'=>'配置 100% A_B']);
        $this->published('ordinary', ['title'=>'配置其他内容']);
        self::assertSame(['special'], array_column((new PublicDocumentService())->listing(['keyword'=>'100%'])['list'], 'slug'));
        self::assertSame(['special'], array_column((new PublicDocumentService())->listing(['keyword'=>'A_B'])['list'], 'slug'));
    }

    public function test_visibility_updates_are_validated_and_can_close_an_existing_public_link(): void
    {
        $row = $this->published('closing');
        (new DocumentService())->updateMeta($row['id'], ['visibility'=>'admin', 'metaVersion'=>1]);
        self::assertNull((new PublicDocumentService())->find('closing'));
        $this->expectException(\InvalidArgumentException::class);
        (new DocumentService())->updateMeta($row['id'], ['visibility'=>'invalid', 'metaVersion'=>2]);
    }

    public function test_an_editor_without_publish_permission_cannot_expose_an_existing_private_publication(): void
    {
        (new \app\common\service\database\MigrationService())->run();
        $row = $this->published('permission-boundary', ['visibility'=>'admin']);
        $this->actAs(2, 'PUT', ['visibility'=>'public','metaVersion'=>1]);
        $response = (new \app\admin\plugins\document\controller\DocumentController())->updateMeta($row['id']);
        self::assertSame(403, json_decode($response->rawBody(), true)['code']);
        self::assertNull((new PublicDocumentService())->find('permission-boundary'));
        $this->actAs(1, 'PUT', ['visibility'=>'public','metaVersion'=>1]);
        $response = (new \app\admin\plugins\document\controller\DocumentController())->updateMeta($row['id']);
        self::assertSame(200, json_decode($response->rawBody(), true)['code']);
        self::assertNotNull((new PublicDocumentService())->find('permission-boundary'));
    }

    public function test_public_directory_only_contains_categories_with_visible_documents_and_their_ancestors(): void
    {
        $now = date('Y-m-d H:i:s');
        $root = Db::table('document_categories')->insertGetId(['scope'=>'framework','parent_id'=>0,'name'=>'主框架','slug'=>'framework','sort_order'=>1,'created_at'=>$now,'updated_at'=>$now]);
        $child = Db::table('document_categories')->insertGetId(['scope'=>'framework','parent_id'=>$root,'name'=>'安装','slug'=>'install','sort_order'=>2,'created_at'=>$now,'updated_at'=>$now]);
        Db::table('document_categories')->insert(['scope'=>'framework','parent_id'=>0,'name'=>'内部秘密目录','slug'=>'internal','sort_order'=>3,'created_at'=>$now,'updated_at'=>$now]);
        $row = $this->published('categorized', ['categoryIds'=>[$child]]);
        self::assertSame(['主框架','安装'], array_column((new PublicDocumentService())->categories(), 'name'));
        self::assertSame([$row['slug']], array_column((new PublicDocumentService())->listing(['categoryId'=>(string)$root])['list'], 'slug'));
    }
}
