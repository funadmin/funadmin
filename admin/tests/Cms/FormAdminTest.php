<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\admin\authorization\service\CasbinService;
use app\admin\plugins\cms\controller\Forms;
use app\cms\service\{FormService,SiteRoleService};
use support\{Context,Db,Request,Response};
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class FormAdminTest extends CmsTestCase
{
    private const FORM=['cms:form:list','cms:form:manage','cms:form:submissions','cms:form:handle','cms:form:export','cms:form:delete'];
    /** 方法 → [站点操作, 是否 POST] */
    private const ENDPOINTS=[
        'index'=>['cms:form:list',false],'summary'=>['cms:form:list',false],
        'save'=>['cms:form:manage',true],'status'=>['cms:form:manage',true],'delete'=>['cms:form:manage',true],
        'submissions'=>['cms:form:submissions',false],'detail'=>['cms:form:submissions',false],'file'=>['cms:form:submissions',false],
        'note'=>['cms:form:handle',true],'transition'=>['cms:form:handle',true],'invalidate'=>['cms:form:handle',true],
        'export'=>['cms:form:export',false],
        'remove'=>['cms:form:delete',true],
    ];
    /** @var list<string> 测试写入私有存储的文件与新建的目录，结束时删除 */
    private array $written=[];
    /** @var list<string> */
    private array $created=[];
    protected function setUp(): void
    {
        parent::setUp();(new \app\common\service\database\MigrationService())->run();
        self::registerHostPermissions();
        Db::table('admin')->insert(['id'=>91,'username'=>'form_member','password'=>'unused','status'=>1]);
        Db::table('auth_group')->insert(['id'=>91,'code'=>'form_member','name'=>'Form member','status'=>1]);
        CasbinService::instance()->syncAdminRoles(91,[91]);
        CasbinService::instance()->syncRolePermissions(91,Db::table('permission')->where('source_name','cms')->pluck('id')->all());
    }
    protected function tearDown(): void
    {
        foreach($this->written as $file) if(is_file($file)) unlink($file);
        foreach(array_reverse($this->created) as $dir) if(is_dir($dir)) @rmdir($dir);
        parent::tearDown();
    }
    private function call(string $method,array $input,string $actor='91'): Response
    {
        $post=self::ENDPOINTS[$method][1];
        $body=$post?json_encode($input):'';$query=$post?'':'?'.http_build_query($input);
        $request=new Request(($post?'POST':'GET').' /admin/plugins/cms/forms/test'.$query." HTTP/1.1\r\nHost: admin.test\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);
        $request->context['admin']=['id'=>$actor];Context::set(\Webman\Http\Request::class,$request);
        try{return (new Forms())->$method();}finally{Context::destroy();}
    }
    private function json(Response $response): array {return json_decode($response->rawBody(),true,512,JSON_THROW_ON_ERROR);}
    /** 把管理员 91 设为站点成员，只拥有给定站点操作。 */
    private function member(string $site,array $operations,string $code='custom'): void
    {
        $roles=new SiteRoleService();
        $role=$roles->saveRole($site,'1',['code'=>$code,'name'=>$code,'permissions'=>array_values($operations)]);
        $roles->assign($site,'1','91',[$role['id']]);
    }
    private function form(string $site,string $code='contact'): array
    {
        return (new FormService())->save($site,['code'=>$code,'name'=>'联系我们','fields'=>[
            ['name'=>'name','type'=>'text','label'=>'姓名','required'=>true],
            ['name'=>'resume','type'=>'file','label'=>'简历','max_files'=>3],
        ]]);
    }
    private function submission(string $site,array $form,array $values,string $status='new'): string
    {
        $now='2026-11-09 08:00:00';
        return (string)Db::table('cms_form_submission')->insertGetId(['site_id'=>$site,'form_id'=>$form['id'],'locale'=>'zh-cn','fields'=>json_encode($form['fields'],JSON_UNESCAPED_UNICODE),'values'=>json_encode($values,JSON_UNESCAPED_UNICODE),'search_text'=>implode(' ',$values),'status'=>$status,'source_url'=>'https://cms.test/contact','ip'=>'203.0.113.7','user_agent'=>'PHPUnit','lock_version'=>0,'created_at'=>$now,'updated_at'=>$now]);
    }
    /** 直接写入私有存储并登记附件。 */
    private function file(string $site,string $submission,string $name,string $mime,string $content): string
    {
        $path=$site.'/202611/'.bin2hex(random_bytes(16));$absolute=runtime_path('plugins-data/cms/forms/'.$path);
        $missing=[];for($dir=dirname($absolute);!is_dir($dir);$dir=dirname($dir))array_unshift($missing,$dir);
        if($missing){mkdir(dirname($absolute),0700,true);array_push($this->created,...$missing);}
        file_put_contents($absolute,$content);$this->written[]=$absolute;
        return (string)Db::table('cms_form_file')->insertGetId(['site_id'=>$site,'submission_id'=>$submission,'field'=>'resume','original_name'=>$name,'mime'=>$mime,'size'=>strlen($content),'sha256'=>hash('sha256',$content),'path'=>$path,'created_at'=>'2026-11-09 08:00:00']);
    }

    /** @return iterable<string,array{string,string}> */
    public static function endpoints(): iterable
    {
        foreach(self::ENDPOINTS as $method=>[$operation]) yield $method=>[$method,$operation];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('endpoints')]
    public function test_each_endpoint_requires_its_site_operation(string $method,string $operation): void
    {
        $site=$this->site()['id'];
        $this->member($site,array_diff(self::FORM,[$operation]),'without');
        $denied=$this->call($method,['site_id'=>$site,'locale'=>'zh-cn']);
        self::assertSame(403,$this->json($denied)['code'],$method.' 缺少 '.$operation.' 时必须拒绝');
        self::assertSame(403,$denied->getStatusCode());
        // 只拥有该操作时通过站点权限检查（后续可能因参数缺失返回 4xx，但不是 403）。
        $this->member($site,[$operation],'only');
        $allowed=$this->call($method,['site_id'=>$site,'locale'=>'zh-cn']);
        self::assertNotSame(403,$allowed->getStatusCode(),$method.' 拥有 '.$operation.' 时不应拒绝');
        // 非站点成员一律 403。
        $other=$this->site('other','other.test')['id'];
        self::assertSame(403,$this->call($method,['site_id'=>$other,'locale'=>'zh-cn'])->getStatusCode());
    }

    public function test_editor_can_handle_but_not_export_or_delete(): void
    {
        $site=$this->site()['id'];
        (new SiteRoleService())->assign($site,'1','91',[(string)Db::table('cms_site_role')->where('site_id',$site)->where('code','editor')->value('id')]);
        $form=$this->form($site);$first=$this->submission($site,$form,['name'=>'张三']);$second=$this->submission($site,$form,['name'=>'李四']);
        $index=$this->json($this->call('index',['site_id'=>$site]));
        self::assertSame(200,$index['code']);self::assertSame(['contact'],array_column($index['data'],'code'));self::assertSame(2,$index['data'][0]['new_count']);
        self::assertSame(['new_count'=>2],$this->json($this->call('summary',['site_id'=>$site]))['data']);
        $list=$this->json($this->call('submissions',['site_id'=>$site,'form_id'=>$form['id'],'keyword'=>'张三','page'=>1]));
        self::assertSame(200,$list['code']);self::assertSame(1,$list['data']['total']);self::assertSame($first,$list['data']['list'][0]['id']);
        $note=$this->json($this->call('note',['site_id'=>$site,'id'=>$first,'body'=>'已电话联系']));
        self::assertSame(200,$note['code']);self::assertSame(['note'],array_column($note['data']['events'],'type'));
        $moved=$this->json($this->call('transition',['site_id'=>$site,'id'=>$first,'status'=>'processing','lock_version'=>'0']));
        self::assertSame(200,$moved['code']);self::assertSame('processing',$moved['data']['status']);self::assertSame(1,$moved['data']['lock_version']);
        self::assertSame(409,$this->call('transition',['site_id'=>$site,'id'=>$first,'status'=>'done','lock_version'=>0])->getStatusCode());
        $detail=$this->json($this->call('detail',['site_id'=>$site,'id'=>$first]));
        self::assertSame(200,$detail['code']);self::assertSame('张三',$detail['data']['fields'][0]['value']);self::assertCount(2,$detail['data']['events']);
        self::assertSame(['count'=>2],$this->json($this->call('invalidate',['site_id'=>$site,'ids'=>[$first,$second]]))['data']);
        self::assertSame(422,$this->json($this->call('invalidate',['site_id'=>$site,'ids'=>$first]))['code'],'ids 必须为数组');
        foreach(['export'=>['site_id'=>$site],'remove'=>['site_id'=>$site,'ids'=>[$first]],'save'=>['site_id'=>$site,'code'=>'x','name'=>'X'],'status'=>['site_id'=>$site,'id'=>$form['id'],'enabled'=>false,'lock_version'=>0],'delete'=>['site_id'=>$site,'id'=>$form['id']]] as $method=>$input){
            self::assertSame(403,$this->call($method,$input)->getStatusCode(),$method);
        }
        self::assertSame(2,Db::table('cms_form_submission')->where('site_id',$site)->count());
        self::assertTrue(Db::table('cms_form')->where('id',$form['id'])->value('status')==1);
        // 站点管理员（超级管理员）可以改表单、删提交。
        $off=$this->json($this->call('status',['site_id'=>$site,'id'=>$form['id'],'enabled'=>'0','lock_version'=>'0'],'1'));
        self::assertSame(200,$off['code']);self::assertFalse($off['data']['enabled']);
        $saved=$this->json($this->call('save',['site_id'=>$site,'id'=>$form['id'],'lock_version'=>1,'name'=>'改名','enabled'=>'true'],'1'));
        self::assertSame(200,$saved['code']);self::assertTrue($saved['data']['enabled']);self::assertSame('改名',$saved['data']['name']);
        self::assertSame(422,$this->json($this->call('save',['site_id'=>$site,'code'=>'bad','name'=>'Bad','enabled'=>'maybe'],'1'))['code']);
        self::assertSame(['count'=>1],$this->json($this->call('remove',['site_id'=>$site,'ids'=>[$second]],'1'))['data']);
        self::assertSame(409,$this->call('delete',['site_id'=>$site,'id'=>$form['id']],'1')->getStatusCode());
        $empty=$this->form($site,'empty');
        self::assertSame(200,$this->call('delete',['site_id'=>$site,'id'=>$empty['id']],'1')->getStatusCode());
        self::assertFalse(Db::table('cms_form')->where('id',$empty['id'])->exists());
    }

    public function test_file_download_headers_and_site_scope(): void
    {
        $a=$this->site()['id'];$b=$this->site('other','other.test')['id'];
        $this->member($a,['cms:form:list','cms:form:submissions'],'viewer_only');
        $form=$this->form($a);$submission=$this->submission($a,$form,['name'=>'张三']);
        $png=$this->file($a,$submission,'photo.png','image/png',"\x89PNG\r\n\x1a\nfake");
        $text=$this->file($a,$submission,'note.txt','text/plain','<html><script>alert(1)</script></html>');
        $pdf=$this->file($a,$submission,'简历 "2026".pdf','application/pdf',"%PDF-1.4\n%%EOF\n");
        $otherForm=$this->form($b);$foreign=$this->file($b,$this->submission($b,$otherForm,['name'=>'外站']),'secret.png','image/png','x');

        $r=$this->call('file',['site_id'=>$a,'id'=>$png]);
        self::assertSame(200,$r->getStatusCode());self::assertSame(runtime_path('plugins-data/cms/forms/'.Db::table('cms_form_file')->where('id',$png)->value('path')),$r->file['file']);
        self::assertSame('image/png',$r->getHeader('Content-Type'));
        self::assertSame("attachment; filename=\"photo.png\"; filename*=UTF-8''photo.png",$r->getHeader('Content-Disposition'));
        self::assertSame('nosniff',$r->getHeader('X-Content-Type-Options'));
        self::assertSame("default-src 'none'; sandbox",$r->getHeader('Content-Security-Policy'));
        self::assertSame("inline; filename=\"photo.png\"; filename*=UTF-8''photo.png",$this->call('file',['site_id'=>$a,'id'=>$png,'preview'=>'1'])->getHeader('Content-Disposition'));

        // 文本类附件即便内容是 HTML 也只能作为二进制附件下载，预览参数无效。
        $r=$this->call('file',['site_id'=>$a,'id'=>$text,'preview'=>'1']);
        self::assertSame('application/octet-stream',$r->getHeader('Content-Type'));
        self::assertStringStartsWith('attachment;',$r->getHeader('Content-Disposition'));
        self::assertSame('nosniff',$r->getHeader('X-Content-Type-Options'));
        self::assertSame("default-src 'none'; sandbox",$r->getHeader('Content-Security-Policy'));

        $r=$this->call('file',['site_id'=>$a,'id'=>$pdf,'preview'=>'1']);
        self::assertSame('application/pdf',$r->getHeader('Content-Type'));
        self::assertSame("attachment; filename=\"__ 2026.pdf\"; filename*=UTF-8''%E7%AE%80%E5%8E%86%20%222026%22.pdf",$r->getHeader('Content-Disposition'));

        // 他站附件：带本站 site_id 查不到（404 而非 403）；带他站 site_id 时本站成员没有他站权限（403）。
        $r=$this->call('file',['site_id'=>$a,'id'=>$foreign]);
        self::assertSame(404,$r->getStatusCode());self::assertNull($r->file);self::assertSame(404,$this->json($r)['code']);
        self::assertSame(403,$this->call('file',['site_id'=>$b,'id'=>$foreign])->getStatusCode());
        self::assertSame(404,$this->call('detail',['site_id'=>$a,'id'=>Db::table('cms_form_file')->where('id',$foreign)->value('submission_id')])->getStatusCode());
        // 文件已从磁盘消失时 404。
        unlink(runtime_path('plugins-data/cms/forms/'.Db::table('cms_form_file')->where('id',$pdf)->value('path')));
        self::assertSame(404,$this->call('file',['site_id'=>$a,'id'=>$pdf])->getStatusCode());
    }

    public function test_export_streams_bom_csv(): void
    {
        $site=$this->site()['id'];$this->member($site,self::FORM,'exporter');
        $form=$this->form($site);
        $this->submission($site,$form,['name'=>'张三']);$this->submission($site,$form,['name'=>'=1+1, "quoted"']);$this->submission($site,$form,['name'=>'已完成'],'done');
        $r=$this->call('export',['site_id'=>$site,'locale'=>'en-us','form_id'=>$form['id']]);
        self::assertSame(200,$r->getStatusCode());
        self::assertSame('text/csv; charset=UTF-8',$r->getHeader('Content-Type'));
        self::assertSame('attachment; filename="forms-main-'.date('Ymd').'.csv"',$r->getHeader('Content-Disposition'));
        self::assertSame('nosniff',$r->getHeader('X-Content-Type-Options'));
        $body=$r->rawBody();
        self::assertStringStartsWith("\xEF\xBB\xBF",$body);
        $rows=array_map(static fn(string $line): array=>str_getcsv($line,',','"',''),explode("\n",rtrim(substr($body,3),"\n")));
        self::assertSame(['Submitted at','Form','Status','Source page','姓名','简历'],$rows[0]);
        self::assertCount(3,$rows,'默认只导出新提交与跟进中');
        self::assertSame(["'=1+1, \"quoted\"",'张三'],[$rows[1][4],$rows[2][4]]);
        self::assertSame('New',$rows[1][2]);
        $all=$this->call('export',['site_id'=>$site,'status'=>['new','processing','done']]);
        $lines=explode("\n",rtrim(substr($all->rawBody(),3),"\n"));
        self::assertCount(4,$lines);self::assertStringStartsWith('提交时间,表单,状态',$lines[0]);
        $bad=$this->call('export',['site_id'=>$site,'status'=>['bogus']]);
        // 业务校验错误（422）按后台约定以 HTTP 200 + 业务码返回，仍是 JSON 而非 CSV。
        self::assertSame(422,$this->json($bad)['code']);self::assertNull($bad->getHeader('Content-Disposition'));
    }
}
