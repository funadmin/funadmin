<?php
declare(strict_types=1);
namespace app\cms\model;
use app\cms\model\concern\{BelongsToSite,Localized,HasStatus};
final class CategoryLocale extends CmsModel
{
    use BelongsToSite, Localized, HasStatus;
    protected $table = 'cms_category_locale';
}
