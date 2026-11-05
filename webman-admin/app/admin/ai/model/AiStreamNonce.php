<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\BaseModel;

/** 已消费 SSE ticket nonce。 */
final class AiStreamNonce extends BaseModel
{
    protected $name = 'ai_stream_nonce';
    protected $updateTime = false;
}
