<?php

declare(strict_types=1);

namespace app\admin\authentication\model;

use app\common\model\BaseModel;

/** 后台登录失败计数，按来源 IP 的 SHA-256 统计。 */
final class LoginAttempt extends BaseModel
{
    public $timestamps = false;
    protected $table = 'login_attempts';
    protected $guarded = [];
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;
}
