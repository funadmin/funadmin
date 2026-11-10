<?php
declare(strict_types=1);
namespace app\cms\model\concern;
use Illuminate\Database\Eloquent\Builder;
trait Localized
{
    /** 按语言过滤，等价于 where('locale', $locale)。 */
    public function scopeForLocale(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale);
    }
}
