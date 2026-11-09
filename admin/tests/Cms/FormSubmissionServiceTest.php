<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\cms\service\FormCaptcha;
use app\cms\service\FormFileStorage;
use app\cms\service\FormGuard;
use app\cms\service\FormService;
use app\cms\service\FormSubmissionService;
use app\cms\service\FormValidationException;
use support\Db;
final class FormSubmissionServiceTest extends CmsTestCase
{
    private const SECRET='form-submission-test-secret';
    private const PDF="%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
    private int $now=1_800_000_000;
    private string $base;
    private string $root;
    private string $scratch;
    private FormGuard $guard;
    private FormSubmissionService $service;
    protected function setUp(): void
    {
        parent::setUp();
        \Webman\Limiter\Limiter::init(null);
        $this->base=sys_get_temp_dir().'/cms-form-submissions-'.bin2hex(random_bytes(6));
        $this->root=$this->base.'/forms';
        $this->scratch=$this->base.'/uploads';
        mkdir($this->scratch,0700,true);
        $this->guard=new FormGuard(self::SECRET,fn(): int=>$this->now);
        $this->service=new FormSubmissionService(new FormService(),new FormFileStorage($this->root),$this->guard,new FormCaptcha(self::SECRET));
    }
    protected function tearDown(): void
    {
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
    /** @return list<string> 私有存储根目录下的所有文件 */
    private function storedFiles(): array
    {
        if(!is_dir($this->root))return [];
        $files=[];
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,\FilesystemIterator::SKIP_DOTS)) as $file) if($file->isFile()) $files[]=$file->getPathname();
        return $files;
    }
    private function fields(): array
    {
        return [
            ['name'=>'name','type'=>'text','label'=>'姓名','required'=>true],
            ['name'=>'email','type'=>'email','label'=>'邮箱'],
            ['name'=>'topic','type'=>'checkbox','label'=>'主题','options'=>[['value'=>'sales','label'=>'销售'],['value'=>'support','label'=>'支持']]],
            ['name'=>'note','type'=>'textarea','label'=>'留言'],
            ['name'=>'_p','type'=>'paragraph','html'=>'<b>说明</b>'],
            ['name'=>'resume','type'=>'file','label'=>'简历','max_files'=>3],
        ];
    }
    private function form(string $siteId,string $code='contact',array $fields=[],array $settings=[]): array
    {
        return (new FormService())->save($siteId,['code'=>$code,'name'=>'联系我们','fields'=>$fields?:$this->fields(),'settings'=>$settings+['success_message'=>'已收到','rate_limit'=>60]]);
    }
    private function upload(string $name,string $content): array
    {
        $tmp=tempnam($this->scratch,'up');
        file_put_contents($tmp,$content);
        return ['tmp'=>$tmp,'name'=>$name,'size'=>strlen($content)];
    }
    private function meta(array $site,array $form,array $extra=[]): array
    {
        $token=$this->guard->issue($site['id'],$form['id']);
        $this->now+=10;
        return $extra+['ip'=>'203.0.113.7','user_agent'=>'PHPUnit','source_url'=>'https://cms.test/contact','member_id'=>null,'token'=>$token,'captcha_id'=>'','captcha'=>''];
    }
    private function submit(array $site,array $form,array $input,array $uploads=[],array $meta=[]): array
    {
        return $this->service->submit($site,'zh-cn',$form['code'],$input,$uploads,$this->meta($site,$form,$meta));
    }
    private function errors(callable $action,int $code=422): array
    {
        try{$action();}catch(FormValidationException $e){self::assertSame($code,$e->getCode());return $e->errors;}
        self::fail('Expected FormValidationException');
    }
    private function fails(callable $action,int $code,string $why): void
    {
        try{$action();self::fail('Expected failure: '.$why);}
        catch(\InvalidArgumentException $e){self::assertNotInstanceOf(FormValidationException::class,$e,$why);self::assertSame($code,$e->getCode(),$why.': '.$e->getMessage());}
    }
    private static function rows(string $table): int { return Db::table($table)->count(); }
    private function seed(array $site,array $form,string $name,string $note='',array $uploads=[]): string
    {
        $this->submit($site,$form,['name'=>$name,'note'=>$note],$uploads);
        return (string)Db::table('cms_form_submission')->max('id');
    }

    public function test_submit_stores_values_snapshot_files_and_created_event(): void
    {
        $site=$this->site();$form=$this->form($site['id']);
        $ua=str_repeat('u',300);$url='https://cms.test/'.str_repeat('p',1100);
        $result=$this->submit($site,$form,['name'=>' 张三 ','email'=>'a@example.com','topic'=>['support'],'note'=>"第一行\n第二行",'ghost'=>'x'],['resume'=>[$this->upload('简历.pdf',self::PDF),$this->upload('附件.pdf',self::PDF)]],['user_agent'=>$ua,'source_url'=>$url,'member_id'=>'42']);
        self::assertSame(['status'=>'ok','message'=>'已收到'],$result);
        $row=Db::table('cms_form_submission')->first();
        self::assertSame($site['id'],(string)$row->site_id);self::assertSame($form['id'],(string)$row->form_id);
        self::assertSame('zh-cn',$row->locale);self::assertSame('new',$row->status);self::assertSame(0,(int)$row->lock_version);
        self::assertSame(['name'=>'张三','email'=>'a@example.com','topic'=>['support'],'note'=>"第一行\n第二行"],json_decode($row->values,true),'file fields are not kept in values');
        $snapshot=json_decode($row->fields,true);
        self::assertSame(['name','email','topic','note','resume'],array_column($snapshot,'name'),'snapshot keeps data fields in order');
        self::assertSame('姓名',$snapshot[0]['label']);
        self::assertStringContainsString('张三',$row->search_text);self::assertStringContainsString('a@example.com',$row->search_text);self::assertStringContainsString('第二行',$row->search_text);
        self::assertSame(255,mb_strlen($row->user_agent));self::assertSame(1024,mb_strlen($row->source_url));
        self::assertSame('203.0.113.7',$row->ip);self::assertSame('42',(string)$row->member_id);self::assertNotNull($row->created_at);
        $events=Db::table('cms_form_event')->get();
        self::assertCount(1,$events);self::assertSame('created',$events[0]->type);self::assertSame('',$events[0]->body);self::assertNull($events[0]->admin_id);self::assertNotNull($events[0]->created_at);
        $files=Db::table('cms_form_file')->orderBy('id')->get();
        self::assertCount(2,$files);
        self::assertSame(['简历.pdf','附件.pdf'],[$files[0]->original_name,$files[1]->original_name]);
        self::assertSame('resume',$files[0]->field);self::assertSame('application/pdf',$files[0]->mime);self::assertSame(strlen(self::PDF),(int)$files[0]->size);
        self::assertSame(hash('sha256',self::PDF),$files[0]->sha256);self::assertSame((string)$row->id,(string)$files[0]->submission_id);
        self::assertStringNotContainsString($this->scratch,json_encode([$row,$files]),'the tmp path is never persisted');
        self::assertCount(2,$this->storedFiles());
        self::assertSame(self::PDF,file_get_contents($this->root.'/'.$files[0]->path));
    }

    public function test_honeypot_returns_ok_without_rows(): void
    {
        $site=$this->site();$form=$this->form($site['id']);
        $upload=$this->upload('a.pdf',self::PDF);
        $result=$this->service->submit($site,'zh-cn','contact',['name'=>'bot','_hp'=>'http://spam'],['resume'=>[$upload]],['ip'=>'203.0.113.7','token'=>'garbage']);
        self::assertSame(['status'=>'ok','message'=>'已收到'],$result);
        foreach(['cms_form_submission','cms_form_event','cms_form_file'] as $table) self::assertSame(0,self::rows($table),$table);
        self::assertSame([],$this->storedFiles());
        for($i=0;$i<60;$i++) $this->guard->throttle($site['id'],$form['id'],'203.0.113.7',60);
        $this->fails(fn()=>$this->service->submit($site,'zh-cn','missing',['_hp'=>'x'],[],[]),404,'unknown form');
        (new FormService())->setStatus($site['id'],$form['id'],false,0);
        $this->fails(fn()=>$this->service->submit($site,'zh-cn','contact',['_hp'=>'x'],[],[]),404,'disabled form');
    }

    public function test_validation_errors_are_reported_per_field(): void
    {
        $site=$this->site();$form=$this->form($site['id']);
        $errors=$this->errors(fn()=>$this->submit($site,$form,['email'=>'nope','topic'=>['ghost']],['resume'=>[$this->upload('a.php.pdf',self::PDF)]]));
        self::assertSame(['name'=>'required','email'=>'invalid_email','topic'=>'invalid_option','resume'=>'invalid_file_type'],$errors);
        $errors=$this->errors(fn()=>$this->submit($site,$form,['name'=>'x'],['resume'=>array_map(fn($i)=>$this->upload("f$i.pdf",self::PDF),range(1,4))]));
        self::assertSame(['resume'=>'too_many_files'],$errors);
        self::assertSame(['_form'=>'token_invalid'],$this->errors(fn()=>$this->service->submit($site,'zh-cn','contact',['name'=>'x'],[],['token'=>'bad','ip'=>'203.0.113.7'])));
        $token=$this->guard->issue($site['id'],$form['id']);
        self::assertSame(['_form'=>'too_fast'],$this->errors(fn()=>$this->service->submit($site,'zh-cn','contact',['name'=>'x'],[],['token'=>$token,'ip'=>'203.0.113.7'])));
        $captcha=$this->form($site['id'],'captcha',[['name'=>'name','type'=>'text','label'=>'姓名']],['captcha'=>true]);
        self::assertSame(['captcha'=>'captcha_invalid'],$this->errors(fn()=>$this->submit($site,$captcha,['name'=>'x'],[],['captcha_id'=>'forged','captcha'=>'abcde'])));
        $limited=$this->form($site['id'],'limited',[['name'=>'name','type'=>'text','label'=>'姓名']],['rate_limit'=>1]);
        $this->submit($site,$limited,['name'=>'first'],[],['ip'=>'198.51.100.1']);
        self::assertSame(['_form'=>'too_many_requests'],$this->errors(fn()=>$this->submit($site,$limited,['name'=>'second'],[],['ip'=>'198.51.100.1']),429));
        self::assertSame(1,self::rows('cms_form_submission'));self::assertSame([],$this->storedFiles());
    }

    public function test_total_upload_over_50mb_is_rejected(): void
    {
        $site=$this->site();
        $form=$this->form($site['id'],'big',[['name'=>'a','type'=>'file','label'=>'A','max_files'=>2],['name'=>'b','type'=>'file','label'=>'B']]);
        $sparse=function(string $name,int $size): array{$tmp=tempnam($this->scratch,'big');$h=fopen($tmp,'r+');ftruncate($h,$size);fclose($h);return ['tmp'=>$tmp,'name'=>$name,'size'=>$size];};
        $mb=1024*1024;
        $errors=$this->errors(fn()=>$this->submit($site,$form,[],['a'=>[$sparse('a1.zip',18*$mb),$sparse('a2.zip',18*$mb)],'b'=>[$sparse('b.zip',15*$mb)]]));
        self::assertSame(['_form'=>'files_too_large'],$errors);
        self::assertSame(0,self::rows('cms_form_submission'));self::assertSame([],$this->storedFiles());
        $this->submit($site,$form,[],['a'=>[$sparse('a1.zip',18*$mb),$sparse('a2.zip',18*$mb)],'b'=>[$sparse('b.zip',14*$mb)]]);
        self::assertSame(3,self::rows('cms_form_file'),'exactly 50MB is accepted');
    }

    public function test_failed_submission_leaves_no_files_or_rows(): void
    {
        $site=$this->site();$form=$this->form($site['id']);
        Db::schema()->drop('cms_form_file');
        try{
            $this->submit($site,$form,['name'=>'张三'],['resume'=>[$this->upload('a.pdf',self::PDF),$this->upload('b.pdf',self::PDF)]]);
            self::fail('Expected the file row insert to fail');
        }catch(\Illuminate\Database\QueryException){}
        self::assertSame(0,self::rows('cms_form_submission'));self::assertSame(0,self::rows('cms_form_event'));
        self::assertSame([],$this->storedFiles(),'stored files are removed when the transaction fails');
    }

    public function test_list_filters_by_status_form_time_and_keyword(): void
    {
        $site=$this->site();$form=$this->form($site['id']);
        $other=$this->form($site['id'],'other',[['name'=>'title','type'=>'text','label'=>'标题'],['name'=>'name','type'=>'text','label'=>'姓名']]);
        $a=$this->seed($site,$form,'Alice','苹果');
        $b=$this->seed($site,$form,'Bob','香蕉');
        $this->submit($site,$other,['title'=>'询价','name'=>'Carol']);$c=(string)Db::table('cms_form_submission')->max('id');
        $d=$this->seed($site,$form,'Dave');
        Db::table('cms_form_submission')->where('id',$a)->update(['created_at'=>'2026-01-05 08:00:00']);
        Db::table('cms_form_submission')->where('id',$b)->update(['created_at'=>'2026-01-10 23:00:00']);
        $this->service->transition($site['id'],$b,'1','processing',0);
        $this->service->transition($site['id'],$d,'1','done',0);
        $ids=fn(array $filters,int $page=1)=>array_column($this->service->list($site['id'],$filters,$page)['list'],'id');
        $page=$this->service->list($site['id'],[],1);
        self::assertSame([$c,$b,$a],array_column($page['list'],'id'),'defaults to new and processing, newest first');
        self::assertSame(3,$page['total']);self::assertSame(1,$page['page']);self::assertSame(20,$page['pageSize']);
        $item=$page['list'][0];
        self::assertSame([['label'=>'标题','value'=>'询价'],['label'=>'姓名','value'=>'Carol']],$item['summary']);
        self::assertSame('other',$item['form_code']);self::assertSame('new',$item['status']);
        self::assertSame([['label'=>'姓名','value'=>'Bob'],['label'=>'留言','value'=>'香蕉']],$page['list'][1]['summary'],'summary skips empty text fields');
        self::assertSame([$d],$ids(['status'=>['done']]));
        self::assertSame([$d,$b,$a],$ids(['form_id'=>$form['id'],'status'=>['new','processing','done']]));
        self::assertSame([$b],$ids(['from'=>'2026-01-06','to'=>'2026-01-10']),'to is inclusive of the whole day');
        self::assertSame([$a],$ids(['to'=>'2026-01-05']));
        self::assertSame([$a],$ids(['keyword'=>'苹果']));
        self::assertSame([],$ids(['keyword'=>'Dave']),'keyword still honors the default status filter');
        self::assertSame([],$ids([],2));
        $this->fails(fn()=>$this->service->list($site['id'],['status'=>['bogus']],1),422,'unknown status');
        $this->fails(fn()=>$this->service->list($site['id'],['from'=>'yesterday'],1),422,'bad date');
        self::assertSame([],$this->service->list($this->site('b','b.test')['id'],[],1)['list']);
    }

    public function test_detail_uses_snapshot_after_form_changes(): void
    {
        $site=$this->site();$form=$this->form($site['id']);
        $id=$this->seed($site,$form,'张三','你好',['resume'=>[$this->upload('简历.pdf',self::PDF)]]);
        Db::table('cms_form_submission')->where('id',$id)->update(['values'=>json_encode(['name'=>'张三','topic'=>['support','sales'],'note'=>'你好'],JSON_UNESCAPED_UNICODE)]);
        (new FormService())->save($site['id'],['id'=>$form['id'],'lock_version'=>0,'code'=>'contact','name'=>'新名称','fields'=>[['name'=>'phone','type'=>'phone','label'=>'电话'],['name'=>'name','type'=>'text','label'=>'名字']]]);
        $detail=$this->service->detail($site['id'],$id);
        self::assertSame($id,$detail['id']);self::assertSame('new',$detail['status']);self::assertSame(0,$detail['lock_version']);
        self::assertSame(['name','email','topic','note','resume'],array_column($detail['fields'],'name'));
        self::assertSame(['姓名','邮箱','主题','留言','简历'],array_column($detail['fields'],'label'));
        self::assertSame('张三',$detail['fields'][0]['value']);self::assertNull($detail['fields'][1]['value']);
        self::assertSame(['support','sales'],$detail['fields'][2]['value']);self::assertSame('支持, 销售',$detail['fields'][2]['text']);
        $file=$detail['files'][0];
        self::assertSame(['id','field','name','mime','size','created_at'],array_keys($file),'no storage path or sha leaks to the client beyond metadata');
        self::assertSame('简历.pdf',$file['name']);self::assertSame('resume',$file['field']);
        self::assertSame([$file],$detail['fields'][4]['files']);self::assertSame('简历.pdf',$detail['fields'][4]['text']);
        self::assertSame(['created'],array_column($detail['events'],'type'));
        $this->fails(fn()=>$this->service->detail($site['id'],'abc'),0,'invalid id');
        $this->fails(fn()=>$this->service->detail($site['id'],'999'),404,'missing id');
    }

    public function test_note_and_transition_append_events(): void
    {
        $site=$this->site();$form=$this->form($site['id']);$id=$this->seed($site,$form,'张三');
        $detail=$this->service->note($site['id'],$id,'7',"  跟进\n电话  ");
        self::assertSame(0,$detail['lock_version'],'notes do not bump the version');
        $note=end($detail['events']);
        self::assertSame(['type'=>'note','admin_id'=>'7','body'=>"跟进\n电话",'from_status'=>'','to_status'=>''],array_intersect_key($note,array_flip(['type','admin_id','body','from_status','to_status'])));
        $this->fails(fn()=>$this->service->note($site['id'],$id,'7','   '),422,'blank note');
        $this->fails(fn()=>$this->service->note($site['id'],$id,'7',str_repeat('字',2001)),422,'long note');
        $this->service->note($site['id'],$id,'7',str_repeat('字',2000));
        $detail=$this->service->transition($site['id'],$id,'7','processing',0);
        self::assertSame('processing',$detail['status']);self::assertSame(1,$detail['lock_version']);self::assertNull($detail['handled_at']);
        $this->fails(fn()=>$this->service->transition($site['id'],$id,'7','processing',1),422,'same status');
        $this->fails(fn()=>$this->service->transition($site['id'],$id,'7','archived',1),422,'unknown status');
        $detail=$this->service->transition($site['id'],$id,'8','done',1);
        self::assertSame('done',$detail['status']);self::assertSame(2,$detail['lock_version']);self::assertNotNull($detail['handled_at']);
        $detail=$this->service->transition($site['id'],$id,'8','new',2);
        self::assertSame(['created','note','note','status','status','status'],array_column($detail['events'],'type'));
        $status=array_values(array_filter($detail['events'],fn($e)=>$e['type']==='status'));
        self::assertSame([['new','processing','7'],['processing','done','8'],['done','new','8']],array_map(fn($e)=>[$e['from_status'],$e['to_status'],$e['admin_id']],$status));
        $other=$this->seed($site,$form,'李四');
        self::assertSame(2,$this->service->invalidate($site['id'],[$id,$other,$other],'9'));
        self::assertSame(0,$this->service->invalidate($site['id'],[$id],'9'),'already invalid rows are skipped');
        $detail=$this->service->detail($site['id'],$other);
        self::assertSame('invalid',$detail['status']);self::assertSame(1,$detail['lock_version']);self::assertNotNull($detail['handled_at']);
        self::assertSame(['new','invalid','9'],[end($detail['events'])['from_status'],end($detail['events'])['to_status'],end($detail['events'])['admin_id']]);
        self::assertSame(9,self::rows('cms_form_event'));
    }

    public function test_concurrent_transition_conflicts(): void
    {
        $site=$this->site();$form=$this->form($site['id']);$id=$this->seed($site,$form,'张三');
        $this->service->transition($site['id'],$id,'1','processing',0);
        $this->fails(fn()=>$this->service->transition($site['id'],$id,'2','done',0),409,'stale version');
        $this->service->invalidate($site['id'],[$id],'3');
        $this->fails(fn()=>$this->service->transition($site['id'],$id,'2','done',1),409,'version bumped by batch invalidate');
        self::assertSame('invalid',$this->service->detail($site['id'],$id)['status']);
        self::assertSame(3,self::rows('cms_form_event'));
    }

    public function test_export_merges_columns_across_snapshots(): void
    {
        $site=$this->site();$form=$this->form($site['id']);
        $this->submit($site,$form,['name'=>'=SUM(A1)','topic'=>['sales','support'],'note'=>"多行\n文本"],['resume'=>[$this->upload('简历.pdf',self::PDF),$this->upload('作品.pdf',self::PDF)]]);
        (new FormService())->save($site['id'],['id'=>$form['id'],'lock_version'=>0,'code'=>'contact','name'=>'联系我们','fields'=>[['name'=>'name','type'=>'text','label'=>'名字'],['name'=>'phone','type'=>'phone','label'=>'电话'],['name'=>'agree','type'=>'consent','label'=>'同意']],'settings'=>['rate_limit'=>60]]);
        $this->submit($site,$form,['name'=>'李四','phone'=>'+86 123456','agree'=>'1']);
        $done=$this->seed($site,$form,'王五');
        $this->service->transition($site['id'],$done,'1','done',0);
        $rows=iterator_to_array($this->service->export($site['id'],[]),false);
        self::assertSame(['提交时间','表单','状态','来源页面','名字','电话','同意','邮箱','主题','留言','简历'],$rows[0],'newest snapshot labels first, older-only fields appended');
        self::assertCount(3,$rows,'export honors the default status filter');
        self::assertSame(['联系我们','新提交','https://cms.test/contact','李四',"'+86 123456",'是','','','',''],array_slice($rows[1],1));
        self::assertSame(["'=SUM(A1)",'','','','销售, 支持',"多行\n文本",'简历.pdf, 作品.pdf'],array_slice($rows[2],4));
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$rows[2][0]);
        $all=iterator_to_array($this->service->export($site['id'],['status'=>['new','processing','done','invalid']]),false);
        self::assertCount(4,$all);self::assertSame('已完成',$all[1][2]);
    }

    public function test_delete_removes_files(): void
    {
        $site=$this->site();$form=$this->form($site['id']);
        $a=$this->seed($site,$form,'张三','',['resume'=>[$this->upload('a.pdf',self::PDF),$this->upload('b.pdf',self::PDF)]]);
        $b=$this->seed($site,$form,'李四','',['resume'=>[$this->upload('c.pdf',self::PDF)]]);
        $this->service->note($site['id'],$a,'1','备注');
        self::assertCount(3,$this->storedFiles());
        $missing=(string)Db::table('cms_form_file')->where('submission_id',$a)->value('path');
        unlink($this->root.'/'.$missing);
        self::assertSame(1,$this->service->delete($site['id'],[$a,'999999']));
        self::assertSame([$b],array_map('strval',Db::table('cms_form_submission')->pluck('id')->all()));
        self::assertSame(0,Db::table('cms_form_file')->where('submission_id',$a)->count());
        self::assertSame(0,Db::table('cms_form_event')->where('submission_id',$a)->count());
        self::assertCount(1,$this->storedFiles(),'the remaining submission keeps its file');
        $this->fails(fn()=>$this->service->detail($site['id'],$a),404,'deleted submission');
        self::assertSame(0,$this->service->delete($site['id'],[]));
    }

    public function test_other_site_cannot_read_submission_or_file(): void
    {
        $site=$this->site();$other=$this->site('b','b.test');$form=$this->form($site['id']);
        $id=$this->seed($site,$form,'张三','',['resume'=>[$this->upload('a.pdf',self::PDF)]]);
        $fileId=(string)Db::table('cms_form_file')->value('id');
        $file=$this->service->file($site['id'],$fileId);
        self::assertSame(['absolute','name','mime'],array_keys($file));
        self::assertSame('a.pdf',$file['name']);self::assertSame('application/pdf',$file['mime']);self::assertSame(self::PDF,file_get_contents($file['absolute']));
        $this->fails(fn()=>$this->service->file($other['id'],$fileId),404,'file from other site');
        $this->fails(fn()=>$this->service->detail($other['id'],$id),404,'detail from other site');
        $this->fails(fn()=>$this->service->note($other['id'],$id,'1','x'),404,'note from other site');
        $this->fails(fn()=>$this->service->transition($other['id'],$id,'1','done',0),404,'transition from other site');
        self::assertSame(0,$this->service->invalidate($other['id'],[$id],'1'));
        self::assertSame(0,$this->service->delete($other['id'],[$id]));
        self::assertSame([],iterator_to_array($this->service->export($other['id'],[]),false)[1] ?? []);
        self::assertSame([],$this->service->list($other['id'],['form_id'=>$form['id']],1)['list']);
        $this->fails(fn()=>$this->service->submit($other,'zh-cn','contact',['name'=>'x'],[],$this->meta($other,$form)),404,'form code from other site');
        self::assertSame(1,self::rows('cms_form_submission'));self::assertSame('new',$this->service->detail($site['id'],$id)['status']);
        self::assertCount(1,$this->storedFiles());
    }
}
