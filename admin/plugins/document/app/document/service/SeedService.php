<?php

declare(strict_types=1);

namespace app\document\service;

use app\document\model\{Document, DocumentCategory, DocumentCategoryLink, DocumentRevision, DocumentSeedEntry};
use support\Db;

/**
 * 内置目录与入门文档。
 *
 * - 首次见到的内置条目（按稳定 slug 登记）会被插入；删除或改名后不再补回。
 * - 仍保持内置原样的条目会随插件升级同步到最新的目录结构、排序和正文；
 *   管理员一旦修改过（目录：updated_at 变化；文档：编辑元数据、保存草稿、发布或归档），就不再触碰。
 * - RETIRED_CATEGORIES 中列出的旧内置目录，在未被修改且已经清空时删除。
 */
final class SeedService
{
    /** 1.3 及更早按范围分层的内置子目录；1.4 起文档改为扁平的阅读分组。 */
    public const RETIRED_CATEGORIES = [
        'framework-architecture', 'framework-deployment', 'framework-backend', 'framework-frontend',
        'plugin-dev-structure', 'plugin-dev-lifecycle',
    ];

    public function __construct(private readonly string $directory)
    {
    }

    public function run(): void
    {
        Db::transaction(function (): void {
            $seedCategories = $this->read($this->directory . '/categories.json');
            $categories = $this->seedCategories($seedCategories);
            $documents = array_map($this->read(...), glob($this->directory . '/documents/*.json') ?: []);
            usort($documents, static fn (array $a, array $b): int => [(int) ($a['sort'] ?? 0), $a['slug']] <=> [(int) ($b['sort'] ?? 0), $b['slug']]);
            foreach ($documents as $data) $this->seedDocument($data, $categories);
            $this->retireCategories(array_column($seedCategories, 'slug'));
        });
    }

    /** @return array<string,int> slug => id */
    private function seedCategories(array $items): array
    {
        $now = Rules::now();
        foreach ($items as $item) {
            $slug = Rules::slug($item['slug']);
            $parentId = $item['parent'] === null ? 0 : (int) DocumentCategory::query()->where('slug', $item['parent'])->value('id');
            $data = ['scope' => Rules::scope($item['scope']), 'parent_id' => $parentId, 'name' => (string) $item['name'], 'sort_order' => (int) ($item['sort'] ?? 0)];
            $claimed = $this->claim('category', $slug);
            $existing = DocumentCategory::query()->where('slug', $slug)->first();
            if ($existing === null) {
                if ($claimed) DocumentCategory::query()->insert($data + ['slug' => $slug, 'source' => 'seed', 'created_at' => $now, 'updated_at' => $now]);
                continue;
            }
            // 保持 updated_at 不变，下次升级仍能识别为「未修改的内置目录」。
            if ($this->ownedCategory($existing)) DocumentCategory::query()->where('id', $existing->id)->update($data);
        }
        return DocumentCategory::query()->pluck('id', 'slug')->map(static fn ($id): int => (int) $id)->all();
    }

    private function seedDocument(array $data, array $categories): void
    {
        $slug = Rules::slug($data['slug']);
        $claimed = $this->claim('document', $slug);
        $existing = Document::query()->where('slug', $slug)->first();
        if ($existing === null) {
            if ($claimed) $this->insertDocument($slug, $data, $categories);
            return;
        }
        if ($this->ownedDocument($existing)) $this->refreshDocument($existing, $data, $categories);
    }

    private function insertDocument(string $slug, array $data, array $categories): void
    {
        $fields = $this->fields($data);
        $now = Rules::now();
        $id = (int) Document::query()->insertGetId($fields + [
            'slug' => $slug, 'plugin_code' => Rules::pluginCode($data['pluginCode'] ?? ''),
            'status' => 'published', 'visibility' => Rules::visibility($data['visibility'] ?? 'public'),
            'lock_version' => 1, 'current_revision_no' => 1, 'owner_id' => 0, 'source' => 'seed',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DocumentRevision::query()->insert([
            'document_id' => $id, 'revision_no' => 1, 'title' => $fields['title'], 'summary' => $fields['summary'],
            'content_json' => $fields['content_json'], 'change_summary' => '内置文档', 'created_by' => 0, 'created_at' => $now,
        ]);
        $this->link($id, $data, $categories);
        Events::record($id, 'seed', 1);
    }

    /** 未修改的内置文档：就地更新内置修订（仍是第 1 版），不产生新的历史版本。 */
    private function refreshDocument(object $row, array $data, array $categories): void
    {
        $fields = $this->fields($data);
        $changed = $fields['title'] !== (string) $row->title || $fields['summary'] !== (string) $row->summary
            || $fields['scope'] !== (string) $row->scope || $fields['type'] !== (string) $row->type
            || $fields['applies_to'] !== (string) $row->applies_to
            || json_decode((string) $row->content_json, true) !== json_decode($fields['content_json'], true);
        $id = (int) $row->id;
        if ($changed) {
            $now = Rules::now();
            Document::query()->where('id', $id)->update($fields + ['updated_at' => $now]);
            DocumentRevision::query()->where('document_id', $id)->where('revision_no', 1)->update([
                'title' => $fields['title'], 'summary' => $fields['summary'], 'content_json' => $fields['content_json'], 'created_at' => $now,
            ]);
            Events::record($id, 'seed_update', 1);
        } elseif ((int) $row->sort_order !== $fields['sort_order']) {
            Document::query()->where('id', $id)->update(['sort_order' => $fields['sort_order']]);
        }
        $this->link($id, $data, $categories);
    }

    private function fields(array $data): array
    {
        $content = ContentValidator::clean($data['content']);
        return [
            'title' => Rules::text($data['title'], 200, '标题', true),
            'summary' => Rules::text($data['summary'] ?? '', 500, '摘要'),
            'scope' => Rules::scope($data['scope']),
            'type' => Rules::type($data['type'] ?? 'guide'),
            'applies_to' => Rules::text($data['appliesTo'] ?? '', 64, '适用版本'),
            'sort_order' => Rules::sortOrder((int) ($data['sort'] ?? 0)),
            'content_json' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'plain_text' => ContentValidator::plainText($content),
        ];
    }

    /** 目标目录不存在（例如管理员删掉了）时保留原有归属，不把文档变成无目录。 */
    private function link(int $id, array $data, array $categories): void
    {
        $categoryId = isset($data['category']) ? ($categories[$data['category']] ?? null) : null;
        if ($categoryId === null) return;
        DocumentCategoryLink::query()->where('document_id', $id)->delete();
        DocumentCategoryLink::query()->insert(['document_id' => $id, 'category_id' => $categoryId]);
    }

    /** 删除已退役、未被修改且已经没有文档和子目录的内置目录（由内向外逐层清理）。 */
    private function retireCategories(array $seedSlugs): void
    {
        $retired = array_values(array_diff(self::RETIRED_CATEGORIES, $seedSlugs));
        if ($retired === []) return;
        do {
            $removed = 0;
            $candidates = DocumentCategory::query()->where('source', 'seed')->whereIn('slug', $retired)->get();
            foreach ($candidates as $category) {
                if (!$this->ownedCategory($category)) continue;
                if (DocumentCategory::query()->where('parent_id', $category->id)->exists()) continue;
                if (DocumentCategoryLink::query()->where('category_id', $category->id)->exists()) continue;
                $removed += DocumentCategory::query()->where('id', $category->id)->delete();
            }
        } while ($removed > 0);
    }

    /** 管理员没有修改过的内置目录：后台更新目录会刷新 updated_at。 */
    private function ownedCategory(object $row): bool
    {
        return (string) $row->source === 'seed' && (string) $row->updated_at === (string) $row->created_at;
    }

    /** 管理员没有动过的内置文档：元数据、正文、发布状态都保持写入时的样子。 */
    private function ownedDocument(object $row): bool
    {
        return (string) $row->source === 'seed' && $row->deleted_at === null && (string) $row->status === 'published'
            && (int) $row->lock_version === 1 && (int) $row->meta_version === 1 && (int) $row->current_revision_no === 1
            && !(bool) $row->has_unpublished_changes;
    }

    /** 首次见到该内置条目时登记并返回 true；之后永远返回 false（删除或改名后不再补回）。 */
    private function claim(string $kind, string $slug): bool
    {
        return DocumentSeedEntry::query()->insertOrIgnore(['kind' => $kind, 'slug' => $slug, 'created_at' => Rules::now()]) > 0;
    }

    private function read(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new \RuntimeException('内置文档数据无效：' . basename($file));
        return $data;
    }
}
