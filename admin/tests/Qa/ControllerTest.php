<?php
declare(strict_types=1);
namespace Tests\Qa;
require_once __DIR__.'/QaTestCase.php';
use app\qa\controller\Questions;
use app\admin\plugins\qa\controller\Moderation;
use app\common\annotation\Permission;
use support\{Context,Request,Db};
use support\annotation\route\{RouteGroup,Get,Post};
final class ControllerTest extends QaTestCase
{
    protected function tearDown(): void { Context::destroy();parent::tearDown(); }
    private function call(string $class,string $action,array $input=[],array $args=[],string $token='',?string $actor=null,bool $post=false): array
    {
        $input=array_replace(['site_id'=>$this->siteId,'locale'=>'zh-cn'],$input);$body=$post?json_encode($input):'';
        $request=new Request(($post?'POST':'GET').' /qa/test'.($post?'':'?'.http_build_query($input))." HTTP/1.1\r\nHost: qa.test\r\nAuthorization: Bearer ".$token."\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);
        if($actor!==null)$request->context['admin']=['id'=>$actor];Context::set(\Webman\Http\Request::class,$request);
        try { $response=(new $class())->$action(...$args);return [$response,json_decode($response->rawBody(),true,512,JSON_THROW_ON_ERROR)]; }finally { Context::destroy(); }
    }
    public function test_question_controller_uses_opaque_bearer_and_ignores_body_identity(): void
    {
        self::assertTrue(class_exists(Questions::class),'QA member controller must exist');
        [$response,$body]=$this->call(Questions::class,'ask',['member_id'=>'1','request_key'=>'api_question','title'=>'API','body_html'=>'<p>Body</p>'],post:true);self::assertSame(401,$body['code']);
        Db::schema()->dropIfExists('market_token');Db::schema()->create('market_token',static function($t):void { $t->id();$t->unsignedBigInteger('member_id');$t->string('access_hash');$t->unsignedInteger('access_expires_at');$t->dateTime('revoked_at')->nullable(); });
        $token=str_repeat('b',64);Db::table('market_token')->insert(['member_id'=>2,'access_hash'=>hash('sha256',$token),'access_expires_at'=>time()+300]);
        [$response,$body]=$this->call(Questions::class,'ask',['member_id'=>'1','status'=>'published','request_key'=>'api_question','title'=>'API','body_html'=>'<p>Body</p>'],token:$token,post:true);
        self::assertSame(200,$body['code']);self::assertSame('2',$body['data']['member_id']);self::assertSame('pending',$body['data']['status']);
        [$response,$body]=$this->call(Questions::class,'index');self::assertSame(200,$body['code']);self::assertSame(0,$body['data']['total']);self::assertStringContainsString('no-store',(string)$response->getHeader('Cache-Control'));
    }
    public function test_moderation_lists_only_qa_and_requires_cms_site_permission(): void
    {
        self::assertTrue(class_exists(Moderation::class),'QA moderation controller must exist');$q=$this->ask();$this->ask(['request_key'=>'content_fixture']);Db::table('cms_member_submission')->where('request_key','content_fixture')->update(['kind'=>'content']);
        [, $body]=$this->call(Moderation::class,'index',actor:'1');self::assertSame(200,$body['code']);self::assertSame(1,$body['data']['total']);
        [, $body]=$this->call(Moderation::class,'index',actor:'9');self::assertSame(403,$body['code']);
        [, $body]=$this->call(Moderation::class,'index',['status'=>'arbitrary'],actor:'1');self::assertSame(422,$body['code']);
        [, $body]=$this->call(Moderation::class,'review',['id'=>$q['id'],'lock_version'=>0,'decision'=>'publish','actor'=>'1'],actor:'9',post:true);self::assertSame(403,$body['code']);
        self::assertSame('pending',Db::table('cms_member_submission')->where('id',$q['id'])->value('status'));
    }
    public function test_route_metadata_exposes_public_and_member_actions_and_plugin_capabilities(): void
    {
        self::assertTrue(class_exists(Questions::class),'QA member controller must exist');self::assertTrue(class_exists(Moderation::class),'QA moderation controller must exist');
        self::assertSame('/qa/api',(new \ReflectionClass(Questions::class))->getAttributes(RouteGroup::class)[0]->newInstance()->prefix);
        foreach([['index',Get::class,'/questions'],['detail',Get::class,'/questions/{id}'],['ask',Post::class,'/questions'],['answer',Post::class,'/questions/{id}/answers'],['accept',Post::class,'/questions/{id}/accept']] as [$method,$attr,$path])self::assertSame($path,(new \ReflectionMethod(Questions::class,$method))->getAttributes($attr)[0]->newInstance()->path);
        foreach(['index'=>'qa:moderation:index','review'=>'qa:moderation:review'] as $method=>$code){self::assertSame($code,\app\admin\authorization\service\PermissionCode::derive(Moderation::class,$method));self::assertSame([],(new \ReflectionMethod(Moderation::class,$method))->getAttributes(Permission::class));}
        self::assertTrue(is_subclass_of(Questions::class,\app\cms\controller\MemberEndpoint::class));self::assertTrue(is_subclass_of(Moderation::class,\app\common\controller\AdminController::class));
    }
}
