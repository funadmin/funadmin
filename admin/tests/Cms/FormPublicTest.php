<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\controller\FormEndpoint;
use app\cms\service\{FormCaptcha,FormFileStorage,FormGuard,FormMessages,FormService,FormSubmissionService,FormSubmissionService as Submissions,FormValueValidator,SiteService};
use app\cms\tags\BuiltinTags;
use app\cms\template\{RenderContext,TemplateEngine,ThemeEngine};
use support\{Db,Request,Response};
final class FormPublicTest extends CmsTestCase
{
    private const SECRET='form-public-test-secret';
    private const PDF="%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
    private int $now=1_800_000_000;
    private string $base;
    private FormGuard $guard;
    protected function setUp(): void
    {
        parent::setUp();
        putenv('CMS_FORM_SECRET='.self::SECRET);
        \Webman\Limiter\Limiter::init(null);
        $this->base=sys_get_temp_dir().'/cms-form-public-'.bin2hex(random_bytes(6));
        mkdir($this->base,0700,true);
        $this->guard=new FormGuard(self::SECRET,fn(): int=>$this->now);
    }
    protected function tearDown(): void
    {
        putenv('CMS_FORM_SECRET');
        self::remove($this->base);
        parent::tearDown();
    }
    private static function remove(string $path): void
    {
        if(is_link($path)||is_file($path)){unlink($path);return;}
        if(!is_dir($path))return;
        foreach(scandir($path) as $item) if($item!=='.'&&$item!=='..') self::remove($path.'/'.$item);
        rmdir($path);
    }
    private function form(string $siteId,array $fields=[],array $settings=[],string $code='contact'): array
    {
        return (new FormService())->save($siteId,['code'=>$code,'name'=>'联系我们','fields'=>$fields?:[
            ['name'=>'name','type'=>'text','label'=>'姓名','required'=>true,'placeholder'=>'您的姓名'],
            ['name'=>'email','type'=>'email','label'=>'邮箱'],
            ['name'=>'topic','type'=>'checkbox','label'=>'主题','options'=>[['value'=>'sales','label'=>'销售'],['value'=>'support','label'=>'支持']]],
            ['name'=>'_p','type'=>'paragraph','html'=>'<b>说明</b>'],
            ['name'=>'resume','type'=>'file','label'=>'简历','max_files'=>2],
        ],'settings'=>$settings+['success_message'=>'已收到','rate_limit'=>60]]);
    }
    private function tag(string $siteId,string $source,string $locale='zh-cn',array $page=[]): string
    {
        $context=new RenderContext($siteId,$locale,$page);
        return (new TemplateEngine((new BuiltinTags())->registry($context),__DIR__))->render($source,[],$context);
    }
    private function endpoint(): FormEndpoint
    {
        $captcha=new FormCaptcha(self::SECRET);
        return new FormEndpoint(new FormSubmissionService(new FormService(),new FormFileStorage($this->base.'/forms'),$this->guard,$captcha),$this->guard,$captcha);
    }
    /** @param array<string,string|list<string>> $fields @param array<string,list<array{0:string,1:string}>> $files */
    private function request(string $path,array $fields,array $files=[],bool $json=true,string $host='cms.test'): Request
    {
        $boundary='----cmsform'.bin2hex(random_bytes(6));$body='';
        foreach($fields as $name=>$value) foreach((array)$value as $item) $body.="--$boundary\r\nContent-Disposition: form-data; name=\"".(is_array($value)?$name.'[]':$name)."\"\r\n\r\n$item\r\n";
        foreach($files as $name=>$list) foreach($list as [$filename,$content]) $body.="--$boundary\r\nContent-Disposition: form-data; name=\"{$name}[]\"; filename=\"$filename\"\r\nContent-Type: application/octet-stream\r\n\r\n$content\r\n";
        $body.="--$boundary--\r\n";
        return new Request("POST /cms/forms/$path HTTP/1.1\r\nHost: $host\r\n".($json?"Accept: application/json\r\nX-Requested-With: XMLHttpRequest\r\n":"Accept: text/html\r\n")."User-Agent: PHPUnit\r\nContent-Type: multipart/form-data; boundary=$boundary\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);
    }
    private function token(array $site,array $form): string
    {
        $token=$this->guard->issue($site['id'],$form['id']);
        $this->now+=10;
        return $token;
    }
    private static function data(Response $response): array { return json_decode($response->rawBody(),true,512,JSON_THROW_ON_ERROR); }

    public function test_tag_renders_fields_with_escaped_admin_text(): void
    {
        $site=$this->site();
        $this->form($site['id'],[
            ['name'=>'name','type'=>'text','label'=>'<script>alert(1)</script>','placeholder'=>'"><img src=x onerror=alert(2)>','help'=>'{{ 7*7 }} @php echo 1; @endphp','required'=>true],
            ['name'=>'kind','type'=>'select','label'=>'类型','options'=>[['value'=>'a"b','label'=>'<i>A</i>']]],
            ['name'=>'_p','type'=>'paragraph','html'=>'<p><b>说明</b><script>bad()</script></p>'],
            ['name'=>'agree','type'=>'consent','label'=>'同意','html'=>'我已阅读<a href="/privacy">隐私政策</a>','required'=>true],
            ['name'=>'channel','type'=>'hidden','label'=>'渠道','value'=>'web','from_query'=>true],
        ],['submit_text'=>'<b>发送</b>']);
        $html=$this->tag($site['id'],'{cms:form name="contact" /}');
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;',$html);
        self::assertStringNotContainsString('<script>alert',$html);
        self::assertStringNotContainsString('<img src=x',$html);
        self::assertStringContainsString('{{ 7*7 }} @php echo 1; @endphp',$html,'admin text is data, never compiled');
        self::assertMatchesRegularExpression('~<small class="cms-form-help"[^>]*>\{\{ 7\*7 \}\} @php echo 1; @endphp</small>~',$html,'help text is not evaluated (random tokens may contain "49", so match the element)');
        self::assertStringContainsString('&lt;i&gt;A&lt;/i&gt;',$html);
        self::assertStringContainsString('value="a&quot;b"',$html);
        self::assertStringContainsString('&lt;b&gt;发送&lt;/b&gt;',$html);
        self::assertStringContainsString('<b>说明</b>',$html,'sanitized paragraph html is output as-is');
        self::assertStringNotContainsString('bad()',$html);
        self::assertStringContainsString('<a href="/privacy">隐私政策</a>',$html);
        self::assertMatchesRegularExpression('~<form[^>]*\bdata-cms-form\b[^>]*>~',$html);
        self::assertMatchesRegularExpression('~<form[^>]*method="post"[^>]*enctype="multipart/form-data"[^>]*action="/forms/contact"~',$html);
        foreach(['_token','_locale','_source'] as $hidden) self::assertMatchesRegularExpression('~<input type="hidden" name="'.$hidden.'"~',$html);
        self::assertMatchesRegularExpression('~<input[^>]*name="_hp"[^>]*tabindex="-1"[^>]*autocomplete="off"~',$html);
        self::assertStringContainsString('data-field="name"',$html);
        self::assertStringContainsString('data-from-query="channel"',$html);
        self::assertSame(1,substr_count($html,'<script src="/plugin-assets/cms/public/cms-form.js" defer></script>'));
        preg_match('~name="_token" value="([^"]+)"~',$html,$m);
        $form=(new FormService())->resolve($site['id'],'contact','zh-cn');
        $this->now=time()+10;
        (new FormGuard(self::SECRET,fn(): int=>$this->now))->verify(html_entity_decode($m[1]),$site['id'],$form['id']);
    }

    public function test_tag_returns_empty_for_missing_or_disabled_form(): void
    {
        $site=$this->site();
        self::assertSame('<i></i>',$this->tag($site['id'],'<i>{cms:form name="missing" /}</i>'));
        self::assertSame('<i></i>',$this->tag($site['id'],'<i>{cms:form name="Bad Code!" /}</i>'));
        $form=$this->form($site['id']);
        (new FormService())->setStatus($site['id'],$form['id'],false,$form['lock_version']);
        self::assertSame('<i></i>',$this->tag($site['id'],'<i>{cms:form name="contact" /}</i>'));
        $other=$this->site('other','other.test');
        $this->form($other['id']);
        self::assertSame('<i></i>',$this->tag($site['id'],'<i>{cms:form name="contact" /}</i>'),'another site form is invisible');
    }

    public function test_database_template_tag_renders_form(): void
    {
        $site=$this->site();$this->form($site['id'],[],['captcha'=>true]);
        $database=$this->tag($site['id'],'{cms:form name="contact" /}');
        $root=$this->base.'/theme';mkdir($root);
        file_put_contents($root.'/page.blade.php','{{ $cms->value(\'form\',[\'name\'=>\'contact\']) }}');
        $context=new RenderContext($site['id'],'zh-cn');
        $blade=(new ThemeEngine((new BuiltinTags())->registry($context),$root))->renderFile('page',[],$context);
        $normalize=static fn(string $html): string=>preg_replace(['~cms-form-[0-9a-f]{8}~','~(name="_token" value=")[^"]+~','~(name="_captcha_id" value=")[^"]+~','~src="data:image/[^"]+"~'],['UID','$1T','$1C','src="IMG"'],$html);
        self::assertStringContainsString('name="_captcha_id"',$database);
        self::assertMatchesRegularExpression('~<img[^>]*src="data:image/~',$database);
        self::assertStringContainsString('name="_captcha"',$database);
        self::assertSame($normalize($blade),$normalize($database));
    }

    public function test_visitor_messages_follow_site_locale(): void
    {
        $keys=array_merge(FormValueValidator::ERRORS,Submissions::ERRORS);
        foreach(['zh-cn','en-us'] as $locale){
            $messages=FormMessages::for($locale);
            foreach($keys as $key) self::assertNotSame('',$messages['errors'][$key]??'',"$locale missing $key");
            self::assertSame(array_keys(FormMessages::for('zh-cn')),array_keys($messages));
            self::assertSame(array_keys(FormMessages::for('zh-cn')['errors']),array_keys($messages['errors']));
        }
        self::assertSame(FormMessages::for('en-us'),FormMessages::for('fr-fr'));
        self::assertSame('提交',FormMessages::for('zh-cn')['submit']);
        self::assertSame('Submit',FormMessages::for('en-us')['submit']);
        $site=$this->site();(new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);
        $form=$this->form($site['id']);
        self::assertMatchesRegularExpression('~<button[^>]*type="submit"[^>]*>\s*提交\s*</button>~',$this->tag($site['id'],'{cms:form name="contact" /}'));
        $english=$this->tag($site['id'],'{cms:form name="contact" /}','en-us');
        self::assertMatchesRegularExpression('~<button[^>]*type="submit"[^>]*>\s*Submit\s*</button>~',$english);
        self::assertStringContainsString('name="_locale" value="en-us"',$english);
        $response=$this->endpoint()->submit($this->request('contact',['_token'=>$this->token($site,$form),'_locale'=>'en-us','name'=>'']),'contact');
        self::assertSame(422,$response->getStatusCode());
        self::assertSame(FormMessages::for('en-us')['errors']['required'],self::data($response)['data']['errors']['name']);
        $response=$this->endpoint()->submit($this->request('contact',['_token'=>$this->token($site,$form),'_locale'=>'fr-fr','name'=>'']),'contact');
        self::assertSame(FormMessages::for('zh-cn')['errors']['required'],self::data($response)['data']['errors']['name'],'unknown locale falls back to the site default');
    }

    public function test_two_forms_on_one_page_render_independent_ids(): void
    {
        $site=$this->site();$this->form($site['id']);
        $html=$this->tag($site['id'],'{cms:form name="contact" /}{cms:form name="contact" /}');
        self::assertSame(2,substr_count($html,'<form '));
        preg_match_all('~\bid="([^"]+)"~',$html,$ids);
        self::assertNotEmpty($ids[1]);
        self::assertSame($ids[1],array_values(array_unique($ids[1])),'every id is unique on the page');
        preg_match_all('~\bfor="([^"]+)"~',$html,$labels);
        foreach($labels[1] as $for) self::assertContains($for,$ids[1]);
        self::assertSame(1,substr_count($html,'cms-form.js'),'the script is included once per page');
    }

    public function test_theme_partial_overrides_builtin_form(): void
    {
        $site=$this->site();$this->form($site['id']);
        $theme='formtest_'.bin2hex(random_bytes(4));
        $root=dirname(__DIR__,2).'/plugins/cms/app/cms/view/'.$theme;
        mkdir($root.'/partials',0755,true);
        try{
            file_put_contents($root.'/partials/form.blade.php','<form class="themed" action="{{ $action }}" id="{{ $uid }}" data-cms-form>{{ $form[\'name\'] }}|{{ $ui[\'submit\'] }}|{{ $locale }}|{{ $captcha === null ? \'nocaptcha\' : \'captcha\' }}<input name="_token" value="{{ $token }}">@foreach($form[\'fields\'] as $field)[{{ $field[\'label\'] }}]@endforeach</form>');
            $html=$this->tag($site['id'],'{cms:form name="contact" /}','zh-cn',['theme'=>$theme]);
            self::assertStringContainsString('<form class="themed" action="/forms/contact"',$html);
            self::assertStringContainsString('联系我们|提交|zh-cn|nocaptcha',$html);
            self::assertStringContainsString('[姓名][邮箱][主题][][简历]',$html);
            self::assertStringNotContainsString('cms-form-field',$html,'built-in markup is replaced');
            self::assertStringContainsString('cms-form.js',$html);
            self::assertStringContainsString('data-field="name"',$this->tag($site['id'],'{cms:form name="contact" /}','zh-cn',['theme'=>'default']),'themes without the partial keep the built-in form');
        }finally{self::remove($root);}
    }

    public function test_json_submission_success_and_field_errors(): void
    {
        $site=$this->site();$form=$this->form($site['id']);
        $endpoint=$this->endpoint();
        $response=$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'_locale'=>'zh-cn','name'=>'','email'=>'bad']),'contact');
        self::assertSame(422,$response->getStatusCode());
        $errors=self::data($response);
        self::assertSame(422,$errors['code']);
        self::assertSame(['name'=>'此项为必填','email'=>FormMessages::for('zh-cn')['errors']['invalid_email']],$errors['data']['errors']);
        self::assertStringContainsString('no-store',$response->getHeader('Cache-Control'));

        $response=$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'_locale'=>'zh-cn','_source'=>'https://cms.test/contact?x=1','name'=>'张三','email'=>'a@example.com','topic'=>['sales','support']],['resume'=>[['简历.pdf',self::PDF],['','']]]),'contact');
        self::assertSame(200,$response->getStatusCode(),$response->rawBody());
        self::assertSame(['code'=>0,'msg'=>'success','data'=>['message'=>'已收到']],self::data($response));
        $row=Db::table('cms_form_submission')->first();
        self::assertSame(['name'=>'张三','email'=>'a@example.com','topic'=>['sales','support']],json_decode($row->values,true));
        self::assertSame('/contact?x=1',$row->source_url,'same-host absolute sources are stored as site-relative paths');
        self::assertSame('PHPUnit',$row->user_agent);
        self::assertNotSame('',$row->ip);
        self::assertNull($row->member_id);
        self::assertSame(['简历.pdf'],Db::table('cms_form_file')->pluck('original_name')->all(),'empty file inputs are skipped');

        $response=$endpoint->submit($this->request('contact',['_token'=>'forged','name'=>'x']),'contact');
        self::assertSame(419,$response->getStatusCode());
        self::assertSame(419,self::data($response)['code']);
        self::assertSame(FormMessages::for('zh-cn')['errors']['token_invalid'],self::data($response)['data']['errors']['_form']);
        $token=$this->guard->issue($site['id'],$form['id']);$this->now+=FormGuard::MAX_AGE+1;
        self::assertSame(419,$endpoint->submit($this->request('contact',['_token'=>$token,'name'=>'x']),'contact')->getStatusCode());
        $token=$this->guard->issue($site['id'],$form['id']);
        $response=$endpoint->submit($this->request('contact',['_token'=>$token,'name'=>'x']),'contact');
        self::assertSame(422,$response->getStatusCode());
        self::assertSame(FormMessages::for('zh-cn')['errors']['too_fast'],self::data($response)['data']['errors']['_form']);

        $limited=$this->form($site['id'],[['name'=>'name','type'=>'text','label'=>'姓名']],['rate_limit'=>1],'limited');
        self::assertSame(200,$endpoint->submit($this->request('limited',['_token'=>$this->token($site,$limited),'name'=>'a']),'limited')->getStatusCode());
        $response=$endpoint->submit($this->request('limited',['_token'=>$this->token($site,$limited),'name'=>'b']),'limited');
        self::assertSame(429,$response->getStatusCode());
        self::assertSame(FormMessages::for('zh-cn')['errors']['too_many_requests'],self::data($response)['data']['errors']['_form']);

        $response=$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'_hp'=>'http://spam','name'=>'bot']),'contact');
        self::assertSame(200,$response->getStatusCode());
        self::assertSame(1,Db::table('cms_form_submission')->where('form_id',$form['id'])->count(),'honeypot is silently dropped');
    }

    public function test_html_fallback_renders_result_page(): void
    {
        $site=$this->site();$form=$this->form($site['id']);$endpoint=$this->endpoint();
        $response=$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'_source'=>'/contact.html','name'=>'张三'],[],false),'contact');
        self::assertSame(200,$response->getStatusCode());
        self::assertStringStartsWith('text/html',$response->getHeader('Content-Type'));
        self::assertStringContainsString('已收到',$response->rawBody());
        self::assertStringContainsString('href="/contact.html"',$response->rawBody());
        self::assertStringContainsString('noindex',$response->rawBody());

        $response=$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'_source'=>'javascript:alert(1)','name'=>'','email'=>'<b>bad</b>'],[],false),'contact');
        self::assertSame(422,$response->getStatusCode());
        $body=$response->rawBody();
        self::assertStringContainsString('姓名',$body);self::assertStringContainsString('此项为必填',$body);
        self::assertStringContainsString('邮箱',$body);self::assertStringContainsString(FormMessages::for('zh-cn')['errors']['invalid_email'],$body);
        self::assertStringContainsString(FormMessages::for('zh-cn')['back'],$body);
        self::assertStringNotContainsString('javascript:',$body,'unsafe return links are dropped');
        foreach(['https://evil.com\\@cms.test/','https://user@cms.test/x','https://user:pw@cms.test/x','//evil.com/x','https://evil.com/x',"/x\r\nLocation: https://evil.com"] as $source){
            $body=$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'_source'=>$source,'name'=>''],[],false),'contact')->rawBody();
            self::assertStringContainsString('<a href="/">',$body,'unsafe source falls back to the site root: '.$source);
            self::assertStringNotContainsString('evil.com',$body);
        }
        $body=$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'_source'=>'https://cms.test/contact.html?a=1&b=2#top','name'=>''],[],false),'contact')->rawBody();
        self::assertStringContainsString('<a href="/contact.html?a=1&amp;b=2">',$body,'same-host absolute sources become relative path + query');

        $token=$this->guard->issue($site['id'],$form['id']);$this->now+=FormGuard::MAX_AGE+1;
        $response=$endpoint->submit($this->request('contact',['_token'=>$token,'name'=>'x'],[],false),'contact');
        self::assertSame(419,$response->getStatusCode());
        self::assertStringContainsString(FormMessages::for('zh-cn')['errors']['token_expired'],$response->rawBody());
    }

    public function test_uploads_are_limited_to_form_file_fields_and_capped(): void
    {
        $site=$this->site();$form=$this->form($site['id']);$endpoint=$this->endpoint();
        $pdf=['a.pdf',self::PDF];
        $response=$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'name'=>'张三'],['ghost'=>[$pdf,$pdf],'name'=>[$pdf]]),'contact');
        self::assertSame(200,$response->getStatusCode(),$response->rawBody());
        self::assertSame(0,Db::table('cms_form_file')->count(),'uploads under non-file field names are ignored');
        $response=$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'name'=>'张三'],['resume'=>array_fill(0,15,$pdf)]),'contact');
        self::assertSame(422,$response->getStatusCode());
        self::assertSame(['resume'=>FormMessages::for('zh-cn')['errors']['too_many_files']],self::data($response)['data']['errors']);
        $method=new \ReflectionMethod(FormEndpoint::class,'uploads');
        $request=$this->request('contact',[],['resume'=>array_fill(0,15,$pdf),'ghost'=>[$pdf]]);
        [$uploads,$errors]=$method->invoke($endpoint,$request,(new FormService())->resolve($site['id'],'contact','zh-cn')['fields']);
        self::assertSame(['resume'],array_keys($uploads));
        self::assertCount(3,$uploads['resume'],'entries are capped at max_files + 1');
        self::assertSame([],$errors);
    }

    public function test_disabled_or_foreign_form_rejects_submission(): void
    {
        $site=$this->site();$form=$this->form($site['id']);
        $other=$this->site('other','other.test');$foreign=$this->form($other['id']);
        $endpoint=$this->endpoint();
        $response=$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'name'=>'x'],[],true,'unknown.test'),'contact');
        self::assertSame(404,$response->getStatusCode());
        self::assertSame(404,self::data($response)['code']);
        $response=$endpoint->submit($this->request('missing',['_token'=>$this->token($site,$form),'name'=>'x']),'missing');
        self::assertSame(404,$response->getStatusCode());
        $response=$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'name'=>'x'],[],true,'other.test'),'contact');
        self::assertSame(419,$response->getStatusCode(),'a token bound to another site form is rejected');
        (new FormService())->setStatus($site['id'],$form['id'],false,$form['lock_version']);
        self::assertSame(404,$endpoint->submit($this->request('contact',['_token'=>$this->token($site,$form),'name'=>'x']),'contact')->getStatusCode());
        self::assertSame(404,$endpoint->token($this->request('contact/token',[]),'contact')->getStatusCode());
        self::assertSame(0,Db::table('cms_form_submission')->count());
        self::assertSame(200,$endpoint->token($this->request('contact/token',[],[],true,'other.test'),'contact')->getStatusCode());
        unset($foreign);
    }

    public function test_token_and_captcha_endpoints(): void
    {
        $site=$this->site();$form=$this->form($site['id']);$endpoint=$this->endpoint();
        $response=$endpoint->token($this->request('contact/token',[]),'contact');
        self::assertSame(200,$response->getStatusCode());
        self::assertStringContainsString('no-store',$response->getHeader('Cache-Control'));
        $token=self::data($response)['data']['token'];
        $this->now+=10;
        $this->guard->verify($token,$site['id'],$form['id']);
        $captchaRequest=fn(string $code): Request=>new Request("GET /cms/forms/$code/captcha HTTP/1.1\r\nHost: cms.test\r\nAccept: application/json\r\n\r\n");
        self::assertSame(404,$endpoint->captcha($captchaRequest('contact'),'contact')->getStatusCode(),'captcha disabled');
        $this->form($site['id'],[['name'=>'name','type'=>'text','label'=>'姓名']],['captcha'=>true],'secure');
        $response=$endpoint->captcha($captchaRequest('secure'),'secure');
        self::assertSame(200,$response->getStatusCode());
        self::assertStringContainsString('no-store',$response->getHeader('Cache-Control'));
        $data=self::data($response)['data'];
        self::assertStringStartsWith('data:image/',$data['image']);
        self::assertNotSame('',$data['id']);
        for($i=1;$i<30;$i++) self::assertSame(200,$endpoint->captcha($captchaRequest('secure'),'secure')->getStatusCode());
        self::assertSame(429,$endpoint->captcha($captchaRequest('secure'),'secure')->getStatusCode(),'captcha issuance is throttled per IP');
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_catch_all_route_no_longer_shadows_form_endpoints(): void
    {
        \Webman\Route::load([config_path()]);
        $found=static function(string $method,string $path): array {
            $route=\Webman\Route::dispatch($method,$path);
            self::assertSame(\FastRoute\Dispatcher::FOUND,$route[0],"$method $path");
            return $route[1]['callback'];
        };
        self::assertSame([FormEndpoint::class,'submit'],$found('POST','/cms/forms/contact'));
        self::assertSame([FormEndpoint::class,'token'],$found('POST','/cms/forms/contact/token'));
        self::assertSame([FormEndpoint::class,'captcha'],$found('GET','/cms/forms/contact/captcha'));
        self::assertSame(\app\cms\controller\Index::class,$found('GET','/cms/news/example.html')[0]);
        self::assertSame(\app\cms\controller\Index::class,$found('GET','/cms/formsets/')[0],'only the forms segment is reserved');
        self::assertSame(\app\cms\controller\MemberContent::class,$found('GET','/cms/member/context')[0]);
    }
}
