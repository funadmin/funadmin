<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\BaseModel;

/** 已使用过的会员刷新令牌，用于识别重放。 */
final class MemberApiSessionUsed extends BaseModel
{
    public $timestamps = false;
    protected $table = 'member_api_session_used';
    protected $guarded = [];
    protected $primaryKey = 'refresh_hash';
    protected $keyType = 'string';
    public $incrementing = false;
}
