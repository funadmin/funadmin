<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\concern\LaravelSoftDelete;
use app\cms\model\concern\BelongsToSite;
final class SiteAdmin extends CmsModel
{
    use BelongsToSite, LaravelSoftDelete;
    protected $table = 'cms_site_admin';
    protected $hidden = ['deleted_token'];
}
