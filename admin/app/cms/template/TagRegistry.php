<?php
declare(strict_types=1);
namespace app\cms\template;
use InvalidArgumentException;
final class TagRegistry
{
    private int $calls=0;
    private array $handlers=[];
    private array $aliases=[];
    private array $renderInitializers=[];
    public function onRender(callable $initializer): void { $this->renderInitializers[]=\Closure::fromCallable($initializer); }
    public function beginRender(): void { $this->calls=0; foreach($this->renderInitializers as $initialize)$initialize(); }
    public function register(string $name, TagHandler $handler): void
    {
        $this->assertName($name);
        if (isset($this->handlers[$name]) || isset($this->aliases[$name]) || in_array($name,['if','else','include','literal'],true)) throw new InvalidArgumentException('标签名重复或保留：'.$name);
        $this->handlers[$name]=$handler;
    }
    public function alias(string $name,string $provider,array $parameters,string $fragment=''): void
    {
        $this->assertName($name);
        if (isset($this->handlers[$name]) || isset($this->aliases[$name]) || in_array($name,['if','else','include','literal'],true)) throw new InvalidArgumentException('标签名重复或保留：'.$name);
        if (!isset($this->handlers[$provider]) || !$this->handlers[$provider]->configurable()) throw new InvalidArgumentException('标签处理器不可配置');
        $this->parameters($this->handlers[$provider]->schema(),$parameters);
        $this->aliases[$name]=['provider'=>$provider,'parameters'=>$parameters,'fragment'=>$fragment];
    }
    public function definitions(bool $configurableOnly=true): array
    {
        $result=[];
        foreach($this->handlers as $name=>$handler) if(!$configurableOnly || $handler->configurable()) $result[]=['name'=>$name,'parameters'=>$handler->schema()];
        return $result;
    }
    public function isAlias(string $name): bool { return isset($this->aliases[$name]); }
    public function resolve(string $name,array $parameters,RenderContext $context): array
    {
        if(++$this->calls>200)throw new \RuntimeException('整页标签调用超过200次');
        $alias=$this->aliases[$name]??null;
        $handler=$this->handlers[$alias['provider']??$name]??null;
        if (!$handler) throw new InvalidArgumentException('未知标签：'.$name);
        $parameters=$this->parameters($handler->schema(),array_replace($alias['parameters']??[],$parameters));
        return ['value'=>$handler->resolve($parameters,$context),'fragment'=>$alias['fragment']??''];
    }
    public function parameters(array $schema,array $input): array
    {
        foreach($input as $key=>$value) if(!isset($schema[$key]) || in_array($key,['site_id','siteId','locale','published_only'],true)) throw new InvalidArgumentException('不允许的标签参数：'.$key);
        $result=[];
        foreach($schema as $key=>$rule) {
            $value=$input[$key]??$rule['default']??null;
            if($value===null) { if($rule['required']??false) throw new InvalidArgumentException('缺少参数：'.$key); continue; }
            $type=$rule['type']??'string';
            if($type==='integer') { if(filter_var($value,FILTER_VALIDATE_INT)===false) throw new InvalidArgumentException('参数必须为整数：'.$key); $value=(int)$value; }
            elseif($type==='boolean') { $v=filter_var($value,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE); if($v===null) throw new InvalidArgumentException('参数必须为布尔值：'.$key); $value=$v; }
            elseif($type==='string'||$type==='text') {
                if($type==='text'&&$value instanceof TrustedHtml)$value=$value->value;
                if(!is_scalar($value))throw new InvalidArgumentException('参数必须为字符串：'.$key);
                if(strlen((string)$value)>($type==='text'?200000:2000))throw new InvalidArgumentException('参数过长');
                if($type==='string')$value=(string)$value;
            }
            else throw new InvalidArgumentException('不支持的参数类型');
            if(isset($rule['min']) && $value<$rule['min'] || isset($rule['max']) && $value>$rule['max'] || isset($rule['enum']) && !in_array($value,$rule['enum'],true)) throw new InvalidArgumentException('参数超出范围：'.$key);
            $result[$key]=$value;
        }
        return $result;
    }
    private function assertName(string $name): void
    {
        if(!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$name)) throw new InvalidArgumentException('标签名无效');
    }
}
