<?php

declare(strict_types=1);

namespace app\document\service;

use support\Db;

/**
 * 内置目录与入门文档。只插入尚不存在的稳定 slug，
 * 因此重复执行（升级、重新启用）永远不会覆盖管理员的修改。
 */
final class SeedService
{
    public function __construct(private readonly string $directory)
    {
    }

    public function run(): void
    {
        Db::transaction(function (): void {
            $categories = $this->seedCategories();
            foreach (glob($this->directory . '/documents/*.json') ?: [] as $file) {
                $this->seedDocument($this->read($file), $categories);
            }
        });
    }

    /** @return array<string,int> slug => id */
    private function seedCategories(): array
    {
        $now = Rules::now();
        foreach ($this->read($this->directory . '/categories.json') as $item) {
            $slug = Rules::slug($item['slug']);
            if (Db::table('document_categories')->where('slug', $slug)->exists()) continue;
            $parentId = $item['parent'] === null ? 0 : (int) Db::table('document_categories')->where('slug', $item['parent'])->value('id');
            Db::table('document_categories')->insert([
                'scope' => Rules::scope($item['scope']), 'parent_id' => $parentId, 'name' => (string) $item['name'], 'slug' => $slug,
                'sort_order' => (int) ($item['sort'] ?? 0), 'source' => 'seed', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        return Db::table('document_categories')->pluck('id', 'slug')->map(static fn ($id): int => (int) $id)->all();
    }

    private function seedDocument(array $data, array $categories): void
    {
        $slug = Rules::slug($data['slug']);
        if (Db::table('documents')->where('slug', $slug)->exists()) return;
        $content = ContentValidator::clean($data['content']);
        $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $now = Rules::now();
        $title = Rules::text($data['title'], 200, '标题', true);
        $summary = Rules::text($data['summary'] ?? '', 500, '摘要');
        $id = (int) Db::table('documents')->insertGetId([
            'title' => $title, 'slug' => $slug, 'summary' => $summary,
            'scope' => Rules::scope($data['scope']), 'plugin_code' => Rules::pluginCode($data['pluginCode'] ?? ''),
            'type' => Rules::type($data['type'] ?? 'guide'), 'status' => 'published', 'visibility' => 'admin',
            'applies_to' => Rules::text($data['appliesTo'] ?? '', 64, '适用版本'),
            'content_json' => $json, 'plain_text' => ContentValidator::plainText($content),
            'lock_version' => 1, 'current_revision_no' => 1, 'owner_id' => 0, 'source' => 'seed',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        Db::table('document_revisions')->insert([
            'document_id' => $id, 'revision_no' => 1, 'title' => $title, 'summary' => $summary, 'content_json' => $json,
            'change_summary' => '内置文档', 'created_by' => 0, 'created_at' => $now,
        ]);
        if (isset($data['category'], $categories[$data['category']])) {
            Db::table('document_category_links')->insert(['document_id' => $id, 'category_id' => $categories[$data['category']]]);
        }
        Events::record($id, 'seed', 1);
    }

    private function read(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new \RuntimeException('内置文档数据无效：' . basename($file));
        return $data;
    }
}
