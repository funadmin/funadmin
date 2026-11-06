<?php

declare(strict_types=1);

namespace app\document\service;

use InvalidArgumentException;
use support\Db;

/** 文档目录树：按范围组织，删除前要求目录为空。 */
final class DocumentCategoryService
{
    public function tree(): array
    {
        $rows = Db::table('document_categories')->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn ($row) => $this->dto($row))->all();
        $byParent = [];
        foreach ($rows as $row) $byParent[$row['parentId']][] = $row;
        $build = static function (int $parent) use (&$build, $byParent): array {
            return array_map(static fn (array $row) => $row + ['children' => $build($row['id'])], $byParent[$parent] ?? []);
        };
        return $build(0);
    }

    public function create(array $payload): array
    {
        $data = $this->validate($payload);
        $this->assertSlugFree($data['slug']);
        $now = Rules::now();
        $id = (int) Db::table('document_categories')->insertGetId($data + ['source' => 'manual', 'created_at' => $now, 'updated_at' => $now]);
        return $this->find($id);
    }

    public function update(int $id, array $payload): array
    {
        $current = $this->find($id);
        $data = $this->validate($payload + ['scope' => $current['scope'], 'slug' => $current['slug'], 'name' => $current['name']]);
        if ($data['parent_id'] === $id) throw new InvalidArgumentException('目录不能以自身为上级', 422);
        if ($data['slug'] !== $current['slug']) $this->assertSlugFree($data['slug']);
        Db::table('document_categories')->where('id', $id)->update($data + ['updated_at' => Rules::now()]);
        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $this->find($id);
        if (Db::table('document_categories')->where('parent_id', $id)->exists()
            || Db::table('document_category_links')->where('category_id', $id)->exists()) {
            throw new InvalidArgumentException('目录下还有子目录或文档，请先移走后再删除', 409);
        }
        Db::table('document_categories')->where('id', $id)->delete();
    }

    public function find(int $id): array
    {
        $row = Db::table('document_categories')->where('id', $id)->first();
        if (!$row) throw new InvalidArgumentException('目录不存在', 404);
        return $this->dto($row);
    }

    private function validate(array $payload): array
    {
        $scope = Rules::scope($payload['scope'] ?? null);
        $parentId = isset($payload['parentId']) && $payload['parentId'] !== 0 && $payload['parentId'] !== '0' && $payload['parentId'] !== null
            ? Rules::id($payload['parentId']) : 0;
        if ($parentId > 0) {
            $parent = Db::table('document_categories')->where('id', $parentId)->first();
            if (!$parent) throw new InvalidArgumentException('上级目录不存在', 422);
            if ($parent->scope !== $scope) throw new InvalidArgumentException('上级目录必须属于同一范围', 422);
        }
        return [
            'scope' => $scope,
            'parent_id' => $parentId,
            'name' => Rules::text($payload['name'] ?? null, 100, '目录名称', true),
            'slug' => Rules::slug($payload['slug'] ?? null),
            'sort_order' => (int) ($payload['sortOrder'] ?? 0),
        ];
    }

    private function assertSlugFree(string $slug): void
    {
        if (Db::table('document_categories')->where('slug', $slug)->exists()) {
            throw new InvalidArgumentException('目录标识已存在', 422);
        }
    }

    private function dto(object $row): array
    {
        return [
            'id' => (int) $row->id, 'scope' => (string) $row->scope, 'parentId' => (int) $row->parent_id,
            'name' => (string) $row->name, 'slug' => (string) $row->slug, 'sortOrder' => (int) $row->sort_order,
        ];
    }
}
