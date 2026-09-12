<?php

declare(strict_types=1);

namespace app\console\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\console\model\BackendModel;

/** AI 会话自定义分组。 */
final class AiConversationGroup extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_conversation_group';
}
