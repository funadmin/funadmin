<?php

declare(strict_types=1);

namespace app\document\model;

use app\common\model\concern\LaravelSoftDelete;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 文档目录（软删除）。 */
final class DocumentCategory extends DocumentModel
{
    use LaravelSoftDelete;

    protected $table = 'document_categories';
    protected $hidden = ['deleted_token'];

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_category_links', 'category_id', 'document_id')->wherePivotNull('deleted_at');
    }
}
