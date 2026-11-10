<?php
declare(strict_types=1);
namespace app\cms\model\concern;
use Illuminate\Database\Eloquent\Builder;
trait HasStatus
{
    /** 只取启用的行，等价于 where('status', 1)。仅用于 status 为整数的表。 */
    public function scopeEnabled(Builder $q): Builder
    {
        return $q->where('status', 1);
    }
}
