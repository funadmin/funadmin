<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\concern\LaravelSoftDelete;
use app\cms\model\concern\{BelongsToSite,Localized,HasStatus};
final class TemplateTag extends CmsModel
{
    use BelongsToSite, Localized, HasStatus, LaravelSoftDelete;
    protected $table = 'cms_template_tag';
}
