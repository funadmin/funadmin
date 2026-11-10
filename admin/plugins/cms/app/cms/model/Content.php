<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\concern\LaravelSoftDelete;
use app\cms\model\concern\BelongsToSite;
final class Content extends CmsModel
{
    use BelongsToSite, LaravelSoftDelete;
    protected $table = 'cms_content';
}
