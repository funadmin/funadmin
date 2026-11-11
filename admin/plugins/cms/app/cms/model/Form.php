<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\concern\LaravelSoftDelete;
use app\cms\model\concern\{BelongsToSite,HasStatus};
final class Form extends CmsModel
{
    use BelongsToSite, HasStatus, LaravelSoftDelete;
    protected $table = 'cms_form';
    protected $hidden = ['deleted_token'];
}
