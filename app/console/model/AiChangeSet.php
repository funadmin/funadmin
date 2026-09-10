<?php

declare(strict_types=1);

namespace app\console\model;

use app\common\model\concern\LaravelSoftDelete;

/** AI 开发助手变更集。 */
final class AiChangeSet extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_change_set';

    protected $json = ['manifest', 'summary', 'base_file_hashes', 'test_result', 'security_result'];

    protected $jsonAssoc = true;
}
