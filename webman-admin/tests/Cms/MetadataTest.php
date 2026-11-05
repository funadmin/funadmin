<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\common\plugin\sdk\{PluginScaffolder,Manifest};
use app\admin\plugin\service\PluginAppPublicationService;
final class MetadataTest extends \PHPUnit\Framework\TestCase
{
    public function test_finder_metadata_does_not_break_manifest_or_native_publication_ownership(): void
    {
        $root=sys_get_temp_dir().'/cms-metadata-'.bin2hex(random_bytes(6));(new PluginScaffolder($root.'/plugins'))->scaffold('metadatafixture');$source=$root.'/plugins/metadatafixture';
        $repository=new class implements \app\admin\plugin\contract\PluginAppPublicationRepository, \app\admin\plugin\contract\PluginResourceRepository{private array $data=[];public function all():array{return $this->data;}public function replaceAppForPlugin(string $code,array $records):void{$this->data=$records;}public function replaceForPlugin(string $code,array $records):void{$this->data=$records;}};
        try{
            file_put_contents($source.'/app/.DS_Store','finder');file_put_contents($source.'/app/admin/.DS_Store','finder');file_put_contents($source.'/app/metadatafixture/.DS_Store','finder');
            $manifest=Manifest::fromDirectory($source);self::assertSame('metadatafixture',$manifest->code());
            $publisher=new PluginAppPublicationService($root.'/app',$root.'/runtime',$repository);$publisher->publish($manifest,'initial');$publisher->complete('initial');
            self::assertFileDoesNotExist($root.'/app/metadatafixture/.DS_Store');
            file_put_contents($root.'/app/metadatafixture/.DS_Store','finder-target');
            self::assertFalse((new \app\admin\plugin\service\PluginModificationDetector($root.'/app',$root.'/public',$root.'/admin-web',$repository))->isModified('metadatafixture'));
            $publisher->publish($manifest,'repeat');$publisher->complete('repeat');
            self::assertFileExists($root.'/app/metadatafixture/controller/Index.php');
        }finally{$iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $file)$file->isDir()&&!$file->isLink()?rmdir($file->getPathname()):unlink($file->getPathname());rmdir($root);}
    }
}
