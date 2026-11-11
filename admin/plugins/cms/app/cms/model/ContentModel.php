<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\concern\LaravelSoftDelete;
use app\cms\model\concern\HasStatus;
final class ContentModel extends CmsModel
{
    use HasStatus, LaravelSoftDelete;
    protected $table = 'cms_content_model';
    protected $hidden = ['deleted_token'];
}
