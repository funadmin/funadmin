<?php

declare(strict_types=1);

namespace app\document\model;

/** 已发布修订，按 (document_id, revision_no) 唯一。 */
final class DocumentRevision extends DocumentModel
{
    protected $table = 'document_revisions';
}
