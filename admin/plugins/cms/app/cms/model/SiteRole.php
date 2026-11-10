<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\concern\LaravelSoftDelete;
use app\cms\model\concern\{BelongsToSite,HasStatus};
final class SiteRole extends CmsModel
{
    use BelongsToSite, HasStatus, LaravelSoftDelete;
    protected $table = 'cms_site_role';
}
