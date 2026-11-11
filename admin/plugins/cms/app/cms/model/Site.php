<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\concern\LaravelSoftDelete;
use app\cms\model\concern\HasStatus;
final class Site extends CmsModel
{
    use HasStatus, LaravelSoftDelete;
    protected $table = 'cms_site';
    protected $hidden = ['deleted_token'];
}
