<?php

declare(strict_types=1);

namespace app\document\model;

use app\common\model\concern\LaravelSoftDelete;

/** 文档与目录的多对多关联（自增 id；唯一键 document_id + category_id + deleted_token）。按差异同步，随文档一起软删除。 */
final class DocumentCategoryLink extends DocumentModel
{
    use LaravelSoftDelete;

    protected $table = 'document_category_links';
    protected $hidden = ['deleted_token'];
}
