<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\concern\LaravelSoftDelete;
use app\cms\model\concern\{BelongsToSite,Localized};
final class ContentLocale extends CmsModel
{
    use BelongsToSite, Localized, LaravelSoftDelete;
    protected $table = 'cms_content_locale';
    protected $hidden = ['deleted_token'];
}
