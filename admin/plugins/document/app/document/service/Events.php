<?php

declare(strict_types=1);

namespace app\document\service;

use app\document\model\DocumentEvent;

/** 文档生命周期事件（创建、保存、发布、归档、恢复、删除）。 */
final class Events
{
    public static function record(int $documentId, string $action, int $revisionNo = 0, array $metadata = []): void
    {
        DocumentEvent::query()->insert([
            'document_id' => $documentId, 'action' => $action, 'revision_no' => $revisionNo,
            'actor_id' => Rules::actor(),
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'created_at' => Rules::now(),
        ]);
    }
}
