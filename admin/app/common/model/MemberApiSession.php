<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

/** 会员 API 刷新会话。 */
final class MemberApiSession extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    public $timestamps = false;
    protected $table = 'member_api_session';
    protected $guarded = [];
}
