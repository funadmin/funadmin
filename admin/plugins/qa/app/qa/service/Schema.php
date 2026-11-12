<?php
declare(strict_types=1);
namespace app\qa\service;
use Illuminate\Database\Schema\{Blueprint,Builder};
use Illuminate\Database\MySqlConnection;
use support\Db;
use app\cms\model\MemberSubmission;
use app\qa\model\QaAnswer;
final class Schema
{
    public static function tables(): array { return ['qa_question','qa_answer','qa_attachment']; }
    /**
     * 按迁移顺序建表：基线（001）+ 软删除（002）+ 后台管理（003）+ 会员附件（004）。002–004 是手写迁移，无法由蓝图导出；
     * softDelete()、questionAdmin()、attachments() 是它们的等价实现，MySQL 上的一致性见 tests/Console/MySqlQaSoftDeleteUpgradeTest.php、
     * tests/Console/MySqlQaQuestionAdminUpgradeTest.php 与 tests/Console/MySqlQaAttachmentUpgradeTest.php。
     */
    public static function install(): void { self::create(Db::schema());self::softDelete(Db::schema());self::questionAdmin(Db::schema());self::attachments(Db::schema()); }
    public static function mysql(): string
    {
        $connection=new MySqlConnection(static fn()=>throw new \RuntimeException('Schema export must not connect'),'', 'fun_', ['driver'=>'mysql','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','engine'=>'InnoDB']);
        $queries=$connection->pretend(static fn()=>self::create($connection->getSchemaBuilder()));
        return "-- QA schema; generated from app/qa/service/Schema.php. Prefix rewritten by host migration runner.\n".implode(";\n",array_map(static fn($q)=>$q['query'],$queries)).";\n";
    }
    /** 002：deleted_at 与索引；没有唯一索引，不加 deleted_token；主键（复用投稿 id）不变。 */
    private static function softDelete(Builder $s): void
    {
        $s->table('qa_question',static function(Blueprint $t): void { $t->dateTime('deleted_at')->nullable();$t->index('deleted_at','idx_qa_question_deleted_at'); });
    }
    /**
     * 003（1.2.0）：问题置顶 pinned 与索引；回答状态表 qa_answer（主键复用 CMS 回答投稿 id，不自增；hidden 为后台隐藏；
     * 软删除只用 deleted_at，没有唯一索引所以不加 deleted_token）。与 003 相同：qa_answer 在本次执行前不存在时才为已有的
     * CMS 回答（问题有问答行的）补状态行；表已存在（含被彻底删除到空表）时重复执行不补，不会让已彻底删除的回答回来。可以重复执行。
     */
    public static function questionAdmin(?Builder $s=null): void
    {
        $s??=Db::schema();
        // 与 003 一样分别检查列与索引，任何一项缺失都只补那一项。
        if(!$s->hasColumn('qa_question','pinned'))$s->table('qa_question',static function(Blueprint $t): void { $t->boolean('pinned')->default(false); });
        if(!$s->hasIndex('qa_question','idx_qa_question_pinned'))$s->table('qa_question',static function(Blueprint $t): void { $t->index(['site_id','locale','pinned','id'],'idx_qa_question_pinned'); });
        $created=!$s->hasTable('qa_answer');
        if(!$created)return;
        $s->create('qa_answer',static function(Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary();$t->unsignedBigInteger('question_id');$t->unsignedBigInteger('site_id');$t->string('locale',20);$t->boolean('hidden')->default(false);$t->timestamps();$t->dateTime('deleted_at')->nullable();
            $t->index(['question_id','id'],'idx_qa_answer_question');$t->index('deleted_at','idx_qa_answer_deleted_at');
        });
        if(!$s->hasTable('cms_member_submission'))return;
        // 与 003 相同：包括 CMS 已删除的回答投稿（状态行只表示问答侧状态，读取时再按 CMS 是否删除过滤）。
        // 写入与来源查询都从模型开始（QaAnswer、MemberSubmission::withTrashed）；补行要在库内一次性 INSERT ... SELECT，
        // hidden 列取常量 0 只能用 selectRaw 表达，insertUsing 需要底层查询构造器，所以来源查询 toBase()。
        QaAnswer::query()->insertUsing(['id','question_id','site_id','locale','hidden','created_at','updated_at'],
            MemberSubmission::withTrashed()->from('cms_member_submission as s')->join('qa_question as q',static function($j): void {$j->on('q.id','=','s.parent_id')->on('q.site_id','=','s.site_id')->on('q.locale','=','s.locale');})
                ->where('s.kind','answer')->select(['s.id','s.parent_id','s.site_id','s.locale'])->selectRaw('0')->addSelect(['s.created_at','s.updated_at'])->toBase());
    }
    /**
     * 004（1.3.0）：会员附件表 qa_attachment。submission_id 是附件所属的问题或回答投稿，question_id 是所在问题（问题附件两者相同）。
     * 文件私有存储（CMS 表单附件存储，根目录 runtime/plugins-data/qa/attachments），path 相对该根目录。
     * 软删除只用 deleted_at（没有唯一索引，不加 deleted_token），与所属问题或回答一起删除、一起恢复。表已存在时不做任何事。
     */
    public static function attachments(?Builder $s=null): void
    {
        $s??=Db::schema();
        if($s->hasTable('qa_attachment'))return;
        $s->create('qa_attachment',static function(Blueprint $t): void {
            $t->id();$t->unsignedBigInteger('site_id');$t->unsignedBigInteger('question_id');$t->unsignedBigInteger('submission_id');$t->unsignedBigInteger('member_id');
            $t->string('original_name',255);$t->string('mime',100);$t->unsignedBigInteger('size');$t->char('sha256',64);$t->string('path',255);$t->timestamps();$t->dateTime('deleted_at')->nullable();
            $t->index(['site_id','submission_id','id'],'idx_qa_attachment_submission');$t->index('question_id','idx_qa_attachment_question');$t->index('deleted_at','idx_qa_attachment_deleted_at');
        });
    }
    private static function create(Builder $s): void
    {
        $s->create('qa_question',static function(Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary();$t->unsignedBigInteger('site_id');$t->string('locale',20);$t->unsignedBigInteger('accepted_answer_id')->nullable();$t->boolean('closed')->default(false);$t->unsignedInteger('lock_version')->default(0);$t->timestamps();$t->index(['site_id','locale','id']);
        });
    }
}
