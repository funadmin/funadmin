<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\concern\LaravelSoftDelete;
use app\cms\model\concern\{BelongsToSite,Localized};
final class MemberSubmission extends CmsModel
{
    use BelongsToSite, Localized, LaravelSoftDelete;
    protected $table = 'cms_member_submission';
    protected $hidden = ['deleted_token'];
}
