<?php

declare(strict_types=1);

namespace app\admin\authentication\model;

use app\common\model\BaseModel;

/** 固定窗口尝试计数：后台登录失败按来源 IP 的 SHA-256 统计；会员注册节流使用带前缀的 SHA-256 键。 */
final class LoginAttempt extends BaseModel
{
    public $timestamps = false;
    protected $table = 'login_attempts';
    protected $guarded = [];
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;
}
