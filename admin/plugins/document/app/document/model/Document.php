<?php

declare(strict_types=1);

namespace app\document\model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 文档：草稿正文在本表，已发布内容见 DocumentRevision。deleted_at 为软删除标记（服务显式写入）。 */
final class Document extends DocumentModel
{
    protected $table = 'documents';

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(DocumentCategory::class, 'document_category_links', 'document_id', 'category_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class, 'document_id');
    }

    /** 未删除的文档。 */
    public function scopeAlive(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('deleted_at'));
    }
}
