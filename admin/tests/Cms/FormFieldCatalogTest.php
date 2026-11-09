<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\cms\service\FormFieldCatalog;
use PHPUnit\Framework\TestCase;
final class FormFieldCatalogTest extends TestCase
{
    private function rejects(array $fields,string $why): void
    {
        try{FormFieldCatalog::normalize($fields);self::fail('Expected rejection: '.$why);}
        catch(\InvalidArgumentException $e){self::assertSame(422,$e->getCode(),$why);}
    }
    public function test_types_match_spec_whitelist(): void
    {
        self::assertSame(['text','textarea','email','phone','number','radio','checkbox','select','date','datetime','time','consent','file','image','hidden','paragraph','divider'],FormFieldCatalog::TYPES);
        self::assertSame(array_values(array_diff(FormFieldCatalog::TYPES,['paragraph','divider'])),FormFieldCatalog::DATA_TYPES);
    }
    public function test_normalize_rejects_unknown_type_duplicate_name_and_limits_over_hard_caps(): void
    {
        $this->rejects([['name'=>'a','type'=>'richtext','label'=>'A']],'unknown type');
        $this->rejects([['name'=>'a','type'=>'text','label'=>'A'],['name'=>'a','type'=>'email','label'=>'B']],'duplicate name');
        $this->rejects([['name'=>'Bad-Name','type'=>'text','label'=>'A']],'invalid name');
        $this->rejects([['name'=>'_hp','type'=>'text','label'=>'A']],'name must start with a letter');
        $this->rejects([['name'=>str_repeat('a',65),'type'=>'text','label'=>'A']],'name too long');
        $this->rejects([['name'=>'f','type'=>'file','label'=>'F','max_size'=>20*1024*1024+1]],'max_size over 20MB');
        $this->rejects([['name'=>'f','type'=>'file','label'=>'F','max_files'=>11]],'max_files over 10');
        $this->rejects([['name'=>'f','type'=>'file','label'=>'F','accept'=>['php']]],'executable extension');
        $this->rejects([['name'=>'f','type'=>'image','label'=>'F','accept'=>['pdf']]],'image field only accepts images');
        $this->rejects([['name'=>'a','type'=>'text','label'=>'A','max'=>2001]],'text max over cap');
        $this->rejects([['name'=>'a','type'=>'number','label'=>'A','min'=>5,'max'=>1]],'min greater than max');
        $this->rejects([['name'=>'a','type'=>'radio','label'=>'A','options'=>[]]],'options required');
        $this->rejects([['name'=>'a','type'=>'radio','label'=>'A','options'=>[['value'=>'x','label'=>'X'],['value'=>'x','label'=>'Y']]]],'duplicate option');
        $this->rejects([['name'=>'a','type'=>'text','label'=>'A','width'=>'third']],'invalid width');
        $this->rejects([['name'=>'a','type'=>'text','label'=>'A','required'=>'yes']],'required must be bool');
        $this->rejects([['name'=>'a','type'=>'date','label'=>'A','min'=>'2026-02-30']],'invalid date bound');
        $this->rejects([['name'=>'a','type'=>'email','label'=>'A','default'=>'not-an-email']],'invalid default');
        $this->rejects(array_map(fn(int $i)=>['name'=>'f'.$i,'type'=>'text','label'=>'F'],range(1,101)),'more than 100 fields');
        $this->rejects(['x'=>['name'=>'a','type'=>'text','label'=>'A']],'fields must be a list');
    }
    public function test_normalize_rejects_names_reserved_for_builtin_controls(): void
    {
        foreach(['captcha','captcha_id','hp','token','locale'] as $name) $this->rejects([['name'=>$name,'type'=>'text','label'=>'A']],'reserved name '.$name);
        self::assertSame(['captchas','source'],array_column(FormFieldCatalog::normalize([['name'=>'captchas','type'=>'text','label'=>'A'],['name'=>'source','type'=>'hidden','from_query'=>true]]),'name'),'similar names and the common source channel field stay allowed');
    }
    public function test_normalize_fills_defaults_names_display_fields_and_drops_unknown_keys(): void
    {
        $fields=FormFieldCatalog::normalize([
            ['type'=>'paragraph','html'=>'<p>Intro</p>'],
            ['name'=>'name','type'=>'text','label'=>' Name ','required'=>true,'max'=>50,'evil'=>'x'],
            ['type'=>'divider'],
            ['type'=>'paragraph','html'=>'Second'],
            ['name'=>'attachment','type'=>'file','label'=>'File'],
            ['name'=>'photo','type'=>'image','label'=>'Photo','accept'=>['png'],'max_size'=>1024,'max_files'=>3],
            ['name'=>'topic','type'=>'select','label'=>'Topic','multiple'=>true,'options'=>[['value'=>'a','label'=>'A'],['value'=>'b']],'max'=>2,'width'=>'half'],
            ['name'=>'source','type'=>'hidden','value'=>'web','from_query'=>true],
        ]);
        self::assertSame(['_p1','name','_d1','_p2','attachment','photo','topic','source'],array_column($fields,'name'));
        self::assertSame(['name'=>'name','type'=>'text','label'=>'Name','width'=>'full','placeholder'=>'','help'=>'','required'=>true,'default'=>null,'max'=>50],$fields[1]);
        self::assertSame(['document','image','archive'],$fields[4]['accept']);
        self::assertSame(20*1024*1024,$fields[4]['max_size']);
        self::assertSame(1,$fields[4]['max_files']);
        self::assertSame(['png'],$fields[5]['accept']);
        self::assertSame([['value'=>'a','label'=>'A'],['value'=>'b','label'=>'b']],$fields[6]['options']);
        self::assertTrue($fields[6]['multiple']);
        self::assertSame('half',$fields[6]['width']);
        self::assertSame('web',$fields[7]['value']);
        self::assertTrue($fields[7]['from_query']);
        self::assertSame(FormFieldCatalog::normalize($fields),$fields,'normalize must be idempotent');
    }
    public function test_normalize_is_idempotent_for_entity_heavy_html_near_cap(): void
    {
        $raw=str_repeat('a & b &lt; c ',1176);
        $once=FormFieldCatalog::normalize([['type'=>'paragraph','html'=>$raw],['name'=>'agree','type'=>'consent','label'=>'Agree','html'=>$raw]]);
        self::assertGreaterThan(19900,mb_strlen($once[0]['html']),'sanitized output must sit close to the cap');
        self::assertLessThanOrEqual(20000,mb_strlen($once[1]['html']));
        self::assertSame($once,FormFieldCatalog::normalize($once));
        $edges=FormFieldCatalog::normalize([['type'=>'paragraph','html'=>"<script>x</script> hi \u{3000}<script>y</script>"]]);
        self::assertSame('hi',$edges[0]['html']);
        self::assertSame($edges,FormFieldCatalog::normalize($edges));
        try{FormFieldCatalog::normalize([['type'=>'paragraph','html'=>str_repeat('a & b ',3333)]]);self::fail('Sanitized html over the cap must be rejected up front');}
        catch(\InvalidArgumentException $e){self::assertSame(422,$e->getCode());}
    }
    public function test_normalize_trims_unicode_separators_and_control_chars_from_texts_and_options(): void
    {
        $fields=FormFieldCatalog::normalize([['name'=>'pick','type'=>'radio','label'=>"\u{3000}Pick\u{00A0}",'options'=>[['value'=>"\u{3000} a\x01 ",'label'=>"\u{2003}A"]]]]);
        self::assertSame('Pick',$fields[0]['label']);
        self::assertSame([['value'=>'a','label'=>'A']],$fields[0]['options']);
        try{FormFieldCatalog::normalize([['name'=>'pick','type'=>'radio','label'=>'P','options'=>[['value'=>"\u{3000}"]]]]);self::fail('Separator-only option value must be rejected');}
        catch(\InvalidArgumentException $e){self::assertSame(422,$e->getCode());}
    }
    public function test_normalize_sanitizes_consent_and_paragraph_html(): void
    {
        $fields=FormFieldCatalog::normalize([
            ['type'=>'paragraph','html'=>'<p>Hello<script>alert(1)</script> <a href="/terms" onclick="x()">terms</a></p>'],
            ['name'=>'agree','type'=>'consent','label'=>'Agree','required'=>true,'html'=>'I accept the <a href="https://example.com/privacy">policy</a><script>steal()</script><img src=x onerror=alert(1)><b>now</b>'],
        ]);
        foreach([$fields[0]['html'],$fields[1]['html']] as $html){
            self::assertStringNotContainsString('<script',$html);
            self::assertStringNotContainsString('alert(1)',$html);
            self::assertStringNotContainsString('onclick',$html);
            self::assertStringNotContainsString('onerror',$html);
        }
        self::assertStringContainsString('<a href="/terms">terms</a>',$fields[0]['html']);
        self::assertStringContainsString('<a href="https://example.com/privacy">policy</a>',$fields[1]['html']);
        self::assertStringContainsString('<b>now</b>',$fields[1]['html']);
    }
}
