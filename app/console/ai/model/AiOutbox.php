<?php

declare(strict_types=1);

namespace app\console\ai\model;

use app\console\model\BackendModel;

/** AI 可靠投递 outbox。 */
final class AiOutbox extends BackendModel
{
    protected $name = 'ai_outbox';
    protected $json = ['payload'];
    protected $jsonAssoc = true;
}
