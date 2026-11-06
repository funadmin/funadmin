<?php
declare(strict_types=1);
namespace Tests;

use PHPUnit\Framework\TestCase;
use Webman\MiddlewareInterface;

final class MiddlewareNamingTest extends TestCase
{
    public function test_owned_middleware_classes_and_files_have_an_unambiguous_suffix(): void
    {
        $classes=[];
        $files=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__).'/app',\FilesystemIterator::SKIP_DOTS));
        foreach($files as $file){
            if($file->getExtension()!=='php'||basename($file->getPath())!=='middleware')continue;
            $source=file_get_contents($file->getPathname());
            if(!preg_match('/^namespace ([^;]+);/m',$source,$namespace)||!preg_match('/^(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)/m',$source,$match))continue;
            $class=$namespace[1].'\\'.$match[1];
            self::assertTrue(is_subclass_of($class,MiddlewareInterface::class),$class);
            self::assertStringEndsWith('Middleware',$match[1],$file->getPathname());
            self::assertSame($match[1].'.php',$file->getFilename());
            $classes[]=$class;
        }
        self::assertGreaterThanOrEqual(12,count($classes));
    }

    public function test_global_and_application_pipelines_resolve_to_real_middleware_classes(): void
    {
        foreach([dirname(__DIR__).'/config/middleware.php',dirname(__DIR__).'/app/admin/middleware.php',dirname(__DIR__).'/app/api/middleware.php'] as $file){
            foreach(require $file as $group){
                foreach(is_array($group)?$group:[$group] as $class){
                    self::assertTrue(is_subclass_of($class,MiddlewareInterface::class),$class);
                    self::assertStringEndsWith('Middleware',(new \ReflectionClass($class))->getShortName());
                }
            }
        }
    }
}
