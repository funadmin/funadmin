<?php

declare(strict_types=1);

namespace Tests\Document;

use app\document\service\DocumentService;
use app\document\service\DocumentVersionService;
use support\Db;

final class DocumentVersionServiceTest extends DocumentTestCase
{
    private DocumentService $documents;
    private DocumentVersionService $versions;
    private array $doc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documents = new DocumentService();
        $this->versions = new DocumentVersionService();
        $this->doc = $this->documents->create(['title' => '部署', 'slug' => 'deploy', 'scope' => 'framework', 'content' => $this->doc('v1')]);
    }

    private function lock(): int
    {
        return $this->documents->find($this->doc['id'])['lockVersion'];
    }

    public function test_save_draft_updates_content_and_bumps_lock_version(): void
    {
        $saved = $this->versions->saveDraft($this->doc['id'], $this->doc('v2'), 1);
        self::assertSame(2, $saved['lockVersion']);
        self::assertSame($this->doc('v2'), $saved['content']);
        self::assertSame('draft', $saved['status']);
    }

    public function test_publish_creates_increasing_immutable_revisions(): void
    {
        $first = $this->versions->publish($this->doc['id'], '首次发布', $this->lock());
        self::assertSame('published', $first['status']);
        self::assertSame(1, $first['currentRevisionNo']);

        $draft = $this->versions->saveDraft($this->doc['id'], $this->doc('v2'), $first['lockVersion']);
        self::assertSame('published', $draft['status'], '编辑不改变发布状态');
        self::assertTrue($draft['hasUnpublishedChanges'], '已发布文档的修改标记为未发布');
        $second = $this->versions->publish($this->doc['id'], '第二版', $this->lock());
        self::assertSame(2, $second['currentRevisionNo']);

        $revisions = $this->versions->revisions($this->doc['id'], 1, 20);
        self::assertSame([2, 1], array_column($revisions['list'], 'revisionNo'));
        self::assertSame($this->doc('v1'), $this->versions->revision($this->doc['id'], 1)['content'], '旧修订内容不可变');
    }

    public function test_restore_copies_revision_into_a_new_draft_without_touching_history(): void
    {
        $this->versions->publish($this->doc['id'], 'v1', $this->lock());
        $draft = $this->versions->saveDraft($this->doc['id'], $this->doc('v2'), $this->documents->find($this->doc['id'])['lockVersion']);
        $this->versions->publish($this->doc['id'], 'v2', $this->lock());

        $lock = $this->documents->find($this->doc['id'])['lockVersion'];
        $restored = $this->versions->restore($this->doc['id'], 1, $lock);
        self::assertSame($this->doc('v1'), $restored['content']);
        self::assertSame('published', $restored['status'], '恢复只替换草稿正文，不改发布状态');
        self::assertTrue($restored['hasUnpublishedChanges']);
        self::assertSame($lock + 1, $restored['lockVersion']);
        self::assertSame(2, Db::table('document_revisions')->where('document_id', $this->doc['id'])->count(), '恢复不新增、不删除修订');
        self::assertSame($this->doc('v2'), $this->versions->revision($this->doc['id'], 2)['content']);
        unset($draft);
    }

    public function test_archive_keeps_revisions_and_editing_never_unarchives(): void
    {
        $this->versions->publish($this->doc['id'], 'v1', $this->lock());
        $archived = $this->versions->archive($this->doc['id'], $this->lock());
        self::assertSame('archived', $archived['status']);
        self::assertSame(1, Db::table('document_revisions')->where('document_id', $this->doc['id'])->count());
        $edited = $this->versions->saveDraft($this->doc['id'], $this->doc('重新编辑'), $archived['lockVersion']);
        self::assertSame('archived', $edited['status'], '只有编辑权限的人不能通过保存取消归档');
        $republished = $this->versions->publish($this->doc['id'], '重新发布', $edited['lockVersion']);
        self::assertSame('published', $republished['status'], '发布权限可以把归档文档重新发布');
        self::assertFalse($republished['hasUnpublishedChanges']);
    }

    public function test_new_document_is_draft_until_first_publish(): void
    {
        self::assertSame('draft', $this->doc['status']);
        $saved = $this->versions->saveDraft($this->doc['id'], $this->doc('v2'), $this->lock());
        self::assertSame('draft', $saved['status']);
        self::assertSame('published', $this->versions->publish($this->doc['id'], '首版', $this->lock())['status']);
    }

    public function test_restore_of_a_missing_revision_is_404(): void
    {
        try {
            $this->versions->restore($this->doc['id'], 99, 1);
            self::fail('不存在的修订应返回 404');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(404, $e->getCode());
        }
    }

    public function test_events_record_the_lifecycle(): void
    {
        $this->versions->publish($this->doc['id'], 'v1', $this->lock());
        $this->versions->archive($this->doc['id'], $this->lock());
        $actions = Db::table('document_events')->where('document_id', $this->doc['id'])->orderBy('id')->pluck('action')->all();
        self::assertSame(['create', 'publish', 'archive'], $actions);
    }
}
