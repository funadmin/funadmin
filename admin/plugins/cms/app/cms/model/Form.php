<?php
declare(strict_types=1);
namespace app\cms\model;
use app\cms\model\concern\{BelongsToSite,HasStatus};
final class Form extends CmsModel
{
    use BelongsToSite, HasStatus;
    protected $table = 'cms_form';
}
