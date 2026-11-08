<?php

declare(strict_types=1);

namespace app\document\service;

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
            $id = (int) Db::table('documents')->insertGetId($meta + [
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
        ]);
        if ($meta['slug'] !== $current->slug) $this->assertSlugFree($meta['slug']);
        Db::transaction(function () use ($id, $meta, $payload, $current): void {
            $locked = Db::table('documents')->where('id', $id)->lockForUpdate()->first();
            if (array_key_exists('metaVersion', $payload) && (int) $locked->meta_version !== Rules::id($payload['metaVersion'])) {
                throw new InvalidArgumentException('文档信息已被他人修改，请刷新后再保存', 409);
            }
            Db::table('documents')->where('id', $id)->update($meta + ['meta_version' => (int) $locked->meta_version + 1, 'updated_at' => Rules::now()]);
            if (array_key_exists('categoryIds', $payload)) {
                $this->syncCategories($id, Rules::ids($payload['categoryIds']), $meta['scope']);
            } elseif ($meta['scope'] !== $current->scope) {
                // 换范围但没给新目录：去掉旧范围的目录关联，避免跨范围挂载。
                Db::table('document_category_links')->where('document_id', $id)->delete();
            }
            Events::record($id, 'update_meta');
        });
        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $this->row($id);
        Db::transaction(static function () use ($id): void {
            Db::table('documents')->where('id', $id)->update(['deleted_at' => Rules::now()]);
            Db::table('document_category_links')->where('document_id', $id)->delete();
            Events::record($id, 'delete');
        });
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
        $builder = Db::table('documents')->whereNull('deleted_at');
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
            $builder->whereIn('id', Db::table('document_category_links')->select('document_id')->where('category_id', Rules::id($query['categoryId'])));
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
        return ['list' => $rows->map(fn ($row) => $this->dto($row, false))->all(), 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    /** @internal 供版本服务在事务内复用。 */
    public function row(int $id): object
    {
        $row = Db::table('documents')->where('id', $id)->whereNull('deleted_at')->first();
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
        ];
    }

    private function assertSlugFree(string $slug): void
    {
        // 软删除的文档仍占用标识，避免旧链接指向新文档。
        if (Db::table('documents')->where('slug', $slug)->exists()) throw new InvalidArgumentException('文档标识已存在', 409);
    }

    private function syncCategories(int $id, array $categoryIds, string $scope): void
    {
        if ($categoryIds) {
            $valid = Db::table('document_categories')->whereIn('id', $categoryIds)->where('scope', $scope)->count();
            if ($valid !== count($categoryIds)) throw new InvalidArgumentException('目录不存在或不属于该文档范围', 422);
        }
        Db::table('document_category_links')->where('document_id', $id)->delete();
        if ($categoryIds) {
            Db::table('document_category_links')->insert(array_map(static fn (int $c) => ['document_id' => $id, 'category_id' => $c], $categoryIds));
        }
    }

    public function dto(object $row, bool $withContent): array
    {
        $dto = [
            'id' => (int) $row->id, 'title' => (string) $row->title, 'slug' => (string) $row->slug,
            'summary' => (string) $row->summary, 'scope' => (string) $row->scope, 'pluginCode' => (string) $row->plugin_code,
            'type' => (string) $row->type, 'status' => (string) $row->status, 'visibility' => (string) $row->visibility, 'appliesTo' => (string) $row->applies_to,
            'lockVersion' => (int) $row->lock_version, 'currentRevisionNo' => (int) $row->current_revision_no,
            'hasUnpublishedChanges' => (bool) $row->has_unpublished_changes,
            'metaVersion' => (int) $row->meta_version,
            'categoryIds' => array_map('intval', Db::table('document_category_links')->where('document_id', $row->id)->orderBy('category_id')->pluck('category_id')->all()),
            'updatedAt' => (string) $row->updated_at,
        ];
        if ($withContent) $dto['content'] = json_decode((string) $row->content_json, true, 512, JSON_THROW_ON_ERROR);
        return $dto;
    }
}
