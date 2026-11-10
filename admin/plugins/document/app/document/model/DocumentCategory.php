<?php

declare(strict_types=1);

namespace app\document\model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DocumentCategory extends DocumentModel
{
    protected $table = 'document_categories';

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_category_links', 'category_id', 'document_id');
    }
}
