<?php
declare(strict_types=1);
namespace app\cms\template;

/** Prefer Blade for filesystem themes while preserving existing HTML themes. */
final class ThemeEngine
{
    public function __construct(private TagRegistry $registry,private string $root) {}
    public function renderFile(string $template,array $variables,RenderContext $context):string
    {
        if(!preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D',$template))throw new \InvalidArgumentException('模板路径无效');
        $tags=new CmsTags($this->registry,$this->root,$variables,$context);
        if(is_file($this->root.'/'.$template.'.blade.php')){
            $this->registry->beginRender();
            return(new BladeThemeEngine($this->root))->renderFile($template,$variables,$tags);
        }
        $blade=new BladeThemeEngine($this->root);
        return(new TemplateEngine($this->registry,$this->root,fn(string $name,array $vars):string=>$blade->renderFile($name,$vars,$tags)))->renderFile($template,$variables,$context);
    }
}
