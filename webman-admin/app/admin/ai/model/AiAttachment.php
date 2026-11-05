<?php

declare(strict_types=1);
namespace app\admin\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\common\model\BaseModel;

/** 私有附件；删除标记与绑定状态仅由仓储事务更新。 */
final class AiAttachment extends BaseModel
{
    use LaravelSoftDelete;
    protected $name = 'ai_attachment';
    protected $fillable = ['conversation_id', 'admin_id', 'message_id', 'storage_path', 'expires_at', 'kind', 'name', 'mime', 'size', 'width', 'height', 'sha256'];
}
