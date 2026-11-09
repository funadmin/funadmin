<?php
declare(strict_types=1);
namespace app\cms\service;

use app\cms\template\{RenderContext,TagRegistry,ThemeEngine,TrustedHtml};

/** Render menu nodes from a theme partial; only escaped data and rendered children enter the view. */
final class NavigationView
{
    public function render(string $theme,string $template,array $items,RenderContext $context): TrustedHtml
    {
        $base=realpath(dirname(__DIR__).'/view');
        $root=realpath(dirname(__DIR__).'/view/'.Input::template($theme));
        if(!$base||!$root||!str_starts_with($root,$base.DIRECTORY_SEPARATOR))throw new \InvalidArgumentException('导航主题不存在');
        $template=Input::template($template);
        // A separate theme engine leaves the page's content pagination state and call budget intact.
        $engine=new ThemeEngine(new TagRegistry(),$root);
        $prefix=str_starts_with($context->locale,'zh')?'查看全部':'View all ';
        $nodes=function(array $rows,int $depth=0)use(&$nodes,$engine,$template,$context,$prefix):string {
            if($depth>16)throw new \InvalidArgumentException('导航嵌套过深');
            $html='';
            foreach($rows as $row){
                $children=$row['children']??[];
                $nav=array_replace($row,['has_children'=>(bool)$children,'overview_title'=>$prefix.$row['title'],'children'=>new TrustedHtml($children?$nodes($children,$depth+1):'')]);
                $html.=$engine->renderFile($template,['nav'=>$nav],$context);
            }
            return $html;
        };
        return new TrustedHtml($nodes($items));
    }
}
