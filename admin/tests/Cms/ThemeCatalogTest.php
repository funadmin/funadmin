<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\cms\service\TemplateCatalog;
final class ThemeCatalogTest extends \PHPUnit\Framework\TestCase
{
    public function test_theme_options_only_include_real_theme_directories_with_a_home_template(): void
    {
        $base=dirname(__DIR__,2).'/plugins/cms/app/cms/view';
        $name='theme_fixture_'.bin2hex(random_bytes(5));
        $valid=$base.'/'.$name;$empty=$base.'/'.$name.'_empty';$linked=$base.'/'.$name.'_link';$linkedIndex=$base.'/'.$name.'_index';
        $outside=sys_get_temp_dir().'/'.$name;
        foreach([$valid,$empty,$linkedIndex,$outside] as $path)mkdir($path);
        file_put_contents($valid.'/index.html','<h1>Theme home</h1>');
        file_put_contents($outside.'/index.html','<h1>Outside theme</h1>');
        symlink($outside,$linked);symlink($outside.'/index.html',$linkedIndex.'/index.html');
        try{
            $options=(new TemplateCatalog())->themes();$values=array_column($options,'value');
            self::assertContains('default',$values);self::assertContains($name,$values);
            self::assertNotContains($name.'_empty',$values);self::assertNotContains($name.'_link',$values);self::assertNotContains($name.'_index',$values);
            foreach($options as $option){self::assertArrayNotHasKey('path',$option);self::assertNotEmpty($option['label']);}
        }finally{
            unlink($linked);unlink($linkedIndex.'/index.html');unlink($valid.'/index.html');unlink($outside.'/index.html');
            foreach([$valid,$empty,$linkedIndex,$outside] as $path)rmdir($path);
        }
    }
}
