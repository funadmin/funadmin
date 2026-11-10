<?php

declare(strict_types=1);

namespace app\document\model;

/** 文档与目录的多对多关联（复合主键 document_id + category_id）。 */
final class DocumentCategoryLink extends DocumentModel
{
    protected $table = 'document_category_links';
    protected $primaryKey = null;
    public $incrementing = false;
}
