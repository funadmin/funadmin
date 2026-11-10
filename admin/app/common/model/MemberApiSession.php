<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\BaseModel;

/** 会员 API 刷新会话。 */
final class MemberApiSession extends BaseModel
{
    public $timestamps = false;
    protected $table = 'member_api_session';
    protected $guarded = [];

}
