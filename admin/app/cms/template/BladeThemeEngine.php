<?php
declare(strict_types=1);
namespace app\cms\template;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;

/** Native Blade for trusted theme files; never compiles database strings. */
final class BladeThemeEngine
{
    public function __construct(private string $root) {}
    public function renderFile(string $template,array $variables,CmsTags $tags):string
    {
        $files=new Filesystem();$cache=runtime_path('views/cms');
        if(!is_dir($cache)&&!mkdir($cache,0755,true)&&!is_dir($cache))throw new \RuntimeException('无法创建 CMS 模板缓存目录');
        $compiler=new BladeCompiler($files,$cache);
        $resolver=new EngineResolver();$resolver->register('blade',static fn()=>new CompilerEngine($compiler,$files));
        $container=new Container();
        // Factory and shared view data are local to this render, not cached on the worker.
        $factory=new Factory($resolver,new ThemeViewFinder($files,$this->root),new Dispatcher($container));
        $factory->setContainer($container);
        try{
            $output=$factory->make($template,array_replace($variables,['cms'=>$tags]))->render();
        }catch(\Illuminate\View\ViewException $error){
            // Blade wraps provider exceptions; preserve the public controller's 400/404 contract.
            for($cause=$error->getPrevious();$cause;$cause=$cause->getPrevious()){
                if($cause instanceof \InvalidArgumentException&&in_array($cause->getCode(),[400,404],true))throw $cause;
            }
            throw $error;
        }
        if(strlen($output)>8_000_000)throw new \RuntimeException('模板输出过大');
        return $output;
    }
}
