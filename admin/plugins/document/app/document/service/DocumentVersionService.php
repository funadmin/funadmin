<?php

declare(strict_types=1);

namespace app\document\service;

use app\document\model\{Document, DocumentRevision};
use InvalidArgumentException;
use support\Db;

/**
 * 草稿 / 发布 / 归档 / 恢复。
 * 文档行保存当前草稿与 lock_version；每次发布插入一条不可变修订。
 * 写入都在事务内锁定文档行并比对 lock_version，旧版本写入返回 409。
 */
final class DocumentVersionService
{
    public function __construct(private readonly DocumentService $documents = new DocumentService())
    {
    }

    public function saveDraft(int $id, mixed $content, int $lockVersion): array
    {
        $clean = ContentValidator::clean($content);
        Db::transaction(function () use ($id, $clean, $lockVersion): void {
            $row = $this->lock($id, $lockVersion);
            Document::query()->where('id', $id)->update([
                'content_json' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'plain_text' => ContentValidator::plainText($clean),
                // 保存不改变发布状态：只有发布权限才能发布或取消归档。
                'has_unpublished_changes' => (int) $row->current_revision_no > 0,
                'lock_version' => (int) $row->lock_version + 1,
                'updated_at' => Rules::now(),
            ]);
            Events::record($id, 'save');
        });
        return $this->documents->find($id);
    }

    /** 发布调用方看到的那一版草稿；lockVersion 不一致说明草稿已被他人改过。 */
    public function publish(int $id, string $changeSummary, int $lockVersion): array
    {
        Db::transaction(function () use ($id, $changeSummary, $lockVersion): void {
            $row = $this->lock($id, $lockVersion);
            $next = (int) $row->current_revision_no + 1;
            DocumentRevision::query()->insert([
                'document_id' => $id, 'revision_no' => $next, 'title' => $row->title, 'summary' => $row->summary,
                'content_json' => $row->content_json,
                'change_summary' => Rules::text($changeSummary, 500, '变更说明'),
                'created_by' => Rules::actor(), 'created_at' => Rules::now(),
            ]);
            // 正文未变，不递增 lock_version，避免正在编辑的人被迫丢弃修改。
            Document::query()->where('id', $id)->update([
                'status' => 'published', 'current_revision_no' => $next,
                'has_unpublished_changes' => false, 'updated_at' => Rules::now(),
            ]);
            Events::record($id, 'publish', $next);
        });
        return $this->documents->find($id);
    }

    public function archive(int $id, int $lockVersion): array
    {
        Db::transaction(function () use ($id, $lockVersion): void {
            $row = $this->lock($id, $lockVersion);
            Document::query()->where('id', $id)->update(['status' => 'archived', 'updated_at' => Rules::now()]);
            Events::record($id, 'archive', (int) $row->current_revision_no);
        });
        return $this->documents->find($id);
    }

    public function revisions(int $id, int $page, int $pageSize): array
    {
        $this->documents->row($id);
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $query = DocumentRevision::query()->where('document_id', $id);
        $total = (clone $query)->count();
        $list = $query->orderByDesc('revision_no')->forPage($page, $pageSize)
            ->get(['revision_no', 'title', 'change_summary', 'created_by', 'created_at'])
            ->map(static fn ($r) => [
                'revisionNo' => (int) $r->revision_no, 'title' => (string) $r->title,
                'changeSummary' => (string) $r->change_summary, 'createdBy' => (int) $r->created_by, 'createdAt' => (string) $r->created_at,
            ])->all();
        return ['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    public function revision(int $id, int $revisionNo): array
    {
        $this->documents->row($id);
        $row = DocumentRevision::query()->where('document_id', $id)->where('revision_no', $revisionNo)->first();
        if (!$row) throw new InvalidArgumentException('修订不存在', 404);
        return [
            'revisionNo' => (int) $row->revision_no, 'title' => (string) $row->title, 'summary' => (string) $row->summary,
            'changeSummary' => (string) $row->change_summary, 'createdAt' => (string) $row->created_at,
            'content' => json_decode((string) $row->content_json, true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /** 把历史修订复制为新的草稿；不修改、不删除任何修订。 */
    public function restore(int $id, int $revisionNo, int $lockVersion): array
    {
        $revision = $this->revision($id, $revisionNo);
        Db::transaction(function () use ($id, $revision, $lockVersion): void {
            $row = $this->lock($id, $lockVersion);
            Document::query()->where('id', $id)->update([
                'content_json' => json_encode($revision['content'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'plain_text' => ContentValidator::plainText($revision['content']),
                'has_unpublished_changes' => $revision['revisionNo'] !== (int) $row->current_revision_no,
                'lock_version' => (int) $row->lock_version + 1,
                'updated_at' => Rules::now(),
            ]);
            Events::record($id, 'restore', $revision['revisionNo']);
        });
        return $this->documents->find($id);
    }

    /** 锁定文档行；传入 lockVersion 时校验乐观锁。 */
    private function lock(int $id, ?int $lockVersion): object
    {
        $row = Document::query()->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
        if (!$row) throw new InvalidArgumentException('文档不存在', 404);
        if ($lockVersion !== null && (int) $row->lock_version !== $lockVersion) {
            throw new InvalidArgumentException('文档已被他人修改，请重新载入后再保存', 409);
        }
        return $row;
    }
}
