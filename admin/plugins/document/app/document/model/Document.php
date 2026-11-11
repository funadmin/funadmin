<?php

declare(strict_types=1);

namespace app\document\model;

use app\common\model\concern\LaravelSoftDelete;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 文档：草稿正文在本表，已发布内容见 DocumentRevision。软删除（删除时目录关联一起软删除，见 CategoryLinks）。 */
final class Document extends DocumentModel
{
    use LaravelSoftDelete;

    protected $table = 'documents';
    protected $hidden = ['deleted_token'];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(DocumentCategory::class, 'document_category_links', 'document_id', 'category_id')->wherePivotNull('deleted_at');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class, 'document_id');
    }
}
