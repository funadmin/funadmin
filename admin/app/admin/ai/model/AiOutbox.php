<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\BaseModel;

/** AI 可靠投递 outbox。 */
final class AiOutbox extends BaseModel
{
    protected $name = 'ai_outbox';
    protected $fillable = ['payload', 'status', 'attempts', 'available_at', 'lease_owner', 'lease_expires_at', 'dispatched_at', 'last_error'];
    protected $casts = ['payload' => 'array', 'attempts' => 'integer'];
}
