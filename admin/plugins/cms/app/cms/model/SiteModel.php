<?php
declare(strict_types=1);
namespace app\cms\model;
use app\cms\model\concern\{BelongsToSite,HasStatus};
final class SiteModel extends CmsModel
{
    use BelongsToSite, HasStatus;
    protected $table = 'cms_site_model';
    protected $primaryKey = null;
    public $incrementing = false;
}
