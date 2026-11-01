<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\admin\model\BackendModel;

/** 已消费 SSE ticket nonce。 */
final class AiStreamNonce extends BackendModel
{
    protected $name = 'ai_stream_nonce';
    protected $updateTime = false;
}
