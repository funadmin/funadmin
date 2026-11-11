<?php

declare(strict_types=1);

namespace app\document\model;

use app\common\model\concern\LaravelSoftDelete;

/** 读者反馈，同一访客对同一文档只保留一条。 */
final class DocumentFeedback extends DocumentModel
{
    use LaravelSoftDelete;

    protected $table = 'document_feedback';
    protected $hidden = ['deleted_token'];
}
