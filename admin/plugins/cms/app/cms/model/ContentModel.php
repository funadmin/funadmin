<?php
declare(strict_types=1);
namespace app\cms\model;
use app\cms\model\concern\HasStatus;
final class ContentModel extends CmsModel
{
    use HasStatus;
    protected $table = 'cms_content_model';
}
