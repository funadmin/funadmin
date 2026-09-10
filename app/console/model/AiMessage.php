<?php

declare(strict_types=1);

namespace app\console\model;

use app\common\model\concern\LaravelSoftDelete;

/** AI 开发助手消息。 */
final class AiMessage extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_message';

    protected $json = ['content', 'metadata', 'usage'];

    protected $jsonAssoc = true;
}
