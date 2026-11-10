<?php
declare(strict_types=1);
namespace app\cms\model\concern;
use Illuminate\Database\Eloquent\Builder;
trait BelongsToSite
{
    /** 按站点过滤，等价于 where('site_id', $siteId)。 */
    public function scopeForSite(Builder $q, string $siteId): Builder
    {
        return $q->where('site_id', $siteId);
    }
}
