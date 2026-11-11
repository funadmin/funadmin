<?php

declare(strict_types=1);

namespace app\document\service;

use app\document\model\{Document, DocumentCategory, DocumentCategoryLink, DocumentFeedback};
use InvalidArgumentException;
use support\Db;

/** 文档元数据、草稿正文与列表查询。版本发布/恢复见 DocumentVersionService。 */
final class DocumentService
{
    public function create(array $payload): array
    {
        $meta = $this->meta($payload);
        $this->assertSlugFree($meta['slug']);
        $content = ContentValidator::clean($payload['content'] ?? ['type' => 'doc', 'content' => []]);
        $now = Rules::now();
        $id = Db::transaction(function () use ($meta, $content, $payload, $now): int {
            $id = (int) Document::query()->insertGetId($meta + [
                'status' => 'draft',
                'content_json' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'plain_text' => ContentValidator::plainText($content),
                'lock_version' => 1, 'current_revision_no' => 0,
                'owner_id' => Rules::actor(), 'source' => 'manual', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->syncCategories($id, Rules::ids($payload['categoryIds'] ?? []), $meta['scope']);
            Events::record($id, 'create');
            return $id;
        });
        return $this->find($id);
    }

    /** 只更新元数据（标题、范围、目录等），不改正文与 lock_version。 */
    public function updateMeta(int $id, array $payload): array
    {
        $current = $this->row($id);
        $meta = $this->meta($payload + [
            'title' => $current->title, 'slug' => $current->slug, 'scope' => $current->scope, 'type' => $current->type,
            'visibility' => $current->visibility, 'summary' => $current->summary, 'pluginCode' => $current->plugin_code, 'appliesTo' => $current->applies_to,
            'sortOrder' => (int) $current->sort_order,
        ]);
        if ($meta['slug'] !== $current->slug) $this->assertSlugFree($meta['slug']);
        Db::transaction(function () use ($id, $meta, $payload, $current): void {
            $locked = Document::query()->where('id', $id)->lockForUpdate()->first();
            if (array_key_exists('metaVersion', $payload) && (int) $locked->meta_version !== Rules::id($payload['metaVersion'])) {
                throw new InvalidArgumentException('文档信息已被他人修改，请刷新后再保存', 409);
            }
            Document::query()->where('id', $id)->update($meta + ['meta_version' => (int) $locked->meta_version + 1, 'updated_at' => Rules::now()]);
            if (array_key_exists('categoryIds', $payload)) {
                $this->syncCategories($id, Rules::ids($payload['categoryIds']), $meta['scope']);
            } elseif ($meta['scope'] !== $current->scope) {
                // 换范围但没给新目录：去掉（软删除）旧范围的目录关联，避免跨范围挂载。
                CategoryLinks::sync($id, []);
            }
            Events::record($id, 'update_meta');
        });
        return $this->find($id);
    }

    /** 软删除文档，目录关联写入相同的删除时间一起软删除；修订与操作记录作为历史保留。 */
    public function delete(int $id): void
    {
        $this->row($id);
        Db::transaction(static function () use ($id): void {
            $row = Document::query()->whereKey($id)->lockForUpdate()->first();
            if (!$row) throw new InvalidArgumentException('文档不存在', 404);
            $row->delete();
            CategoryLinks::afterSoftDelete($id, (string) Document::withTrashed()->whereKey($id)->value('deleted_at'));
            Events::record($id, 'delete');
        });
    }

    /** 回收站：已删除的文档，最近删除的在前。 */
    public function trashed(array $query): array
    {
        $page = max(1, min(10000, (int) ($query['page'] ?? 1)));
        $pageSize = max(1, min(100, (int) ($query['pageSize'] ?? 20)));
        $builder = Document::onlyTrashed();
        $keyword = is_string($query['keyword'] ?? null) ? trim($query['keyword']) : '';
        if ($keyword !== '') {
            $like = Rules::like(mb_substr($keyword, 0, 100));
            $builder->where(static fn ($where) => $where->where('title', 'like', $like)->orWhere('slug', 'like', $like));
        }
        $total = (clone $builder)->count();
        $rows = $builder->orderByDesc('deleted_at')->orderByDesc('id')->forPage($page, $pageSize)
            ->get(['id', 'title', 'slug', 'scope', 'status', 'deleted_at']);
        return [
            'list' => $rows->map(static fn ($row): array => [
                'id' => (int) $row->id, 'title' => (string) $row->title, 'slug' => (string) $row->slug, 'scope' => (string) $row->scope,
                'status' => (string) $row->status, 'deletedAt' => (string) $row->getRawOriginal('deleted_at'),
            ])->all(),
            'total' => $total, 'page' => $page, 'pageSize' => $pageSize,
        ];
    }

    /**
     * 从回收站恢复文档：标识已被未删除的文档占用时抛 RestoreConflictException（409）。
     * 同时恢复这次删除带走的目录关联（已删除或换了范围的目录跳过）。
     */
    public function restore(int $id): array
    {
        Db::transaction(static function () use ($id): void {
            $row = self::lockTrashed($id);
            $deletedAt = (string) $row->getRawOriginal('deleted_at');
            $row->restore();
            CategoryLinks::afterRestore($id, (string) $row->scope, $deletedAt);
            Events::record($id, 'recover');
        });
        return $this->find($id);
    }

    /**
     * 彻底删除回收站中的文档：文档、全部目录关联（含已软删除的）与读者反馈物理删除，不可恢复。
     * 修订与操作记录是只追加的历史表，按设计保留，不连带删除。
     */
    public function destroy(int $id): void
    {
        Db::transaction(static function () use ($id): void {
            self::lockTrashed($id);
            CategoryLinks::purge($id);
            DocumentFeedback::withTrashed()->where('document_id', $id)->forceDelete();
            Document::onlyTrashed()->whereKey($id)->forceDelete();
            Events::record($id, 'destroy');
        });
    }

    /** 锁定回收站中的文档；未删除或不存在时 404。 */
    private static function lockTrashed(int $id): Document
    {
        $row = Document::onlyTrashed()->whereKey($id)->lockForUpdate()->first();
        if (!$row) throw new InvalidArgumentException('回收站中没有这篇文档', 404);
        return $row;
    }

    public function find(int $id): array
    {
        $row = $this->row($id);
        return $this->dto($row, true) + ['plainText' => (string) $row->plain_text];
    }

    public function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($query['pageSize'] ?? 20)));
        $builder = Document::query();
        if (!empty($query['scope'])) $builder->where('scope', Rules::scope($query['scope']));
        if (!empty($query['type'])) $builder->where('type', Rules::type($query['type']));
        if (!empty($query['pluginCode'])) $builder->where('plugin_code', Rules::pluginCode($query['pluginCode']));
        if (!empty($query['status'])) {
            if (!in_array($query['status'], Rules::STATUSES, true)) throw new InvalidArgumentException('文档状态无效', 422);
            $builder->where('status', $query['status']);
        } else {
            // 默认列表不含归档文档；按状态筛选「已归档」时才显示。
            $builder->where('status', '<>', 'archived');
        }
        if (!empty($query['categoryId'])) {
            $builder->whereIn('id', DocumentCategoryLink::query()->select('document_id')->where('category_id', Rules::id($query['categoryId'])));
        }
        $keyword = is_string($query['keyword'] ?? null) ? trim($query['keyword']) : '';
        if ($keyword !== '') {
            $like = Rules::like(mb_substr($keyword, 0, 100));
            $builder->where(static function ($where) use ($like): void {
                $where->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('plain_text', 'like', $like);
            });
        }
        $total = (clone $builder)->count();
        $rows = $builder->orderByDesc('updated_at')->orderByDesc('id')->forPage($page, $pageSize)->get();
        $feedback = (new FeedbackService())->counts($rows->pluck('id')->all());
        $list = $rows->map(fn ($row) => $this->dto($row, false) + ['feedback' => $feedback[(int) $row->id] ?? ['helpful' => 0, 'unhelpful' => 0]])->all();
        return ['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    /** @internal 供版本服务在事务内复用。 */
    public function row(int $id): object
    {
        $row = Document::query()->where('id', $id)->first();
        if (!$row) throw new InvalidArgumentException('文档不存在', 404);
        return $row;
    }

    private function meta(array $payload): array
    {
        return [
            'title' => Rules::text($payload['title'] ?? null, 200, '标题', true),
            'slug' => Rules::slug($payload['slug'] ?? null),
            'summary' => Rules::text($payload['summary'] ?? '', 500, '摘要'),
            'scope' => Rules::scope($payload['scope'] ?? null),
            'visibility' => Rules::visibility($payload['visibility'] ?? 'public'),
            'type' => Rules::type($payload['type'] ?? 'guide'),
            'plugin_code' => Rules::pluginCode($payload['pluginCode'] ?? ''),
            'applies_to' => Rules::text($payload['appliesTo'] ?? '', 64, '适用版本'),
            'sort_order' => Rules::sortOrder($payload['sortOrder'] ?? 0),
        ];
    }

    private function assertSlugFree(string $slug): void
    {
        // 只检查未删除的文档：删除后可以用相同标识重建（唯一索引含 deleted_token）。
        if (Document::query()->where('slug', $slug)->exists()) throw new InvalidArgumentException('文档标识已存在', 409);
    }

    private function syncCategories(int $id, array $categoryIds, string $scope): void
    {
        if ($categoryIds) {
            $valid = DocumentCategory::query()->whereIn('id', $categoryIds)->where('scope', $scope)->count();
            if ($valid !== count($categoryIds)) throw new InvalidArgumentException('目录不存在或不属于该文档范围', 422);
        }
        CategoryLinks::sync($id, $categoryIds);
    }

    public function dto(object $row, bool $withContent): array
    {
        $dto = [
            'id' => (int) $row->id, 'title' => (string) $row->title, 'slug' => (string) $row->slug,
            'summary' => (string) $row->summary, 'scope' => (string) $row->scope, 'pluginCode' => (string) $row->plugin_code,
            'type' => (string) $row->type, 'status' => (string) $row->status, 'visibility' => (string) $row->visibility, 'appliesTo' => (string) $row->applies_to,
            'sortOrder' => (int) $row->sort_order,
            'lockVersion' => (int) $row->lock_version, 'currentRevisionNo' => (int) $row->current_revision_no,
            'hasUnpublishedChanges' => (bool) $row->has_unpublished_changes,
            'metaVersion' => (int) $row->meta_version,
            'categoryIds' => array_map('intval', DocumentCategoryLink::query()->where('document_id', $row->id)->orderBy('category_id')->pluck('category_id')->all()),
            'updatedAt' => (string) $row->updated_at,
        ];
        if ($withContent) $dto['content'] = json_decode((string) $row->content_json, true, 512, JSON_THROW_ON_ERROR);
        return $dto;
    }
}
