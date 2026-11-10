<?php
declare(strict_types=1);
namespace app\cms\model;
use app\cms\model\concern\BelongsToSite;
final class SiteAuthAudit extends CmsModel
{
    use BelongsToSite;
    protected $table = 'cms_site_auth_audit';
}
