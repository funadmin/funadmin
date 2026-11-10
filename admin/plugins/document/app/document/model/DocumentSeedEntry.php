<?php

declare(strict_types=1);

namespace app\document\model;

/** 已写入过的内置条目登记，删除或改名后不再补回。 */
final class DocumentSeedEntry extends DocumentModel
{
    protected $table = 'document_seed_entries';
}
