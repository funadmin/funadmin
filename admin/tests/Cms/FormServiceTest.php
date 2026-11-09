<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\cms\service\FormService;
use app\cms\service\SiteService;
use support\Db;
final class FormServiceTest extends CmsTestCase
{
    private function fields(): array
    {
        return [
            ['name'=>'name','type'=>'text','label'=>'姓名','placeholder'=>'请输入姓名','help'=>'真实姓名'],
            ['name'=>'topic','type'=>'radio','label'=>'主题','options'=>[['value'=>'sales','label'=>'销售'],['value'=>'support','label'=>'支持']]],
            ['name'=>'note','type'=>'textarea','label'=>'留言'],
        ];
    }
    private function fails(callable $action,int $code,string $why,?string $message=null): void
    {
        try{$action();self::fail('Expected failure: '.$why);}
        catch(\InvalidArgumentException $e){self::assertSame($code,$e->getCode(),$why.': '.$e->getMessage());if($message!==null)self::assertSame($message,$e->getMessage());}
    }
    private function submission(string $siteId,string $formId,string $status='new'): void
    {
        $now=gmdate('Y-m-d H:i:s');
        Db::table('cms_form_submission')->insert(['site_id'=>$siteId,'form_id'=>$formId,'locale'=>'zh-cn','fields'=>'[]','values'=>'{}','search_text'=>'','status'=>$status,'created_at'=>$now,'updated_at'=>$now]);
    }
    public function test_save_validates_code_uniqueness_and_optimistic_lock(): void
    {
        $a=$this->site('a','a.test');$b=$this->site('b','b.test');$service=new FormService();
        $form=$service->save($a['id'],['code'=>'contact','name'=>' 联系我们 ','fields'=>$this->fields()]);
        self::assertSame('contact',$form['code']);self::assertSame('联系我们',$form['name']);self::assertSame($a['id'],$form['site_id']);
        self::assertTrue($form['enabled']);self::assertSame(0,$form['lock_version']);self::assertSame(['name','topic','note'],array_column($form['fields'],'name'));
        self::assertSame($form,$service->get($a['id'],$form['id']));
        $this->fails(fn()=>$service->save($a['id'],['code'=>'contact','name'=>'重复','fields'=>[]]),409,'duplicate code in same site');
        $other=$service->save($b['id'],['code'=>'contact','name'=>'B 站联系','fields'=>[]]);
        self::assertSame($b['id'],$other['site_id']);
        $this->fails(fn()=>$service->save($a['id'],['code'=>'Bad Code','name'=>'X','fields'=>[]]),0,'invalid code');
        $this->fails(fn()=>$service->save($a['id'],['code'=>'empty','name'=>'  ','fields'=>[]]),422,'blank name');
        $this->fails(fn()=>$service->save($a['id'],['code'=>'bad','name'=>'X','fields'=>[['name'=>'a','type'=>'richtext']]]),422,'invalid fields');
        $updated=$service->save($a['id'],['id'=>$form['id'],'lock_version'=>0,'code'=>'contact-us','name'=>'联系','fields'=>$form['fields']]);
        self::assertSame(1,$updated['lock_version']);self::assertSame('contact-us',$updated['code']);self::assertSame($form['fields'],$updated['fields']);
        $this->fails(fn()=>$service->save($a['id'],['id'=>$form['id'],'lock_version'=>0,'code'=>'contact-us','name'=>'旧版本','fields'=>[]]),409,'stale lock_version');
        $this->fails(fn()=>$service->save($a['id'],['id'=>$form['id'],'code'=>'contact-us','name'=>'无版本','fields'=>[]]),409,'missing lock_version');
        $second=$service->save($a['id'],['code'=>'second','name'=>'第二个','fields'=>[]]);
        $this->fails(fn()=>$service->save($a['id'],['id'=>$second['id'],'lock_version'=>0,'code'=>'contact-us','name'=>'抢占','fields'=>[]]),409,'rename onto existing code');
        $this->fails(fn()=>$service->save($b['id'],['id'=>$form['id'],'lock_version'=>1,'code'=>'contact-us','name'=>'跨站','fields'=>[]]),404,'update other site form');
        $this->fails(fn()=>$service->get($b['id'],$form['id']),404,'get other site form');
        self::assertSame('联系',$service->get($a['id'],$form['id'])['name']);
    }
    public function test_texts_fall_back_to_default_locale(): void
    {
        $site=$this->site();(new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);$service=new FormService();
        $form=$service->save($site['id'],['code'=>'contact','name'=>'联系我们','fields'=>$this->fields(),'settings'=>['success_message'=>'已收到','submit_text'=>'提交'],'texts'=>[
            'zh-cn'=>['name'=>'联系','submit_text'=>'发送','fields'=>['name'=>['label'=>'您的姓名','help'=>'中文说明'],'topic'=>['options'=>['support'=>'技术支持']]]],
            'EN-US'=>['name'=>'Contact us','success_message'=>'Thanks','fields'=>['name'=>['label'=>'Your name','placeholder'=>''],'topic'=>['label'=>'Topic','options'=>['sales'=>'Sales','ghost'=>'Ghost']],'ghost'=>['label'=>'Unknown']]],
        ]]);
        self::assertSame(['zh-cn','en-us'],array_keys($form['texts']));
        self::assertArrayNotHasKey('ghost',$form['texts']['en-us']['fields'],'unknown field names are dropped');
        self::assertSame(['sales'=>'Sales'],$form['texts']['en-us']['fields']['topic']['options'],'unknown option values are dropped');
        self::assertArrayNotHasKey('placeholder',$form['texts']['en-us']['fields']['name'],'blank overrides are not stored');

        $en=$service->resolve($site['id'],'contact','en-us');
        self::assertSame('Contact us',$en['name']);
        self::assertSame('Thanks',$en['settings']['success_message']);
        self::assertSame('发送',$en['settings']['submit_text'],'falls back to the default locale');
        $name=$en['fields'][0];$topic=$en['fields'][1];$note=$en['fields'][2];
        self::assertSame('Your name',$name['label']);
        self::assertSame('请输入姓名',$name['placeholder'],'falls back to the field value when no locale sets it');
        self::assertSame('中文说明',$name['help'],'falls back to the default locale');
        self::assertSame('Topic',$topic['label']);
        self::assertSame([['value'=>'sales','label'=>'Sales'],['value'=>'support','label'=>'技术支持']],$topic['options']);
        self::assertSame('留言',$note['label']);
        self::assertArrayNotHasKey('texts',$en);

        $zh=$service->resolve($site['id'],'contact','zh-cn');
        self::assertSame('联系',$zh['name']);self::assertSame('已收到',$zh['settings']['success_message']);self::assertSame('您的姓名',$zh['fields'][0]['label']);
        self::assertSame($zh,$service->resolve($site['id'],'contact','fr-fr'),'a locale outside the site resolves like the default locale');

        $this->fails(fn()=>$service->save($site['id'],['id'=>$form['id'],'lock_version'=>0,'code'=>'contact','name'=>'X','fields'=>$this->fields(),'texts'=>['fr-fr'=>['name'=>'Contactez-nous']]]),422,'locale outside site');
        $kept=$service->save($site['id'],['id'=>$form['id'],'lock_version'=>0,'code'=>'contact','name'=>'X','fields'=>[['name'=>'note','type'=>'textarea','label'=>'留言']]]);
        self::assertSame(['zh-cn'=>['name'=>'联系','submit_text'=>'发送'],'en-us'=>['name'=>'Contact us','success_message'=>'Thanks']],$kept['texts'],'texts of removed fields are dropped when fields change');
    }
    public function test_resolve_hides_disabled_and_other_site_forms(): void
    {
        $a=$this->site('a','a.test');$b=$this->site('b','b.test');$service=new FormService();
        $form=$service->save($a['id'],['code'=>'contact','name'=>'联系','fields'=>$this->fields()]);
        self::assertSame($form['id'],$service->resolve($a['id'],'contact','zh-cn')['id']);
        self::assertNull($service->resolve($b['id'],'contact','zh-cn'),'other site cannot resolve the code');
        self::assertNull($service->resolve($a['id'],'missing','zh-cn'));
        self::assertNull($service->resolve($a['id'],'../contact','zh-cn'),'invalid code resolves to nothing');
        $this->fails(fn()=>$service->setStatus($b['id'],$form['id'],false,0),404,'other site cannot disable');
        $this->fails(fn()=>$service->setStatus($a['id'],$form['id'],false,3),409,'stale version');
        $disabled=$service->setStatus($a['id'],$form['id'],false,0);
        self::assertFalse($disabled['enabled']);self::assertSame(1,$disabled['lock_version']);
        self::assertNull($service->resolve($a['id'],'contact','zh-cn'),'disabled form is hidden');
        self::assertSame(0,(int)Db::table('cms_form')->where('id',$form['id'])->value('status'));
        $created=$service->save($a['id'],['code'=>'off','name'=>'停用','enabled'=>false,'fields'=>[]]);
        self::assertFalse($created['enabled']);self::assertNull($service->resolve($a['id'],'off','zh-cn'));
        self::assertTrue($service->setStatus($a['id'],$form['id'],true,1)['enabled']);
        self::assertNotNull($service->resolve($a['id'],'contact','zh-cn'));
    }
    public function test_delete_refuses_when_submissions_exist(): void
    {
        $a=$this->site('a','a.test');$b=$this->site('b','b.test');$service=new FormService();
        $used=$service->save($a['id'],['code'=>'contact','name'=>'联系','fields'=>$this->fields()]);
        $empty=$service->save($a['id'],['code'=>'empty','name'=>'空表单','fields'=>[]]);
        $foreign=$service->save($b['id'],['code'=>'contact','name'=>'B','fields'=>[]]);
        $this->submission($a['id'],$used['id']);$this->submission($a['id'],$used['id']);$this->submission($a['id'],$used['id'],'processing');
        $this->submission($b['id'],$foreign['id']);
        $this->fails(fn()=>$service->delete($a['id'],$used['id']),409,'form with submissions','表单已有提交，只能停用');
        $this->fails(fn()=>$service->delete($b['id'],$empty['id']),404,'other site cannot delete');
        $list=array_column($service->list($a['id']),null,'code');
        self::assertSame(['contact','empty'],array_keys($list));
        self::assertSame([3,2],[$list['contact']['submission_count'],$list['contact']['new_count']]);
        self::assertSame([0,0],[$list['empty']['submission_count'],$list['empty']['new_count']]);
        self::assertSame(2,$service->newCount($a['id']));self::assertSame(1,$service->newCount($b['id']));
        $service->delete($a['id'],$empty['id']);
        $this->fails(fn()=>$service->get($a['id'],$empty['id']),404,'deleted form is gone');
        self::assertSame(['contact'],array_column($service->list($a['id']),'code'));
    }
    public function test_settings_rate_limit_is_clamped_to_60(): void
    {
        $site=$this->site();$service=new FormService();
        $defaults=$service->save($site['id'],['code'=>'defaults','name'=>'默认','fields'=>[]]);
        self::assertSame(['success_message'=>'','submit_text'=>'','captcha'=>false,'rate_limit'=>5],$defaults['settings']);
        $high=$service->save($site['id'],['code'=>'high','name'=>'上限','fields'=>[],'settings'=>['success_message'=>"　已收到 ",'submit_text'=>' 发送 ','captcha'=>true,'rate_limit'=>100,'extra'=>'x']]);
        self::assertSame(['success_message'=>'已收到','submit_text'=>'发送','captcha'=>true,'rate_limit'=>60],$high['settings']);
        self::assertSame(1,$service->save($site['id'],['code'=>'low','name'=>'下限','fields'=>[],'settings'=>['rate_limit'=>0]])['settings']['rate_limit']);
        self::assertSame(12,$service->save($site['id'],['code'=>'numeric','name'=>'字符串','fields'=>[],'settings'=>['rate_limit'=>'12']])['settings']['rate_limit']);
        $this->fails(fn()=>$service->save($site['id'],['code'=>'bad1','name'=>'X','fields'=>[],'settings'=>['rate_limit'=>'many']]),422,'non-numeric rate limit');
        $this->fails(fn()=>$service->save($site['id'],['code'=>'bad2','name'=>'X','fields'=>[],'settings'=>['captcha'=>'yes']]),422,'captcha must be bool');
        $this->fails(fn()=>$service->save($site['id'],['code'=>'bad3','name'=>'X','fields'=>[],'settings'=>'oops']),422,'settings must be an object');
        $kept=$service->save($site['id'],['id'=>$high['id'],'lock_version'=>0,'code'=>'high','name'=>'上限']);
        self::assertSame($high['settings'],$kept['settings'],'omitted settings keep stored values');
        self::assertSame(60,$service->resolve($site['id'],'high','zh-cn')['settings']['rate_limit']);
    }
}
