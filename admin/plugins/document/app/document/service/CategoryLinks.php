<?php

declare(strict_types=1);

namespace app\document\service;

use app\document\model\{Document, DocumentCategory, DocumentCategoryLink};
use Illuminate\Database\Query\Expression;

/**
 * 文档与目录的关联行。
 *
 * - 保存按差异同步：只软删除移除的关联、只插入缺少的关联，未变化的行不动；读取现有集合前先锁住文档行，
 *   同一文档的并发保存串行化（调用方负责事务）。
 * - 删除文档：关联写入与文档完全相同的 deleted_at（同一事务、同一个值）。
 * - 恢复文档：只恢复 deleted_at 与文档删除时间相同的关联，也就是这次删除带走的；编辑时移除的关联更早就已删除，保持删除。
 *   目录已删除、目录换了范围、或已有相同的有效关联时跳过。
 * - 彻底删除文档：物理删除它的全部关联（含已软删除的）。
 */
final class CategoryLinks
{
    /** @param list<int> $categoryIds */
    public static function sync(int $documentId, array $categoryIds): void
    {
        Document::withTrashed()->whereKey($documentId)->lockForUpdate()->first();
        $wanted = array_fill_keys(array_values(array_unique($categoryIds)), true);
        $kept = [];
        $removed = [];
        foreach (DocumentCategoryLink::query()->where('document_id', $documentId)->orderBy('id')->get(['id', 'category_id']) as $row) {
            $categoryId = (int) $row->category_id;
            if (!isset($wanted[$categoryId]) || isset($kept[$categoryId])) {
                $removed[] = (int) $row->id;
                continue;
            }
            $kept[$categoryId] = true;
        }
        if ($removed !== []) DocumentCategoryLink::query()->whereKey($removed)->delete();
        $inserts = [];
        foreach (array_keys($wanted) as $categoryId) {
            if (!isset($kept[$categoryId])) $inserts[] = ['document_id' => $documentId, 'category_id' => $categoryId];
        }
        if ($inserts !== []) DocumentCategoryLink::query()->insert($inserts);
    }

    public static function afterSoftDelete(int $documentId, string $deletedAt): void
    {
        $model = new DocumentCategoryLink();
        $columns = [$model->getDeletedAtColumn() => $deletedAt];
        if ($model->hasDeletedToken()) {
            $columns[$model->getDeletedTokenColumn()] = new Expression($model->getConnection()->getQueryGrammar()->wrap($model->getKeyName()));
        }
        DocumentCategoryLink::query()->where('document_id', $documentId)->update($columns);
    }

    /** @return int 因目录已删除或不属于文档范围而未恢复的关联数 */
    public static function afterRestore(int $documentId, string $scope, ?string $deletedAt): int
    {
        if ($deletedAt === null || $deletedAt === '') return 0;
        $rows = DocumentCategoryLink::onlyTrashed()->where('document_id', $documentId)->where('deleted_at', $deletedAt)->orderBy('id')->get();
        if ($rows->isEmpty()) return 0;
        $live = array_flip(array_map('intval', DocumentCategory::query()->whereKey($rows->pluck('category_id')->all())->where('scope', $scope)->pluck('id')->all()));
        $skipped = 0;
        foreach ($rows as $row) {
            $categoryId = (int) $row->category_id;
            if (!isset($live[$categoryId])) {
                $skipped++;
                continue;
            }
            if (DocumentCategoryLink::query()->where('document_id', $documentId)->where('category_id', $categoryId)->exists()) continue;
            $row->restore();
        }
        return $skipped;
    }

    public static function purge(int $documentId): void
    {
        DocumentCategoryLink::withTrashed()->where('document_id', $documentId)->forceDelete();
    }
}
