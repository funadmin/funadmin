<?php
declare(strict_types=1);
namespace app\cms\model;
use app\cms\model\concern\BelongsToSite;
final class SiteAdmin extends CmsModel
{
    use BelongsToSite;
    protected $table = 'cms_site_admin';
    protected $primaryKey = null;
    public $incrementing = false;
}
