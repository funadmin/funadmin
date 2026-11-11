<?php

declare(strict_types=1);

namespace app\document\service;

use app\document\model\{DocumentCategory, DocumentCategoryLink};
use InvalidArgumentException;
use support\Db;

/** 文档目录树：按范围组织，删除（软删除）前要求目录下没有未删除的子目录和文档；回收站中可恢复或彻底删除。 */
final class DocumentCategoryService
{
    public function tree(): array
    {
        // 每个目录直接关联的未删除文档数，供后台显示和判断能否删除。
        $rows = DocumentCategory::query()->withCount('documents')
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn ($row) => $this->dto($row) + ['documentCount' => (int) $row->documents_count])->all();
        $byParent = [];
        foreach ($rows as $row) $byParent[$row['parentId']][] = $row;
        $build = static function (int $parent) use (&$build, $byParent): array {
            return array_map(static fn (array $row) => $row + ['children' => $build($row['id'])], $byParent[$parent] ?? []);
        };
        return $build(0);
    }

    /** 新建目录：在事务内以共享锁读取上级目录，与正在删除该上级的事务串行化。 */
    public function create(array $payload): array
    {
        $id = Db::transaction(function () use ($payload): int {
            $data = $this->validate($payload);
            $this->assertSlugFree($data['slug']);
            $now = Rules::now();
            return (int) DocumentCategory::query()->insertGetId($data + ['source' => 'manual', 'created_at' => $now, 'updated_at' => $now]);
        });
        return $this->find($id);
    }

    public function update(int $id, array $payload): array
    {
        Db::transaction(function () use ($id, $payload): void {
            $row = DocumentCategory::query()->whereKey($id)->lockForUpdate()->first();
            if (!$row) throw new InvalidArgumentException('目录不存在', 404);
            $current = $this->dto($row);
            $data = $this->validate($payload + ['scope' => $current['scope'], 'slug' => $current['slug'], 'name' => $current['name'], 'parentId' => $current['parentId'], 'sortOrder' => $current['sortOrder']]);
            if ($data['parent_id'] === $id || in_array($data['parent_id'], $this->descendantIds($id), true)) {
                throw new InvalidArgumentException('不能把目录移动到它自己或它的子目录下', 422);
            }
            // 回收站中的子目录也算：否则上级改范围后，子目录恢复时会挂到另一个范围下。
            if ($data['scope'] !== $current['scope']
                && (DocumentCategory::withTrashed()->where('parent_id', $id)->exists()
                    || DocumentCategoryLink::query()->where('category_id', $id)->exists())) {
                throw new InvalidArgumentException('目录下还有子目录（含回收站中的）或文档，不能修改范围', 409);
            }
            if ($data['slug'] !== $current['slug']) $this->assertSlugFree($data['slug']);
            DocumentCategory::query()->where('id', $id)->update($data + ['updated_at' => Rules::now()]);
        });
        return $this->find($id);
    }

    /** 软删除目录：只看未删除的子目录与关联（已删除文档的关联随文档一起软删除了）。 */
    public function delete(int $id): void
    {
        Db::transaction(function () use ($id): void {
            $row = DocumentCategory::query()->whereKey($id)->lockForUpdate()->first();
            if (!$row) throw new InvalidArgumentException('目录不存在', 404);
            if (self::hasLiveChildrenOrLinks($id)) {
                throw new InvalidArgumentException('目录下还有子目录或文档，请先移走后再删除', 409);
            }
            $row->delete();
        });
    }

    /** 回收站：已删除的目录，最近删除的在前。 */
    public function trashed(): array
    {
        $rows = DocumentCategory::onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id')->get();
        return ['list' => $rows->map(fn ($row): array => $this->dto($row) + ['deletedAt' => (string) $row->getRawOriginal('deleted_at')])->all()];
    }

    /**
     * 从回收站恢复目录（409 拒绝，不自动改挂，交给管理员决定）：
     * - 上级目录已删除或不存在：先恢复上级；
     * - 上级目录已属于另一个范围（升级前的旧数据才会出现，update() 现在会拦住）：先把上级改回原范围或修改该目录；
     * - 标识被占用：RestoreConflictException。
     */
    public function restore(int $id): array
    {
        Db::transaction(static function () use ($id): void {
            $row = self::lockTrashed($id);
            $parentId = (int) $row->parent_id;
            if ($parentId > 0) {
                $parent = DocumentCategory::query()->whereKey($parentId)->sharedLock()->first();
                if (!$parent) throw new InvalidArgumentException('上级目录已删除，请先恢复上级目录', 409);
                if ((string) $parent->scope !== (string) $row->scope) {
                    throw new InvalidArgumentException('上级目录已改为其他范围，不能恢复到它下面，请先调整上级目录的范围', 409);
                }
            }
            $row->restore();
        });
        return $this->find($id);
    }

    /**
     * 彻底删除回收站中的目录，不可恢复。只物理删除已删除的关联（属于已删除的文档）；
     * 仍有未删除的关联或子目录（并发保存在删除前挂上的）时返回 409，什么都不删。
     * 以它为上级的已删除子目录改挂到顶级，恢复时不会指向不存在的上级。
     */
    public function destroy(int $id): void
    {
        Db::transaction(static function () use ($id): void {
            self::lockTrashed($id);
            if (self::hasLiveChildrenOrLinks($id)) {
                throw new InvalidArgumentException('目录下还有未删除的子目录或文档关联，不能彻底删除', 409);
            }
            DocumentCategoryLink::onlyTrashed()->where('category_id', $id)->forceDelete();
            DocumentCategory::onlyTrashed()->where('parent_id', $id)->update(['parent_id' => 0]);
            DocumentCategory::onlyTrashed()->whereKey($id)->forceDelete();
        });
    }

    /** 加锁读取（调用方已锁住目录行）：未删除的子目录或关联。 */
    private static function hasLiveChildrenOrLinks(int $id): bool
    {
        return DocumentCategory::query()->where('parent_id', $id)->lockForUpdate()->value('id') !== null
            || DocumentCategoryLink::query()->where('category_id', $id)->lockForUpdate()->value('id') !== null;
    }

    private static function lockTrashed(int $id): DocumentCategory
    {
        $row = DocumentCategory::onlyTrashed()->whereKey($id)->lockForUpdate()->first();
        if (!$row) throw new InvalidArgumentException('回收站中没有这个目录', 404);
        return $row;
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
            // 共享锁：与删除该上级的事务串行化，不会把子目录挂到正在删除的目录下（调用方在事务内）。
            $parent = DocumentCategory::query()->where('id', $parentId)->sharedLock()->first();
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
