<?php

declare(strict_types=1);

namespace app\admin\authentication\model;

use app\common\model\BaseModel;

/** 管理员一次性令牌（只保存 SHA-256）。 */
final class AdminAuthToken extends BaseModel
{
    public $timestamps = false;
    protected $table = 'admin_auth_tokens';
    protected $guarded = [];
    protected $primaryKey = 'token_hash';
    protected $keyType = 'string';
    public $incrementing = false;
}
