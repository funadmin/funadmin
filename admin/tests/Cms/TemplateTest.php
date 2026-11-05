<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__ . '/bootstrap.php';
use PHPUnit\Framework\TestCase;
use app\cms\template\{RenderContext,TagRegistry,TemplateEngine,TrustedHtml};
use app\cms\tags\CallbackTag;
final class TemplateTest extends TestCase
{
    private function engine(): TemplateEngine
    {
        self::assertTrue(class_exists(TemplateEngine::class), 'CMS template interpreter must exist');
        $registry = new TagRegistry();
        $registry->register('content', new CallbackTag(['limit' => ['type'=>'integer','default'=>10,'min'=>1,'max'=>100]], static fn(array $p, RenderContext $c) => [['title'=>'<News>','url'=>'/news','site'=>$c->siteId]]));
        $registry->register('greeting', new CallbackTag([], static fn()=>'<Hello>'));
        $registry->alias('featured','content',['limit'=>2],'<b>{$item.title}</b>');
        return new TemplateEngine($registry, __DIR__);
    }
    public function test_variables_collections_conditions_and_safe_html_render_server_side(): void
    {
        $engine=$this->engine();
        $output=$engine->render('<h1>{$title}</h1>{cms:content limit="2" as="row"}<a href="{$row.url}">{$row.title}</a>{/cms:content}{cms:if test="$yes"}YES{cms:else /}NO{/cms:if}{$body}', ['title'=>'<x>','yes'=>true,'body'=>new TrustedHtml('<p>body</p>')], new RenderContext('7','en-us'));
        self::assertSame('<h1>&lt;x&gt;</h1><a href="/news">&lt;News&gt;</a>YES<p>body</p>',$output);
    }
    public function test_php_provider_and_backend_style_alias_fragment(): void
    {
        self::assertSame('&lt;Hello&gt;<b>&lt;News&gt;</b>', $this->engine()->render('{cms:greeting /}{cms:featured /}',[],new RenderContext('7','zh-cn')));
    }
    public function test_scope_override_is_rejected(): void
    {
        $engine=$this->engine(); $this->expectException(\InvalidArgumentException::class);
        $engine->render('{cms:content site_id="99" /}',[],new RenderContext('7','zh-cn'));
    }
    public function test_literal_preserves_vue_and_documentation(): void
    {
        self::assertSame('{{ query }} {$keep} {cms:unknown /}', $this->engine()->render('{{ query }} {cms:literal}{$keep} {cms:unknown /}{/cms:literal}',[],new RenderContext('7','zh-cn')));
    }
    public function test_unknown_attributes_do_not_reach_provider(): void
    {
        $engine=$this->engine(); $this->expectException(\InvalidArgumentException::class);
        $engine->render('{cms:content sql="select secret" /}',[],new RenderContext('7','zh-cn'));
    }
    public function test_include_cannot_escape_theme_root(): void
    {
        $engine=$this->engine(); $this->expectException(\InvalidArgumentException::class);
        $engine->render('{cms:include file="../bootstrap" /}',[],new RenderContext('7','zh-cn'));
    }
    public function test_alias_cycle_and_page_budget_are_bounded(): void
    {
        $this->engine(); $r=new TagRegistry(); $r->register('text',new CallbackTag([],static fn()=>'text'));
        $r->alias('cycle','text',[],'{cms:cycle /}');
        $this->expectException(\RuntimeException::class);
        (new TemplateEngine($r,__DIR__))->render('{cms:cycle /}',[],new RenderContext('1','zh-cn'));
    }
    public function test_large_list_parameter_is_rejected(): void
    {
        $engine=$this->engine(); $this->expectException(\InvalidArgumentException::class);
        $engine->render('{cms:content limit="101" /}',[],new RenderContext('7','zh-cn'));
    }
    public function test_mismatched_closing_tag_is_rejected(): void
    {
        $engine=$this->engine(); $this->expectException(\InvalidArgumentException::class);
        $engine->render('{cms:content}{/cms:category}',[],new RenderContext('7','zh-cn'));
    }
    public function test_output_budget_applies_to_plain_variable_expansion(): void
    {
        $engine=$this->engine();$this->expectException(\RuntimeException::class);
        $engine->render(str_repeat('{$large}',5),['large'=>str_repeat('x',2_000_000)],new RenderContext('7','zh-cn'));
    }

}
