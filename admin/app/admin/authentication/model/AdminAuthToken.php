<?php

declare(strict_types=1);

namespace app\admin\authentication\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

/** 管理员一次性令牌（只保存 SHA-256）。 */
final class AdminAuthToken extends BaseModel
{
    use LaravelSoftDelete;

    public $timestamps = false;
    protected $table = 'admin_auth_tokens';
    protected $guarded = [];
}
