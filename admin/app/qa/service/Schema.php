<?php
declare(strict_types=1);
namespace app\qa\service;
use Illuminate\Database\Schema\{Blueprint,Builder};
use Illuminate\Database\MySqlConnection;
use support\Db;
final class Schema
{
    public static function tables(): array { return ['qa_question']; }
    public static function install(): void { self::create(Db::schema()); }
    public static function mysql(): string
    {
        $connection=new MySqlConnection(static fn()=>throw new \RuntimeException('Schema export must not connect'),'', 'fun_', ['driver'=>'mysql','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','engine'=>'InnoDB']);
        $queries=$connection->pretend(static fn()=>self::create($connection->getSchemaBuilder()));
        return "-- QA schema; generated from app/qa/service/Schema.php. Prefix rewritten by host migration runner.\n".implode(";\n",array_map(static fn($q)=>$q['query'],$queries)).";\n";
    }
    private static function create(Builder $s): void
    {
        $s->create('qa_question',static function(Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary();$t->unsignedBigInteger('site_id');$t->string('locale',20);$t->unsignedBigInteger('accepted_answer_id')->nullable();$t->boolean('closed')->default(false);$t->unsignedInteger('lock_version')->default(0);$t->timestamps();$t->index(['site_id','locale','id']);
        });
    }
}
