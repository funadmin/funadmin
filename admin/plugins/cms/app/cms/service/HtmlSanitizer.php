<?php
declare(strict_types=1);
namespace app\cms\service;
final class HtmlSanitizer
{
    public static function clean(string $html): string
    {
        if(strlen($html)>2_000_000) throw new \InvalidArgumentException('正文过大');
        $document=new \DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
        try { $document->loadHTML('<?xml encoding="UTF-8"><div id="cms-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NONET); }
        finally { libxml_clear_errors();libxml_use_internal_errors($previous); }
        $allowed=['p','div','span','br','hr','h1','h2','h3','h4','h5','h6','strong','b','em','i','u','s','ul','ol','li','blockquote','pre','code','table','thead','tbody','tr','th','td','a','img','figure','figcaption','video','source'];
        $walk=function(\DOMNode $node)use(&$walk,$allowed): void {
            foreach(iterator_to_array($node->childNodes) as $child) {
                if($child instanceof \DOMElement) {
                    $tag=strtolower($child->tagName);
                    if(!in_array($tag,$allowed,true)) { $node->removeChild($child);continue; }
                    foreach(iterator_to_array($child->attributes) as $attr) {
                        $name=strtolower($attr->name);$value=$attr->value;
                        $permitted=in_array($name,['title','alt','width','height','colspan','rowspan','controls'],true)||$name==='class'&&preg_match('/^[a-zA-Z0-9 _-]{0,200}$/D',$value);
                        if(($tag==='a'&&$name==='href')||(in_array($tag,['img','video','source'],true)&&$name==='src')) $permitted=self::safeUrl($value,$tag==='a');
                        if(!$permitted)$child->removeAttribute($attr->name);
                    }
                    $walk($child);
                }elseif(!$child instanceof \DOMText) $node->removeChild($child);
            }
        };
        $root=$document->getElementById('cms-root');if(!$root)return '';$walk($root);$output='';foreach($root->childNodes as $node)$output.=$document->saveHTML($node);return $output;
    }
    public static function safeUrl(string $url,bool $link=true): bool
    {
        if(preg_match('/[\x00-\x20\\\\]/',$url))return false;
        return str_starts_with($url,'/')&&!str_starts_with($url,'//')||preg_match($link?'~^(?:https?://|mailto:|#)~i':'~^https?://~i',$url)===1;
    }
}
