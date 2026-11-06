<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\cms\tags\{BuiltinTags,CallbackTag};
use app\cms\template\{RenderContext,TemplateEngine,TrustedHtml};
use PHPUnit\Framework\TestCase;

final class PhaseTwoTemplateTest extends TestCase
{
    private function render(string $source,array $variables=[]): string
    {
        $context=new RenderContext('1','zh-cn',['config'=>['items'=>[['name'=>'A','enabled'=>true],['name'=>'B','enabled'=>true]]]]);
        return (new TemplateEngine((new BuiltinTags())->registry($context),__DIR__))->render($source,$variables,$context);
    }
    public function test_text_formats_summaries_and_preserves_final_escaping_and_falsy_values(): void
    {
        self::assertSame('中文…',$this->render('{cms:text value="$summary" strip_html="true" length="2" /}',['summary'=>new TrustedHtml('<b>中文摘要</b>')]));
        self::assertSame('&lt;fallback&gt;',$this->render('{cms:text value="$missing" default="<fallback>" /}'));
        self::assertSame('0',$this->render('{cms:text value="$zero" default="fallback" /}',['zero'=>0]));
        self::assertSame('',$this->render('{cms:text value="$flag" default="fallback" /}',['flag'=>false]));
        self::assertSame('&lt;b&gt;safe&lt;/b&gt;',$this->render('{cms:text value="$body" /}',['body'=>new TrustedHtml('<b>safe</b>')]));
        self::assertSame('short',$this->render('{cms:text value="short" length="10" /}'));
    }
    public function test_dates_accept_sql_iso_and_epoch_but_not_relative_or_invalid_dates(): void
    {
        self::assertSame('2026/11/06',$this->render('{cms:date value="2026-11-06 09:08:07" format="Y/m/d" /}'));
        self::assertSame('2026-11-06',$this->render('{cms:date value="2026-11-06T09:08:07+08:00" /}'));
        self::assertSame('1970',$this->render('{cms:date value="0" format="Y" /}'));
        foreach(['tomorrow','2026-02-30','bad'] as $value)self::assertSame('未发布',$this->render('{cms:date value="'.$value.'" default="未发布" /}'));
    }
    public function test_list_loops_expose_position_and_parent_without_overwriting_outer_loop(): void
    {
        $html=$this->render('{cms:config name="items" as="row"}{$loop.number}/{$loop.count}:{$row.name}{cms:config name="items" as="child"}{$loop.parent.number}.{$loop.number}{/cms:config}[{$loop.number}]{/cms:config}');
        self::assertSame('1/2:A1.11.2[1]2/2:B2.12.2[2]',$html);
        $this->expectException(\InvalidArgumentException::class);
        $this->render('{cms:config name="items" as="loop"}{$loop.name}{/cms:config}');
    }
    public function test_formatting_limits_and_unknown_function_parameters_are_rejected(): void
    {
        foreach(['{cms:text value="x" length="10001" /}','{cms:text value="x" function="system" /}','{cms:date value="0" format="'.str_repeat('Y',81).'" /}'] as $source){
            try{$this->render($source);self::fail('Unsafe formatting accepted');}catch(\InvalidArgumentException){self::assertTrue(true);}
        }
    }
}
