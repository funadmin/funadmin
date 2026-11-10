<?php
declare(strict_types=1);
namespace app\cms\model;
use app\cms\model\concern\{BelongsToSite,Localized};
final class FormSubmission extends CmsModel
{
    use BelongsToSite, Localized;
    protected $table = 'cms_form_submission';
}
