<?php

declare(strict_types=1);

namespace app\document\service;

use app\document\model\{DocumentCategory, DocumentCategoryLink};
use InvalidArgumentException;

/** 文档目录树：按范围组织，删除前要求目录为空。 */
final class DocumentCategoryService
{
    public function tree(): array
    {
        // 每个目录直接关联的未删除文档数，供后台显示和判断能否删除。
        $rows = DocumentCategory::query()->withCount(['documents' => static fn ($query) => $query->alive()])
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn ($row) => $this->dto($row) + ['documentCount' => (int) $row->documents_count])->all();
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
        $id = (int) DocumentCategory::query()->insertGetId($data + ['source' => 'manual', 'created_at' => $now, 'updated_at' => $now]);
        return $this->find($id);
    }

    public function update(int $id, array $payload): array
    {
        $current = $this->find($id);
        $data = $this->validate($payload + ['scope' => $current['scope'], 'slug' => $current['slug'], 'name' => $current['name'], 'parentId' => $current['parentId'], 'sortOrder' => $current['sortOrder']]);
        if ($data['parent_id'] === $id || in_array($data['parent_id'], $this->descendantIds($id), true)) {
            throw new InvalidArgumentException('不能把目录移动到它自己或它的子目录下', 422);
        }
        if ($data['scope'] !== $current['scope']
            && (DocumentCategory::query()->where('parent_id', $id)->exists()
                || DocumentCategoryLink::query()->where('category_id', $id)->exists())) {
            throw new InvalidArgumentException('目录下还有子目录或文档，不能修改范围', 409);
        }
        if ($data['slug'] !== $current['slug']) $this->assertSlugFree($data['slug']);
        DocumentCategory::query()->where('id', $id)->update($data + ['updated_at' => Rules::now()]);
        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $this->find($id);
        if (DocumentCategory::query()->where('parent_id', $id)->exists()
            || DocumentCategoryLink::query()->where('category_id', $id)->exists()) {
            throw new InvalidArgumentException('目录下还有子目录或文档，请先移走后再删除', 409);
        }
        DocumentCategory::query()->where('id', $id)->delete();
    }

    public function find(int $id): array
    {
        $row = DocumentCategory::query()->where('id', $id)->first();
        if (!$row) throw new InvalidArgumentException('目录不存在', 404);
        return $this->dto($row);
    }

    /** @return list<int> */
    private function descendantIds(int $id): array
    {
        $children = [];
        foreach (DocumentCategory::query()->get(['id', 'parent_id']) as $row) $children[(int) $row->parent_id][] = (int) $row->id;
        $result = [];
        $queue = $children[$id] ?? [];
        while ($queue) {
            $next = array_shift($queue);
            if (in_array($next, $result, true)) continue;
            $result[] = $next;
            array_push($queue, ...($children[$next] ?? []));
        }
        return $result;
    }

    private function validate(array $payload): array
    {
        $scope = Rules::scope($payload['scope'] ?? null);
        $parentId = isset($payload['parentId']) && $payload['parentId'] !== 0 && $payload['parentId'] !== '0' && $payload['parentId'] !== null
            ? Rules::id($payload['parentId']) : 0;
        if ($parentId > 0) {
            $parent = DocumentCategory::query()->where('id', $parentId)->first();
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
        if (DocumentCategory::query()->where('slug', $slug)->exists()) {
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
