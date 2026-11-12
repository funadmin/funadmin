<?php
declare(strict_types=1);
namespace app\qa\model;
use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;
/** 问题的问答状态（采纳的回答、是否关闭）；问题正文在 CMS 会员投稿表。id 与投稿 id 相同（不自增）。软删除只写 deleted_at（表上没有 deleted_token）。 */
final class QaQuestion extends BaseModel
{
    use LaravelSoftDelete;
    public $timestamps = false;
    protected $table = 'qa_question';
    protected $guarded = [];
    public $incrementing = false;
    /** 软删除写 deleted_at 时取 UTC，与 Input::now() 写入的其他问答时间一致。 */
    public function freshTimestamp(): \Illuminate\Support\Carbon { return \Illuminate\Support\Carbon::now('UTC'); }
}
