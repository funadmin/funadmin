<?php

declare(strict_types=1);
namespace app\admin\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\admin\model\BackendModel;

/** 私有附件；删除标记与绑定状态仅由仓储事务更新。 */
final class AiAttachment extends BackendModel
{
    use LaravelSoftDelete;
    protected $name = 'ai_attachment';
}
