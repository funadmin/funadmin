<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\admin\model\BackendModel;

/** AI 会话自定义分组。 */
final class AiConversationGroup extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_conversation_group';

    protected $fillable = ['admin_id', 'name'];
    protected $casts = ['id' => 'integer', 'admin_id' => 'integer'];
}
