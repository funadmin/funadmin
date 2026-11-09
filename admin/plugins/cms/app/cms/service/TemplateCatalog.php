<?php
declare(strict_types=1);
namespace app\cms\service;

/** Only actual full-page templates from the site's active theme are selectable. */
final class TemplateCatalog
{
    /** 站点只能选择实际安装且包含首页模板的主题目录，不暴露服务器路径。 */
    public function themes(): array
    {
        $base=realpath(dirname(__DIR__).'/view');if(!$base)return [];
        $options=[];
        foreach(new \DirectoryIterator($base) as $directory){
            if($directory->isDot()||!$directory->isDir()||$directory->isLink())continue;
            $name=$directory->getFilename();
            if(!preg_match('/^[a-zA-Z0-9_-]+$/D',$name))continue;
            $root=$directory->getRealPath();$homeFile=$root.'/index'.(is_file($root.'/index.blade.php')?'.blade.php':'.html');$home=$root?realpath($homeFile):false;
            if(!$root||!str_starts_with($root,$base.DIRECTORY_SEPARATOR)||!$home||!is_file($home)||is_link($homeFile)||!is_readable($home)||!str_starts_with($home,$root.DIRECTORY_SEPARATOR))continue;
            $definition=BuiltinThemes::get($name);
            $options[]=['value'=>$name,'label'=>$definition?$definition['name'].'（'.$name.'）':$name.'（自定义主题）'];
        }
        usort($options,static fn(array $a,array $b):int=>[$a['value']!=='default',$a['value']]<=>[$b['value']!=='default',$b['value']]);
        return $options;
    }
    public function details(string $siteId): array
    {
        $theme=Input::template((new SiteService())->get($siteId)['theme']);
        $base=realpath(dirname(__DIR__).'/view');
        $root=realpath(dirname(__DIR__).'/view/'.$theme);
        if(!$base||!$root||!str_starts_with($root,$base.DIRECTORY_SEPARATOR))throw new \InvalidArgumentException('主题不存在');
        $labels=['detail'=>'通用详情','article'=>'文章详情','product'=>'产品详情','case'=>'案例详情','page'=>'单页详情','download'=>'下载详情'];
        $options=[];
        $files=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS));
        foreach($files as $file){
            if(!$file->isFile()||$file->isLink())continue;
            $suffix=str_ends_with($file->getFilename(),'.blade.php')?'.blade.php':($file->getExtension()==='html'?'.html':null);if($suffix===null)continue;
            $resolved=$file->getRealPath();if(!$resolved||!str_starts_with($resolved,$root.DIRECTORY_SEPARATOR))continue;
            $name=substr(str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($root)+1)),0,-strlen($suffix));
            if(preg_match('~^(?:partials|includes|layouts)(?:/|$)~',$name)||in_array($name,['index','category','search'],true))continue;
            if(!preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D',$name))continue;
            $options[$name]=['value'=>$name,'label'=>($labels[$name]??$name).' ('.$name.')'];
        }
        $options=array_values($options);
        usort($options,static fn($a,$b)=>strcmp($a['value'],$b['value']));
        return $options;
    }
}
