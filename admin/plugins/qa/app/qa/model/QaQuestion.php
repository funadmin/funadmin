<?php
declare(strict_types=1);
namespace app\qa\model;
use app\common\model\BaseModel;
/** 问题的问答状态（采纳的回答、是否关闭）；问题正文在 CMS 会员投稿表。id 与投稿 id 相同。 */
final class QaQuestion extends BaseModel
{
    public $timestamps = false;
    protected $table = 'qa_question';
    protected $guarded = [];
    public $incrementing = false;
}
