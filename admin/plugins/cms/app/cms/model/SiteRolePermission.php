<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\concern\LaravelSoftDelete;
use app\cms\model\concern\BelongsToSite;
final class SiteRolePermission extends CmsModel
{
    use BelongsToSite, LaravelSoftDelete;
    protected $table = 'cms_site_role_permission';
    protected $hidden = ['deleted_token'];
}
