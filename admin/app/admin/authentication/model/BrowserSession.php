<?php

declare(strict_types=1);

namespace app\admin\authentication\model;

use app\common\model\BaseModel;

/** 后台浏览器会话（只保存会话 Cookie 的 SHA-256）。 */
final class BrowserSession extends BaseModel
{
    public $timestamps = false;
    protected $table = 'browser_sessions';
    protected $guarded = [];
    protected $primaryKey = 'session_hash';
    protected $keyType = 'string';
    public $incrementing = false;
}
