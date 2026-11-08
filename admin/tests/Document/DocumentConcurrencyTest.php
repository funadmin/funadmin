<?php

declare(strict_types=1);

namespace Tests\Document;

use app\document\service\DocumentService;
use app\document\service\DocumentVersionService;

final class DocumentConcurrencyTest extends DocumentTestCase
{
    public function test_stale_lock_version_on_save_is_409_and_leaves_content_unchanged(): void
    {
        $documents = new DocumentService();
        $versions = new DocumentVersionService();
        $doc = $documents->create(['title' => '并发', 'slug' => 'concurrent', 'scope' => 'framework', 'content' => $this->doc('原文')]);

        $versions->saveDraft($doc['id'], $this->doc('甲的修改'), 1); // 甲先保存，lock 1 -> 2
        try {
            $versions->saveDraft($doc['id'], $this->doc('乙的修改'), 1); // 乙仍持有 lock 1
            self::fail('旧 lockVersion 必须冲突');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(409, $e->getCode());
        }
        self::assertSame($this->doc('甲的修改'), $documents->find($doc['id'])['content'], '冲突时不能覆盖甲的内容');
    }

    public function test_stale_lock_version_on_restore_is_409(): void
    {
        $documents = new DocumentService();
        $versions = new DocumentVersionService();
        $doc = $documents->create(['title' => '并发', 'slug' => 'concurrent', 'scope' => 'framework', 'content' => $this->doc('原文')]);
        $versions->publish($doc['id'], 'v1', $documents->find($doc['id'])['lockVersion']);
        $versions->saveDraft($doc['id'], $this->doc('新草稿'), $documents->find($doc['id'])['lockVersion']);

        $this->expectExceptionCode(409);
        $versions->restore($doc['id'], 1, 1);
    }

    public function test_publish_with_a_stale_lock_is_409_so_unseen_content_is_never_published(): void
    {
        $documents = new DocumentService();
        $versions = new DocumentVersionService();
        $doc = $documents->create(['title' => '并发', 'slug' => 'concurrent', 'scope' => 'framework', 'content' => $this->doc('B 看到的版本')]);
        $versions->saveDraft($doc['id'], $this->doc('A 后来保存的版本'), 1); // lock 1 -> 2，B 没看到
        try {
            $versions->publish($doc['id'], 'B 发布', 1);
            self::fail('发布者没看过最新草稿，必须冲突');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(409, $e->getCode());
        }
        self::assertSame(0, \support\Db::table('document_revisions')->where('document_id', $doc['id'])->count());
    }

    public function test_publish_and_archive_do_not_bump_the_lock_so_editors_are_not_forced_to_discard(): void
    {
        $documents = new DocumentService();
        $versions = new DocumentVersionService();
        $doc = $documents->create(['title' => '并发', 'slug' => 'concurrent', 'scope' => 'framework', 'content' => $this->doc('原文')]);
        $versions->publish($doc['id'], 'v1', 1);
        $versions->archive($doc['id'], 1);
        // 正在编辑的人仍持有 lock 1：正文没被别人改过，保存应成功。
        $saved = $versions->saveDraft($doc['id'], $this->doc('编辑者的修改'), 1);
        self::assertSame(2, $saved['lockVersion']);
    }

    public function test_metadata_update_does_not_bump_the_content_lock(): void
    {
        $documents = new DocumentService();
        $versions = new DocumentVersionService();
        $doc = $documents->create(['title' => '并发', 'slug' => 'concurrent', 'scope' => 'framework', 'content' => $this->doc('原文')]);
        $documents->updateMeta($doc['id'], ['title' => '新标题']);
        self::assertSame(2, $versions->saveDraft($doc['id'], $this->doc('正文'), 1)['lockVersion']);
    }

    public function test_metadata_update_with_a_stale_meta_version_is_409(): void
    {
        $documents = new DocumentService();
        $doc = $documents->create(['title' => '原标题', 'slug' => 'meta', 'scope' => 'framework']);
        self::assertSame(1, $doc['metaVersion']);
        $first = $documents->updateMeta($doc['id'], ['title' => '甲改的标题', 'metaVersion' => 1]);
        self::assertSame(2, $first['metaVersion']);
        try {
            $documents->updateMeta($doc['id'], ['title' => '乙基于旧数据改的', 'metaVersion' => 1]);
            self::fail('旧 metaVersion 必须冲突');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(409, $e->getCode());
        }
        self::assertSame('甲改的标题', $documents->find($doc['id'])['title']);
    }
}
