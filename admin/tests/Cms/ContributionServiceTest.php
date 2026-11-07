<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{ContributionSchema,ContributionPolicy,ContributionService,ContentService,Input,PermissionCatalog};
use Illuminate\Database\Schema\Blueprint;
use support\Db;
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class ContributionServiceTest extends CmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::assertTrue(class_exists(ContributionSchema::class),'Shared contribution schema must exist');
        Db::schema()->dropIfExists('cms_member_submission');ContributionSchema::install();
        if(!Db::schema()->hasTable('member'))Db::schema()->create('member',static function(Blueprint $t):void{$t->id();$t->string('username')->default('');$t->string('nickname')->default('');$t->boolean('status')->default(true);$t->softDeletes();});
        Db::table('member')->updateOrInsert(['id'=>701],['username'=>'contributor','nickname'=>'Contributor','status'=>1,'deleted_at'=>null]);
        Db::table('member')->updateOrInsert(['id'=>702],['username'=>'other','nickname'=>'Other','status'=>1,'deleted_at'=>null]);
    }
    private function enabled(): string
    {
        $s=$this->site()['id'];(new ContributionPolicy())->save($s,'1',['enabled'=>true,'model_codes'=>['article']]);return $s;
    }
    private function payload(array $extra=[]): array {return array_replace(['request_key'=>'request_1234','title'=>'A title','body_html'=>'<p onclick="bad()">Hello<script>bad()</script></p>','model_code'=>'article','data'=>['source'=>'Member']],$extra);}
    private function failure(int $code,callable $call): void
    {
        try{$call();self::fail('Expected domain rejection '.$code);}catch(\InvalidArgumentException $e){self::assertSame($code,$e->getCode());}
    }
    public function test_schema_migration_and_permission_ceiling_are_exact(): void
    {
        self::assertSame(['cms_member_submission'],ContributionSchema::tables());
        self::assertStringEndsWith(ContributionSchema::mysql(),file_get_contents(dirname(__DIR__,2).'/plugins/cms/database/migrations/001_cms.sql'));
        self::assertSame(['cms:contributions:review'],PermissionCatalog::ceilings('cms:submission:review'));
        self::assertStringContainsString('`fun_cms_member_submission`',ContributionSchema::mysql());
    }
    public function test_policy_defaults_are_private_and_allowlist_validated(): void
    {
        $s=$this->site()['id'];$p=new ContributionPolicy();self::assertFalse($p->read($s)['enabled']);self::assertSame([],$p->read($s)['model_codes']);
        $context=$p->context();self::assertTrue($context['ready']);self::assertArrayNotHasKey('settings',$context['sites'][0]);self::assertArrayNotHasKey('domains',$context['sites'][0]);
        $this->failure(422,fn()=>$p->save($s,'1',['enabled'=>'true','model_codes'=>[]]));
        $this->failure(422,fn()=>$p->save($s,'1',['enabled'=>true,'model_codes'=>['unknown']]));
        $this->failure(403,fn()=>$p->save($s,'999',['enabled'=>true,'model_codes'=>['article']]));
    }
    public function test_member_model_fields_are_safe_and_schema_changes_cannot_enable_attachment_references(): void
    {
        $s=$this->enabled();$policy=new ContributionPolicy();$article=array_values(array_filter($policy->context()['sites'][0]['models'],fn($m)=>$m['code']==='article'))[0];
        self::assertSame('source',$article['fields'][0]['name']);
        try {
            $policy->save($s,'1',['enabled'=>true,'model_codes'=>['download']]);
            self::fail('Models containing attachment fields must remain unavailable');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(422,$e->getCode());
            self::assertSame('投稿模型必须为本站启用且不含附件字段的模型',$e->getMessage());
        }
        $model=Db::table('cms_content_model')->where('code','article')->first();
        (new \app\cms\service\ModelService())->save(['id'=>(string)$model->id,'code'=>'article','name'=>'Article','fields'=>[['name'=>'photo','label'=>'Photo','type'=>'image']]]);
        $this->failure(422,fn()=>(new ContributionService())->submit($s,'zh-cn','701','content',$this->payload()));
    }
    public function test_submit_sanitizes_and_derives_identity_schema_and_state(): void
    {
        $s=$this->enabled();$service=new ContributionService();$r=$service->submit($s,'zh-cn','701','content',$this->payload(['member_id'=>'702','status'=>'published','reviewer_admin_id'=>'1','desired_path'=>'/evil','data'=>['source'=>'Member']]));
        self::assertIsString($r['id']);self::assertSame('701',$r['member_id']);self::assertSame('pending',$r['status']);self::assertNull($r['reviewer_admin_id']);self::assertSame(0,$r['lock_version']);self::assertSame([],$r['result']);
        self::assertStringNotContainsString('onclick',$r['body_html']);self::assertStringNotContainsString('<script',$r['body_html']);self::assertSame('article',$r['data']['model_code']);self::assertSame('Member',$r['data']['fields']['source']);self::assertIsString($r['data']['model_version_id']);
        self::assertSame($r,$service->get($s,$r['id']));self::assertCount(1,$service->mine($s,'zh-cn','701')['items']);self::assertSame(0,$service->mine($s,'zh-cn','702')['total']);
    }
    public function test_idempotency_precedes_quota_and_payload_conflict(): void
    {
        $s=$this->enabled();$v=new ContributionService();$r=$v->submit($s,'zh-cn','701','content',$this->payload());
        for($i=1;$i<10;$i++)$v->submit($s,'zh-cn','701','question',$this->payload(['request_key'=>'question_'.$i]));
        self::assertSame($r['id'],$v->submit($s,'zh-cn','701','content',$this->payload())['id']);
        $this->failure(409,fn()=>$v->submit($s,'zh-cn','701','content',$this->payload(['title'=>'Changed'])));
        $this->failure(429,fn()=>$v->submit($s,'zh-cn','701','question',$this->payload(['request_key'=>'question_excess'])));
    }
    public function test_member_site_locale_policy_and_parent_boundaries(): void
    {
        $s=$this->enabled();$v=new ContributionService();$other=$this->site('second','second.test')['id'];
        $this->failure(403,fn()=>$v->submit($other,'zh-cn','701','question',$this->payload()));
        $this->failure(404,fn()=>$v->submit($s,'en-us','701','question',$this->payload()));
        Db::table('member')->where('id',702)->update(['status'=>0]);$this->failure(401,fn()=>$v->submit($s,'zh-cn','702','question',$this->payload()));
        $q=$v->submit($s,'zh-cn','701','question',$this->payload());$this->failure(422,fn()=>$v->submit($s,'zh-cn','701','answer',$this->payload(['request_key'=>'answer_1234']),$q['id']));
        $v->review($s,'1',$q['id'],0,'publish');$a=$v->submit($s,'zh-cn','701','answer',$this->payload(['request_key'=>'answer_1234']),$q['id']);self::assertSame($q['id'],$a['parent_id']);
        $this->failure(404,fn()=>$v->get($other,$q['id']));
        $this->failure(422,fn()=>$v->submit($s,'zh-cn','701','content',$this->payload(['request_key'=>'invalid_data','model_code'=>'page'])));
    }
    public function test_review_and_withdraw_enforce_owner_cas_reason_and_transaction(): void
    {
        $s=$this->enabled();$v=new ContributionService();$r=$v->submit($s,'zh-cn','701','question',$this->payload());
        $this->failure(403,fn()=>$v->withdraw($s,'702',$r['id'],0));$this->failure(403,fn()=>$v->review($s,'999',$r['id'],0,'publish'));
        $this->failure(422,fn()=>$v->review($s,'1',$r['id'],0,'reject'));
        try{$v->review($s,'1',$r['id'],0,'publish','',static fn()=>throw new \RuntimeException('publisher failed'));self::fail('Expected publisher failure');}catch(\RuntimeException){}
        self::assertSame('pending',$v->get($s,$r['id'])['status']);
        $r=$v->review($s,'1',$r['id'],0,'reject','Reason');self::assertSame(1,$r['lock_version']);
        $this->failure(409,fn()=>$v->withdraw($s,'701',$r['id'],0));$r=$v->withdraw($s,'701',$r['id'],1);self::assertSame('withdrawn',$r['status']);
        $this->failure(409,fn()=>$v->review($s,'1',$r['id'],2,'publish'));
    }
    public function test_review_content_publishes_with_admin_actor_and_hide_removes_public_content(): void
    {
        $s=$this->enabled();$v=new ContributionService();$r=$v->submit($s,'zh-cn','701','content',$this->payload());
        $r=$v->reviewContent($s,'1',$r['id'],0,'publish');self::assertSame('published',$r['status']);self::assertSame('1',$r['reviewer_admin_id']);self::assertSame('/community/articles/'.$r['id'],$r['result']['url']);self::assertIsString($r['result']['content_id']);
        self::assertSame('1',(string)Db::table('cms_content')->where('id',$r['result']['content_id'])->value('created_by'));
        $content=new ContentService();self::assertNotNull($content->publicDetail($s,$r['result']['locale_id']));
        $this->failure(409,fn()=>$v->withdraw($s,'701',$r['id'],1));
        $r=$v->reviewContent($s,'1',$r['id'],1,'hide','Violation');self::assertSame('hidden',$r['status']);self::assertNull($content->publicDetail($s,$r['result']['locale_id']));
    }
}
