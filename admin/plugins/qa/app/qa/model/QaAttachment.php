<?php
declare(strict_types=1);
namespace app\qa\model;
use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;
/**
 * 会员随问题或回答上传的附件（1.3.0）。submission_id 为所属的问题或回答投稿 id，question_id 为所在问题。
 * 文件私有存储，path 相对附件根目录；软删除只写 deleted_at（表上没有 deleted_token），与所属问题或回答同一时间删除、一起恢复。
 */
final class QaAttachment extends BaseModel
{
    use LaravelSoftDelete;
    public $timestamps = false;
    protected $table = 'qa_attachment';
    protected $guarded = [];
    /** 软删除写 deleted_at 时取 UTC，与 Input::now() 写入的其他问答时间一致。 */
    public function freshTimestamp(): \Illuminate\Support\Carbon { return \Illuminate\Support\Carbon::now('UTC'); }
}
