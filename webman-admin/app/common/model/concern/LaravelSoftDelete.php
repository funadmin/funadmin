<?php

declare(strict_types=1);

namespace app\common\model\concern;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 使用 Laravel 风格 deleted_at 字段实现软删除。
 */
trait LaravelSoftDelete
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
}
