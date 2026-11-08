<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\common\plugin\sdk\{Manifest,PluginActivationCompiler,PluginActivationReader};
use app\common\middleware\PluginApplicationGuardMiddleware;
use app\admin\plugin\service\PluginAppPublicationService;
use Webman\{Route,Finder\FileInfo};
final class LifecycleTest extends CmsTestCase
{
    public function test_public_and_admin_annotations_register_under_native_webman_routes(): void
    {
        $root=dirname(__DIR__,2).'/plugins/cms';$files=[];
        foreach(['app/cms/controller/Index.php'=>'app\\cms\\controller\\Index','app/admin/plugins/cms/controller/Content.php'=>'app\\admin\\plugins\\cms\\controller\\Content','app/admin/plugins/cms/controller/Sites.php'=>'app\\admin\\plugins\\cms\\controller\\Sites'] as $relative=>$class)$files[]=new FileInfo($root.'/'.$relative,['class'=>$class],$root);
        $definitions=(new \ReflectionMethod(Route::class,'buildAnnotationRouteDefinitions'))->invoke(null,$files);
        $dispatcher=\FastRoute\simpleDispatcher(static function($collector)use($definitions): void { Route::setCollector($collector);(new \ReflectionMethod(Route::class,'registerAnnotationRouteDefinitions'))->invoke(null,$definitions); });
        foreach([['GET','/cms'],['GET','/cms/'],['GET','/cms/custom/deep.html'],['HEAD','/cms/custom/deep.html'],['POST','/admin/plugins/cms/content/save'],['GET','/admin/plugins/cms/content/lookups']] as [$method,$path])self::assertSame(\FastRoute\Dispatcher::FOUND,$dispatcher->dispatch($method,$path)[0],$method.' '.$path);
    }
    public function test_native_publication_keeps_new_layout_and_disable_blocks_both_entry_types(): void
    {
        $source=dirname(__DIR__,2).'/plugins/cms';$root=sys_get_temp_dir().'/cms-publication-'.bin2hex(random_bytes(6));mkdir($root,0700,true);
        $repository=new class implements \app\admin\plugin\contract\PluginAppPublicationRepository {private array $records=[];public function all(): array{return $this->records;}public function replaceAppForPlugin(string $code,array $records): void{$this->records=$records;}};
        try {
            $publisher=new PluginAppPublicationService($root.'/app',$root.'/runtime/plugins',$repository);
            $publisher->publish(Manifest::fromDirectory($source),'cms-publication');$publisher->complete('cms-publication');
            self::assertFileExists($root.'/app/cms/controller/Index.php');self::assertFileExists($root.'/app/admin/plugins/cms/controller/Content.php');self::assertFileExists($root.'/app/cms/view/default/detail.html');
            $compiler=new PluginActivationCompiler($root.'/activation',dirname($source));$compiler->compile(['cms'=>['code'=>'cms','version'=>'1.0.0','status'=>0,'lifecycle_state'=>'disabled']],[]);
            $guard=new PluginApplicationGuardMiddleware(new PluginActivationReader($root.'/activation'),dirname($source));
            foreach(['/cms/about.html','/admin/plugins/cms/content'] as $path)self::assertSame(404,$guard->process(new \support\Request("GET $path HTTP/1.1\r\nHost: cms.test\r\n\r\n"),static fn()=>response('should not run'))->getStatusCode());
        } finally {
            $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $file)$file->isDir()&&!$file->isLink()?rmdir($file->getPathname()):unlink($file->getPathname());rmdir($root);
        }
    }
}
