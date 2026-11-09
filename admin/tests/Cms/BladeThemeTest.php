<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\cms\template\{ThemeEngine,RenderContext,TagRegistry,TrustedHtml};
use app\cms\tags\CallbackTag;
use PHPUnit\Framework\TestCase;
final class BladeThemeTest extends TestCase
{
    private string $root;
    protected function setUp():void {$this->root=sys_get_temp_dir().'/cms-blade-'.bin2hex(random_bytes(6));mkdir($this->root.'/partials',0700,true);}
    protected function tearDown():void {
        $files=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($this->root);
    }
    private function put(string $name,string $source):void {file_put_contents($this->root.'/'.$name,$source);}
    private function render(string $name,array $vars=[],?TagRegistry $registry=null):string {return(new ThemeEngine($registry??new TagRegistry(),$this->root))->renderFile($name,$vars,new RenderContext('7','zh-cn'));}
    public function test_native_blade_inheritance_includes_loops_and_escaping():void {
        $this->put('layout.blade.php','<main>@yield(\'body\')</main>');
        $this->put('partials/footer.blade.php','<footer>{{ $title }}</footer>');
        $this->put('index.blade.php',"@extends('layout')\n@section('body')<h1>{{ \$title }}</h1>@foreach(\$items as \$item)<b>{{ \$loop->iteration }}:{{ \$item }}</b>@endforeach{{ \$body }}@include('partials.footer')@endsection");
        self::assertSame('<main><h1>&lt;Title&gt;</h1><b>1:&lt;one&gt;</b><b>2:two</b><p>safe</p><footer>&lt;Title&gt;</footer></main>',$this->render('index',['title'=>'<Title>','items'=>['<one>','two'],'body'=>new TrustedHtml('<p>safe</p>')]));
    }
    public function test_blade_precedes_html_but_legacy_html_remains_supported():void {
        $this->put('index.html','legacy {$title}');self::assertSame('legacy &lt;x&gt;',$this->render('index',['title'=>'<x>']));
        $this->put('index.blade.php','blade {{ $title }}');self::assertSame('blade &lt;x&gt;',$this->render('index',['title'=>'<x>']));
    }
    public function test_legacy_html_page_can_include_migrated_blade_partial():void {
        $this->put('index.html','{cms:include file="partials/footer" /}');
        $this->put('partials/footer.blade.php','<footer>{{ $title }}</footer>');
        self::assertSame('<footer>&lt;legacy&gt;</footer>',$this->render('index',['title'=>'<legacy>']));
    }
    public function test_legacy_include_prefers_blade_when_both_suffixes_exist():void {
        $this->put('index.html','{cms:include file="partials/footer" /}');
        $this->put('partials/footer.html','OLD');$this->put('partials/footer.blade.php','NEW');
        self::assertSame('NEW',$this->render('partials/footer'));
        self::assertSame('NEW',$this->render('index'));
    }
    public function test_blade_alias_recursion_is_rejected_by_restricted_fragment_engine():void {
        $r=new TagRegistry();$r->register('text',new CallbackTag([],static fn()=>'x'));$r->alias('cycle','text',[],'{cms:cycle /}');
        $this->put('index.blade.php',"{{ \$cms->tag('cycle') }}");
        $this->expectExceptionMessage('自定义标签循环调用');$this->render('index',[],$r);
    }
    public function test_html_blade_and_database_fragments_share_one_call_budget():void {
        $r=new TagRegistry();$r->register('text',new CallbackTag([],static fn()=>'x'));$r->alias('notice','text',[],'{cms:text /}');
        $this->put('index.html',str_repeat('{cms:text /}',199).'{cms:include file="partials/footer" /}');
        $this->put('partials/footer.blade.php',"{{ \$cms->tag('notice') }}");
        $this->expectExceptionMessage('整页标签调用超过200次');$this->render('index',[],$r);
    }
    public function test_cms_tags_keep_context_and_database_fragments_do_not_execute_blade():void {
        $registry=new TagRegistry();$registry->register('content',new CallbackTag([],static fn($p,$c)=>[['title'=>'<News>','site'=>$c->siteId]]));
        $registry->alias('featured','content',[],'<i>{$item.title}</i>@php throw new Exception("must not run"); @endphp {{ 7 * 7 }}');
        $this->put('index.blade.php',"@foreach(\$cms->items('content') as \$item)<b>{{ \$item['site'] }}:{{ \$item['title'] }}</b>@endforeach{{ \$cms->tag('featured') }}");
        self::assertSame('<b>7:&lt;News&gt;</b><i>&lt;News&gt;</i>@php throw new Exception("must not run"); @endphp {{ 7 * 7 }}',$this->render('index',[],$registry));
    }
    public function test_native_data_and_fragment_calls_share_pagination_state():void {
        $ready=false;$r=new TagRegistry();$r->onRender(static function()use(&$ready):void{$ready=false;});
        $r->register('content',new CallbackTag([],static function()use(&$ready){$ready=true;return [['title'=>'item']];}));
        $r->register('marker',new CallbackTag([],static fn()=>'fragment'));
        $r->register('pagination',new CallbackTag([],static function()use(&$ready){return $ready?'page 1':'';}));
        $this->put('index.blade.php',"@foreach(\$cms->items('content') as \$item){{ \$item['title'] }}@endforeach{{ \$cms->tag('marker') }}{{ \$cms->tag('pagination') }}");
        self::assertSame('itemfragmentpage 1',$this->render('index',[],$r));
    }
    public function test_fragment_can_include_trusted_blade_partial_without_compiling_its_data():void {
        $r=new TagRegistry();$r->register('content',new CallbackTag([],static fn()=>[['title'=>'{{ 7 * 7 }} <x>']]));
        $r->alias('featured','content',[],'{cms:include file="partials/card" /}');
        $this->put('partials/card.blade.php','<p>{{ $item[\'title\'] }}</p>');$this->put('index.blade.php',"{{ \$cms->tag('featured') }}");
        self::assertSame('<p>{{ 7 * 7 }} &lt;x&gt;</p>',$this->render('index',[],$r));
    }
    public function test_sequential_theme_renders_do_not_share_partials_or_variables():void {
        $this->put('index.blade.php',"@include('partials.footer')");$this->put('partials/footer.blade.php','{{ $title }}');
        self::assertSame('A',$this->render('index',['title'=>'A']));self::assertSame('B',$this->render('index',['title'=>'B']));
        mkdir($this->root.'/other');file_put_contents($this->root.'/other/index.blade.php','other {{ $title }}');
        self::assertSame('other C',(new ThemeEngine(new TagRegistry(),$this->root.'/other'))->renderFile('index',['title'=>'C'],new RenderContext('8','en-us')));
        self::assertSame('D',$this->render('index',['title'=>'D']));
    }
    public function test_include_cannot_read_outside_theme_root():void {
        $this->put('index.blade.php',"@include('../outside')");$this->expectExceptionMessage('模板路径无效');$this->render('index');
    }
    public function test_symbolic_link_to_outside_template_is_rejected():void {
        $outside=tempnam(sys_get_temp_dir(),'cms-outside-');file_put_contents($outside,'secret');symlink($outside,$this->root.'/leak.blade.php');
        try{$this->expectExceptionMessage('模板不存在或越界');$this->render('leak');}finally{unlink($outside);}
    }
    public function test_blade_variable_strings_are_never_recompiled():void {
        $this->put('index.blade.php','{{ $title }} @{{ vue }} @verbatim{{ literal }}@endverbatim');
        self::assertSame('@php exit; @endphp {{ 7 * 7 }} {{ vue }} {{ literal }}',$this->render('index',['title'=>'@php exit; @endphp {{ 7 * 7 }}']));
    }
    public function test_provider_not_found_keeps_404_for_public_controller():void {
        $r=new TagRegistry();$r->register('content',new CallbackTag([],static function(){throw new \InvalidArgumentException('页面不存在',404);}));
        $this->put('index.blade.php',"{{ \$cms->value('content') }}");
        $this->expectException(\InvalidArgumentException::class);$this->expectExceptionCode(404);$this->render('index',[],$r);
    }
    public function test_tag_call_budget_is_enforced_for_blade():void {
        $r=new TagRegistry();$r->register('text',new CallbackTag([],static fn()=>'x'));
        $this->put('index.blade.php',"@for(\$i=0;\$i<201;\$i++){{ \$cms->value('text') }}@endfor");
        $this->expectExceptionMessage('整页标签调用超过200次');$this->render('index',[],$r);
    }
}
