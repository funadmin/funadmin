<?php
declare(strict_types=1);
namespace app\qa\service;
use Illuminate\Database\Schema\{Blueprint,Builder};
use Illuminate\Database\MySqlConnection;
use support\Db;
final class Schema
{
    public static function tables(): array { return ['qa_question']; }
    /**
     * 按迁移顺序建表：基线（001）+ 软删除（002）。002 是按 information_schema 守卫的手写 PREPARE 迁移，无法由蓝图导出；
     * softDelete() 是它的等价实现，MySQL 上的一致性见 tests/Console/MySqlQaSoftDeleteUpgradeTest.php。
     */
    public static function install(): void { self::create(Db::schema());self::softDelete(Db::schema()); }
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
    private static function create(Builder $s): void
    {
        $s->create('qa_question',static function(Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary();$t->unsignedBigInteger('site_id');$t->string('locale',20);$t->unsignedBigInteger('accepted_answer_id')->nullable();$t->boolean('closed')->default(false);$t->unsignedInteger('lock_version')->default(0);$t->timestamps();$t->index(['site_id','locale','id']);
        });
    }
}
