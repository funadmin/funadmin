<?php
declare(strict_types=1);
namespace app\cms\template;

/** Request-scoped access to the existing, site- and language-filtered tag providers. */
final class CmsTags
{
    private TemplateEngine $fragments;
    public function __construct(private TagRegistry $registry,string $root,private array $variables,private RenderContext $context)
    {
        $blade=new BladeThemeEngine($root);
        $this->fragments=new TemplateEngine($registry,$root,fn(string $name,array $vars):string=>$blade->renderFile($name,$vars,$this));
    }
    public function value(string $name,array $parameters=[]):mixed
    {
        return $this->registry->resolve($name,$parameters,$this->context)['value'];
    }
    /** Match the legacy block semantics: lists iterate; objects/scalars form one item. */
    public function items(string $name,array $parameters=[]):array
    {
        $value=$this->value($name,$parameters);
        $items=is_array($value)&&array_is_list($value)?$value:[$value];
        if(count($items)>100)throw new \RuntimeException('单次标签结果超过100项');
        return $items;
    }
    public function tag(string $name,array $parameters=[],array $variables=[]):TrustedHtml
    {
        return new TrustedHtml($this->fragments->renderTag($name,$parameters,array_replace($this->variables,$variables),$this->context));
    }
}
