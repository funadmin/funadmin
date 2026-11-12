<?php
declare(strict_types=1);
namespace app\qa\service;
use Illuminate\Database\Schema\{Blueprint,Builder};
use Illuminate\Database\MySqlConnection;
use support\Db;
final class Schema
{
    public static function tables(): array { return ['qa_question','qa_answer']; }
    /**
     * 按迁移顺序建表：基线（001）+ 软删除（002）+ 后台管理（003）。002、003 是按 information_schema 守卫的手写迁移，无法由蓝图导出；
     * softDelete()、questionAdmin() 是它们的等价实现，MySQL 上的一致性见 tests/Console/MySqlQaSoftDeleteUpgradeTest.php
     * 与 tests/Console/MySqlQaQuestionAdminUpgradeTest.php。
     */
    public static function install(): void { self::create(Db::schema());self::softDelete(Db::schema());self::questionAdmin(Db::schema()); }
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
     * 软删除只用 deleted_at，没有唯一索引所以不加 deleted_token）。
     */
    private static function questionAdmin(Builder $s): void
    {
        $s->table('qa_question',static function(Blueprint $t): void { $t->boolean('pinned')->default(false);$t->index(['site_id','locale','pinned','id'],'idx_qa_question_pinned'); });
        $s->create('qa_answer',static function(Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary();$t->unsignedBigInteger('question_id');$t->unsignedBigInteger('site_id');$t->string('locale',20);$t->boolean('hidden')->default(false);$t->timestamps();$t->dateTime('deleted_at')->nullable();
            $t->index(['question_id','id'],'idx_qa_answer_question');$t->index('deleted_at','idx_qa_answer_deleted_at');
        });
    }
    private static function create(Builder $s): void
    {
        $s->create('qa_question',static function(Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary();$t->unsignedBigInteger('site_id');$t->string('locale',20);$t->unsignedBigInteger('accepted_answer_id')->nullable();$t->boolean('closed')->default(false);$t->unsignedInteger('lock_version')->default(0);$t->timestamps();$t->index(['site_id','locale','id']);
        });
    }
}
