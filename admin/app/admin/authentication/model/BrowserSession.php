<?php

declare(strict_types=1);

namespace app\admin\authentication\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

/** 后台浏览器会话（只保存会话 Cookie 的 SHA-256）。 */
final class BrowserSession extends BaseModel
{
    use LaravelSoftDelete;

    public $timestamps = false;
    protected $table = 'browser_sessions';
    protected $guarded = [];
}
