<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\BaseModel;

/** 已消费 SSE ticket nonce。 */
final class AiStreamNonce extends BaseModel
{
    protected $name = 'ai_stream_nonce';
    /** 表中只有 created_at（见基线），不能让 Eloquent 写 updated_at。 */
    public const UPDATED_AT = null;

    protected $fillable = ['nonce_hash', 'expires_at', 'consumed_at'];
}
