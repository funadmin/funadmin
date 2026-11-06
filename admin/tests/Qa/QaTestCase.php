<?php
declare(strict_types=1);
namespace Tests\Qa;
require_once __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/Cms/CmsTestCase.php';
use app\cms\service\{ContributionSchema,ContributionPolicy};
use app\qa\service\{Schema,QuestionService};
use Illuminate\Database\Schema\Blueprint;
use support\Db;
abstract class QaTestCase extends \Tests\Cms\CmsTestCase
{
    protected string $siteId;
    protected QuestionService $qa;
    protected function setUp(): void
    {
        if(Db::connection()->getDriverName()!=='sqlite'||Db::connection()->getDatabaseName()!==':memory:')throw new \RuntimeException('QA tests require SQLite :memory:');
        Db::beginTransaction();
        parent::setUp();
        self::assertTrue(class_exists(QuestionService::class),'QA question service must exist');
        self::assertTrue(class_exists(ContributionSchema::class),'Shared contribution schema must exist');
        foreach([...Schema::tables(),...ContributionSchema::tables()] as $table)Db::schema()->dropIfExists($table);
        ContributionSchema::install();Schema::install();
        Db::schema()->dropIfExists('member');
        Db::schema()->create('member',static function(Blueprint $t):void {
            $t->id();$t->string('username');$t->string('nickname')->default('');$t->string('email')->nullable();$t->string('mobile')->nullable();$t->string('password')->default('');$t->string('avatar')->default('');$t->boolean('status')->default(true);$t->softDeletes();
        });
        Db::table('member')->insert([['id'=>1,'username'=>'owner','nickname'=>'Question author','email'=>'secret@example.test'],['id'=>2,'username'=>'answerer','nickname'=>'Answer author','email'=>'private@example.test'],['id'=>3,'username'=>'other','nickname'=>'Other','email'=>'other@example.test']]);
        $this->siteId=$this->site()['id'];(new ContributionPolicy())->save($this->siteId,'1',['enabled'=>true,'model_codes'=>[]]);
        $this->qa=new QuestionService();
    }
    protected function tearDown(): void { while(Db::connection()->transactionLevel()>0)Db::rollBack();parent::tearDown(); }
    protected function ask(array $input=[]): array { return $this->qa->ask($this->siteId,'zh-cn','1',array_replace(['request_key'=>'question_'.bin2hex(random_bytes(5)),'title'=>'Question title','body_html'=>'<p>Question body</p>'],$input)); }
    protected function publish(array $submission): array { return $this->qa->review($this->siteId,'1',$submission['id'],$submission['lock_version'],'publish'); }
    protected function question(): array { return $this->publish($this->ask()); }
    protected function answer(string $question,string $key='answer_0001'): array { return $this->qa->answer($this->siteId,'zh-cn','2',$question,['request_key'=>$key,'body_html'=>'<p>Answer body</p>']); }
    protected function rejected(callable $action,int $code): void { try{$action();self::fail('Expected rejection '.$code);}catch(\InvalidArgumentException $e){self::assertSame($code,$e->getCode());} }
}
