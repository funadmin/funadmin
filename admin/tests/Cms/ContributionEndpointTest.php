<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\controller\{MemberEndpoint,MemberContent};
use app\cms\service\{ContributionSchema,ContributionPolicy,ContributionService};
use support\{Context,Request,Db};
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class ContributionEndpointTest extends CmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();self::assertTrue(class_exists(MemberContent::class),'Member contribution controller must exist');
        ContributionSchema::install();
        Db::schema()->dropIfExists('member');Db::schema()->create('member',static function($t):void {$t->id();$t->string('username');$t->string('nickname');$t->string('password');$t->boolean('status')->default(true);$t->softDeletes();});
        Db::schema()->dropIfExists('market_token');Db::schema()->create('market_token',static function($t):void {$t->id();$t->unsignedBigInteger('member_id');$t->string('access_hash');$t->string('refresh_hash');$t->unsignedInteger('access_expires_at');$t->unsignedInteger('refresh_expires_at');$t->string('ip');$t->dateTime('created_at');$t->dateTime('revoked_at')->nullable();});
        Db::table('member')->insert(['id'=>711,'username'=>'member711','nickname'=>'Member','password'=>'unused','status'=>1]);
        Db::table('member')->insert(['id'=>712,'username'=>'member712','nickname'=>'Other','password'=>'unused','status'=>1]);
        Db::table('market_token')->insert(['member_id'=>711,'access_hash'=>hash('sha256',str_repeat('a',64)),'refresh_hash'=>hash('sha256',str_repeat('b',64)),'access_expires_at'=>time()+3600,'refresh_expires_at'=>time()+7200,'ip'=>'127.0.0.1','created_at'=>gmdate('Y-m-d H:i:s')]);
    }
    private function call(string $method,array $input=[],string $token='',bool $post=false,?string $id=null): \support\Response
    {
        $body=$post?json_encode($input):'';
        $request=new Request(($post?'POST':'GET').' /cms/member/test'.($post?'':'?'.http_build_query($input))." HTTP/1.1\r\nHost: portal.test\r\nAuthorization: Bearer ".$token."\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);
        Context::set(\Webman\Http\Request::class,$request);
        try{return $id===null?(new MemberContent())->$method():(new MemberContent())->$method($id);}finally{Context::destroy();}
    }
    private function data(\support\Response $response): array {return json_decode($response->rawBody(),true,512,JSON_THROW_ON_ERROR);}
    public function test_context_is_public_and_identity_uses_opaque_bearer_only(): void
    {
        $this->site();$r=$this->call('context');self::assertSame(200,$r->getStatusCode());self::assertTrue($this->data($r)['data']['ready']);
        self::assertSame(401,$this->call('identity',['member_id'=>'711'],'not-a-token')->getStatusCode());
        $r=$this->call('identity',['member_id'=>'712'],str_repeat('a',64));self::assertSame(['member_id'=>'711'],$this->data($r)['data']);self::assertStringContainsString('private',$r->getHeader('Cache-Control'));
        Db::table('market_token')->update(['revoked_at'=>gmdate('Y-m-d H:i:s')]);self::assertSame(401,$this->call('identity',[],str_repeat('a',64))->getStatusCode());
    }
    public function test_submit_and_withdraw_never_take_member_from_payload(): void
    {
        $site=$this->site()['id'];(new ContributionPolicy())->save($site,'1',['enabled'=>true,'model_codes'=>['article']]);
        $input=['site_id'=>$site,'locale'=>'zh-cn','member_id'=>'712','status'=>'published','request_key'=>'endpoint_123','model_code'=>'article','title'=>'Test','body_html'=>'<p>Body</p>','data'=>[]];
        $r=$this->call('submit',$input,str_repeat('a',64),true);self::assertSame(200,$r->getStatusCode());$submission=$this->data($r)['data'];self::assertSame('711',$submission['member_id']);self::assertSame('pending',$submission['status']);
        $r=$this->call('submissions',['site_id'=>$site,'locale'=>'zh-cn'],str_repeat('a',64));self::assertSame(1,$this->data($r)['data']['total']);
        $r=$this->call('withdraw',['site_id'=>$site,'lock_version'=>0,'member_id'=>'712'],str_repeat('a',64),true,$submission['id']);self::assertSame('withdrawn',$this->data($r)['data']['status']);
        self::assertSame(409,$this->call('withdraw',['site_id'=>$site,'lock_version'=>0],str_repeat('a',64),true,$submission['id'])->getStatusCode());
    }
    public function test_member_action_closes_unknown_errors_without_exception_details(): void
    {
        self::assertTrue(class_exists(MemberEndpoint::class));
        $controller=new class extends MemberEndpoint {public function probe(): \support\Response {return $this->action(static fn()=>throw new \RuntimeException('password=secret'));}};
        $r=$controller->probe();self::assertSame(500,$r->getStatusCode());self::assertStringNotContainsString('secret',$r->rawBody());self::assertStringContainsString('no-store',$r->getHeader('Cache-Control'));
    }
    public function test_route_and_permission_metadata_are_actual_attributes(): void
    {
        $class=new \ReflectionClass(MemberContent::class);$group=$class->getAttributes(\support\annotation\route\RouteGroup::class)[0]->getArguments();self::assertSame('/cms/member',$group[0]);
        self::assertSame('/identity',$class->getMethod('identity')->getAttributes(\support\annotation\route\Get::class)[0]->getArguments()[0]);
        self::assertSame('/submissions/{id}/withdraw',$class->getMethod('withdraw')->getAttributes(\support\annotation\route\Post::class)[0]->getArguments()[0]);
        $admin=new \ReflectionClass(\app\admin\plugins\cms\controller\Contributions::class);
        // 代码即权限：审核与保存设置各自推导出独立的接口权限码，不再在方法上声明权限注解。
        self::assertSame('cms:contributions:review',\app\admin\authorization\service\PermissionCode::derive($admin->getName(),'review'));
        self::assertSame('cms:contributions:save-settings',\app\admin\authorization\service\PermissionCode::derive($admin->getName(),'saveSettings'));
        self::assertSame([],$admin->getMethod('review')->getAttributes(\app\common\annotation\Permission::class));
    }
}
