<?php
declare(strict_types=1);
namespace app\cms\template;
use InvalidArgumentException;
use RuntimeException;
/** A deliberately small template interpreter. Database templates never execute PHP. */
final class TemplateEngine
{
    private int $calls=0;
    private array $stack=[];
    public function __construct(private TagRegistry $registry, private string $root) {}
    public function render(string $source,array $variables,RenderContext $context): string
    {
        $this->calls=0; $this->stack=[];
        $this->registry->beginRender();
        return $this->evaluate($this->parse($source),$variables,$context,0);
    }
    public function renderFile(string $template,array $variables,RenderContext $context): string
    {
        return $this->render($this->file($template),$variables,$context);
    }
    private function file(string $template): string
    {
        if(!preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D',$template)) throw new InvalidArgumentException('模板路径无效');
        $root=realpath($this->root); $path=realpath($this->root.'/'.$template.'.html');
        if(!$root || !$path || !str_starts_with($path,$root.DIRECTORY_SEPARATOR) || !is_file($path)) throw new InvalidArgumentException('模板不存在或越界');
        $text=file_get_contents($path); if($text===false || strlen($text)>2_000_000) throw new RuntimeException('模板不可读取或过大');
        return $text;
    }
    private function parse(string $source): array
    {
        if(strlen($source)>2_000_000 || str_contains($source,'<?')) throw new InvalidArgumentException('模板不允许 PHP 或过大');
        $tokens=[];$offset=0;$length=strlen($source);
        while($offset<$length) {
            // Literal contents are opaque; scan their boundaries without regex backtracking.
            $literalStart=strpos($source,'{cms:literal}',$offset);
            $plain=substr($source,$offset,$literalStart===false?null:$literalStart-$offset);
            $parts=preg_split('~(\{/?cms:[^{}]*\}|\{\$[a-zA-Z_]\w*(?:\.[a-zA-Z_0-9]+)*\})~s',$plain,-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
            if($parts===false) throw new RuntimeException('模板分词失败：'.preg_last_error_msg());
            foreach($parts as $part)$tokens[]=$part;
            if($literalStart===false)break;
            $literalEnd=strpos($source,'{/cms:literal}',$literalStart+13);
            if($literalEnd===false)throw new InvalidArgumentException('模板标签未闭合：literal');
            $offset=$literalEnd+14;
            $tokens[]=substr($source,$literalStart,$offset-$literalStart);
        }
        $index=0;
        return $this->nodes($tokens,$index,null,0);
    }
    private function nodes(array $tokens,int &$i,?string $closing,int $depth): array
    {
        if($depth>16) throw new RuntimeException('模板嵌套超过16层');
        $nodes=[];
        while($i<count($tokens)) {
            $token=$tokens[$i++];
            if(str_starts_with($token,'{cms:literal}')) { $nodes[]=['text',substr($token,13,-14)]; continue; }
            if(preg_match('~^\{/cms:([a-z][a-z0-9_-]*)\}$~D',$token,$m)) {
                if($m[1]!==$closing) throw new InvalidArgumentException('模板结束标签不匹配');
                return $nodes;
            }
            if($token==='{cms:else /}') {
                if($closing!=='if') throw new InvalidArgumentException('else 必须位于 if');
                $nodes[]=['else']; continue;
            }
            if(str_starts_with($token,'{$')) { $nodes[]=['variable',substr($token,2,-1)]; continue; }
            if(str_starts_with($token,'{cms:')) {
                if(!preg_match('~^\{cms:([a-z][a-z0-9_-]*)(.*?)\s*(/?)\}$~sD',$token,$m)) throw new InvalidArgumentException('模板标签格式无效');
                $attrs=$this->attributes($m[2]);
                $children=$m[3]==='/'?null:$this->nodes($tokens,$i,$m[1],$depth+1);
                $nodes[]=['tag',$m[1],$attrs,$children]; continue;
            }
            $nodes[]=['text',$token];
        }
        if($closing!==null) throw new InvalidArgumentException('模板标签未闭合：'.$closing);
        return $nodes;
    }
    private function attributes(string $source): array
    {
        $attrs=[]; $offset=0;
        while($offset<strlen($source)) {
            if(trim(substr($source,$offset))==='') break;
            if(!preg_match('~\G\s+([a-zA-Z_][a-zA-Z_0-9]*)\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27)~s',$source,$m,0,$offset)) throw new InvalidArgumentException('标签参数必须为带引号的键值');
            if(array_key_exists($m[1],$attrs)) throw new InvalidArgumentException('重复标签参数');
            $attrs[$m[1]]=$m[2]!==''?$m[2]:($m[3]??''); $offset+=strlen($m[0]);
        }
        return $attrs;
    }
    private function value(string $path,array $variables): mixed
    {
        $result=$variables;
        foreach(explode('.',$path) as $part) { if(!is_array($result) || !array_key_exists($part,$result)) return ''; $result=$result[$part]; }
        return $result;
    }
    private function output(mixed $value): string
    {
        if($value instanceof TrustedHtml) return $value->value;
        if(!is_scalar($value) && $value!==null) throw new InvalidArgumentException('集合或对象需要块标签');
        return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }
    private function append(string &$output,string $chunk): void
    {
        if(strlen($output)+strlen($chunk)>8_000_000)throw new RuntimeException('模板输出过大');
        $output.=$chunk;
    }
    private function evaluate(array $nodes,array $variables,RenderContext $context,int $depth): string
    {
        if($depth>16) throw new RuntimeException('模板嵌套超过16层');
        $output='';
        foreach($nodes as $node) {
            if($node[0]==='text') { $this->append($output,$node[1]); continue; }
            if($node[0]==='variable') { $this->append($output,$this->output($this->value($node[1],$variables))); continue; }
            if($node[0]==='else') throw new InvalidArgumentException('重复或无效 else');
            if(++$this->calls>200) throw new RuntimeException('整页标签调用超过200次');
            [, $name,$attrs,$children]=$node;
            foreach($attrs as &$attribute) if(str_starts_with($attribute,'$')) $attribute=$this->value(substr($attribute,1),$variables);
            unset($attribute);
            if($name==='if') {
                if(array_keys($attrs)!==['test'] || $children===null) throw new InvalidArgumentException('if 需要 test 和块体');
                $parts=[[],[]]; $side=0;
                foreach($children as $child) { if($child[0]==='else') { if($side===1) throw new InvalidArgumentException('重复 else'); $side=1; } else $parts[$side][]=$child; }
                $this->append($output,$this->evaluate($parts[empty($attrs['test'])?1:0],$variables,$context,$depth+1)); continue;
            }
            if($name==='include') {
                if(array_keys($attrs)!==['file'] || $children!==null) throw new InvalidArgumentException('include 参数错误');
                $key='file:'.$attrs['file']; if(in_array($key,$this->stack,true)) throw new RuntimeException('循环 include');
                $this->stack[]=$key;
                try { $this->append($output,$this->evaluate($this->parse($this->file((string)$attrs['file'])),$variables,$context,$depth+1)); } finally { array_pop($this->stack); }
                continue;
            }
            $as=(string)($attrs['as']??'item'); unset($attrs['as']);
            if(!preg_match('/^[a-zA-Z_]\w*$/D',$as) || in_array($as,['site','locale','content','page','category','seo','config','loop'],true)) throw new InvalidArgumentException('循环变量名无效或保留');
            $alias=$this->registry->isAlias($name); $key='tag:'.$name;
            if($alias && in_array($key,$this->stack,true)) throw new RuntimeException('自定义标签循环调用');
            $resolved=$this->registry->resolve($name,$attrs,$context);
            $body=$children??($resolved['fragment']!==''?$this->parse($resolved['fragment']):null);
            if($body===null) { $this->append($output,$this->output($resolved['value'])); continue; }
            $value=$resolved['value'];
            $items=is_array($value)&&array_is_list($value)?$value:[$value];
            if(count($items)>100) throw new RuntimeException('单次标签结果超过100项');
            if($alias) $this->stack[]=$key;
            try { foreach($items as $index=>$item){
                $loop=['index'=>$index,'number'=>$index+1,'first'=>$index===0,'last'=>$index===count($items)-1,'count'=>count($items),'parent'=>$variables['loop']??[]];
                $this->append($output,$this->evaluate($body,array_replace($variables,[$as=>$item,'loop'=>$loop]),$context,$depth+1));
            } }
            finally { if($alias) array_pop($this->stack); }
            if(strlen($output)>8_000_000) throw new RuntimeException('模板输出过大');
        }
        return $output;
    }
}
