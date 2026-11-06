<?php
declare(strict_types=1);
namespace Tests\Qa;
require_once __DIR__.'/bootstrap.php';
use app\qa\service\Schema;
use support\Db;
final class SchemaTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void { if(Db::connection()->getDriverName()!=='sqlite'||Db::connection()->getDatabaseName()!==':memory:')throw new \RuntimeException('QA tests require SQLite :memory:');Db::beginTransaction(); }
    protected function tearDown(): void { Db::rollBack(); }
    public function test_question_schema_tracks_shared_submission_and_exports_prefixed_sql(): void
    {
        self::assertTrue(class_exists(Schema::class),'QA schema must exist');
        foreach(Schema::tables() as $table)Db::schema()->dropIfExists($table);
        Schema::install();
        Db::table('qa_question')->insert(['id'=>'101','site_id'=>'1','locale'=>'zh-cn','created_at'=>'2026-11-06 00:00:00','updated_at'=>'2026-11-06 00:00:00']);
        $row=(array)Db::table('qa_question')->where('id','101')->first();
        self::assertSame(0,(int)$row['closed']);self::assertSame(0,(int)$row['lock_version']);self::assertNull($row['accepted_answer_id']);
        self::assertStringContainsString('`fun_qa_question`',Schema::mysql());
        self::assertSame(Schema::mysql(),file_get_contents(dirname(__DIR__,2).'/plugins/qa/database/migrations/001_qa.sql'));
    }
}
