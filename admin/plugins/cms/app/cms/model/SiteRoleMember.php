<?php
declare(strict_types=1);
namespace app\cms\model;
use app\cms\model\concern\BelongsToSite;
final class SiteRoleMember extends CmsModel
{
    use BelongsToSite;
    protected $table = 'cms_site_role_member';
    protected $primaryKey = null;
    public $incrementing = false;
}
