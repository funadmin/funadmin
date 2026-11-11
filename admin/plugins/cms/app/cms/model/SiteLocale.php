<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\concern\LaravelSoftDelete;
use app\cms\model\concern\{BelongsToSite,Localized,HasStatus};
final class SiteLocale extends CmsModel
{
    use BelongsToSite, Localized, HasStatus, LaravelSoftDelete;
    protected $table = 'cms_site_locale';
    protected $hidden = ['deleted_token'];
}
