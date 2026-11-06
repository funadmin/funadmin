<?php
declare(strict_types=1);
namespace Tests\Oss;
require_once __DIR__.'/bootstrap.php';
use app\admin\plugins\oss\service\{Schema,ProfileService};
use app\common\storage\{StorageDriverInterface,StorageDriverRegistry,StoredFile};
use app\common\service\storage\UploadService;
use app\common\service\database\MigrationService;
use app\common\plugin\hook\HookRuntime;
use app\common\plugin\sdk\{PluginActivationCompiler,PluginActivationReader};
use app\common\model\Attach;
use support\{Db,Context,Request,Container};
use Webman\Http\UploadFile;
use Defuse\Crypto\Key;
use PHPUnit\Framework\TestCase;
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class TakeoverFlowTest extends TestCase
{
    private string $root;private array $sources=[];private ProfileService $profiles;
    protected function setUp():void{
        parent::setUp();(new MigrationService())->run();Schema::install();Db::beginTransaction();
        $this->root=sys_get_temp_dir().'/oss-flow-'.bin2hex(random_bytes(8));mkdir($this->root.'/plugins/oss/hooks',0700,true);copy(dirname(__DIR__,2).'/plugins/oss/hooks/storage.php',$this->root.'/plugins/oss/hooks/storage.php');
        $this->profiles=new ProfileService(Key::createNewRandomKey()->saveToAsciiSafeString(),static fn(array $profile)=>new FlowDriver($profile));
        Container::instance()->addDefinitions([ProfileService::class=>fn()=>$this->profiles,HookRuntime::class=>fn()=>new HookRuntime(new PluginActivationReader($this->root.'/activation'),$this->root.'/plugins',static function():void{}),StorageDriverRegistry::class=>static fn()=>new StorageDriverRegistry()]);
        $this->compile(true);Context::set(\Webman\Http\Request::class,new Request("POST /admin/upload HTTP/1.1\r\nHost: admin.test\r\n\r\n"));
        $GLOBALS['oss_flow_stores']=[];$GLOBALS['oss_flow_failure']=false;$GLOBALS['oss_flow_switch']=null;
    }
    protected function tearDown():void{
        foreach($this->sources as $file)if(is_file($file))unlink($file);Db::rollBack();Context::destroy();
        $files=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($files as $file)$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());rmdir($this->root);
        unset($GLOBALS['oss_flow_stores'],$GLOBALS['oss_flow_failure'],$GLOBALS['oss_flow_switch']);parent::tearDown();
    }
    private function compile(bool $enabled):void{
        $hooks=json_decode(file_get_contents(dirname(__DIR__,2).'/plugins/oss/plugin.json'),true)['serverHooks'];
        (new PluginActivationCompiler($this->root.'/activation',$this->root.'/plugins'))->compile(['oss'=>['code'=>'oss','version'=>'1.0.0','lifecycle_state'=>$enabled?'enabled':'disabled','status'=>$enabled?1:0]],['oss'=>['serverHooks'=>$hooks]]);
    }
    private function profile(string $name):array{return $this->profiles->save(['name'=>$name,'provider'=>'aliyun','bucket'=>$name.'-bucket','endpoint'=>'https://oss-cn-hangzhou.aliyuncs.com','domain'=>'https://'.$name.'.example.com','region'=>'cn-hangzhou','prefix'=>'files','access_key'=>'test-access','secret_key'=>'test-secret']);}
    private function file(string $bytes='upload contents'):UploadFile{$path=tempnam(sys_get_temp_dir(),'oss-flow-file-');$this->sources[]=$path;file_put_contents($path,$bytes);return new UploadFile($path,'test.txt','text/plain',UPLOAD_ERR_OK);}
    public function test_real_storage_hooks_take_over_and_switch_only_the_next_upload():void{
        $a=$this->profile('first');$b=$this->profile('second');$this->profiles->activate($a['id']);
        $GLOBALS['oss_flow_switch']=fn()=>$this->profiles->activate($b['id']);
        $service=new UploadService();$first=$service->attach($this->file(),0,1);self::assertSame('oss_'.$a['id'],$first->driver);self::assertSame('https://first.example.com/files/test.txt',$first->path);
        $next=$service->attach($this->file(),0,1);self::assertSame('oss_'.$b['id'],$next->driver);self::assertCount(2,$GLOBALS['oss_flow_stores']);
        $same=$service->attach($this->file(),0,1);self::assertSame($next->id,$same->id);self::assertCount(2,$GLOBALS['oss_flow_stores']);
        $this->compile(false);self::assertSame('local',Container::get(StorageDriverRegistry::class)->forUpload('local')->name());
        $this->compile(true);self::assertSame('oss_'.$a['id'],Container::get(StorageDriverRegistry::class)->require('oss_'.$a['id'])->name());
    }
    public function test_cloud_upload_applies_the_existing_image_watermark_before_remote_storage():void{
        $profile=$this->profile('watermark');$this->profiles->activate($profile['id']);
        $path=tempnam(sys_get_temp_dir(),'oss-water-');$this->sources[]=$path;$image=imagecreatetruecolor(12,12);imagefill($image,0,0,imagecolorallocate($image,255,255,255));imagepng($image,$path);
        $markPath=public_path('oss-test-mark-'.bin2hex(random_bytes(5)).'.png');$mark=imagecreatetruecolor(2,2);imagefill($mark,0,0,imagecolorallocate($mark,255,0,0));imagepng($mark,$markPath);
        $cacheKey='syscfg_'.hash('sha256',"upload\0");\support\Cache::set($cacheKey,['cached'=>true,'value'=>['upload_water'=>1,'upload_water_thumb'=>'/'.basename($markPath),'upload_water_position'=>9,'upload_water_alpha'=>100]]);
        try{(new UploadService())->attach(new UploadFile($path,'image.png','image/png',UPLOAD_ERR_OK),0,1);self::assertSame(0xff0000,$GLOBALS['oss_flow_image_pixel']);}
        finally{\support\Cache::delete($cacheKey);unlink($markPath);unset($GLOBALS['oss_flow_image_pixel']);}
    }
    public function test_a_cloud_failure_never_inserts_a_successful_local_attachment():void{
        $a=$this->profile('first');$this->profiles->activate($a['id']);$GLOBALS['oss_flow_failure']=true;$count=Attach::count();
        try{(new UploadService())->attach($this->file(),0,1);self::fail('Cloud failure must be returned');}catch(\RuntimeException $error){self::assertSame('Cloud fixture failed',$error->getMessage());self::assertSame($count,Attach::count());}
    }
}
final class FlowDriver implements StorageDriverInterface
{
    public function __construct(private readonly array $profile){}
    public function name():string{return 'oss_'.$this->profile['id'];}public function label():string{return $this->profile['name'];}public function source():string{return 'oss';}public function available():bool{return true;}
    public function store(UploadFile $file,string $directory,string $rule=''):StoredFile{if($GLOBALS['oss_flow_failure'])throw new \RuntimeException('Cloud fixture failed');$GLOBALS['oss_flow_stores'][]=$this->name();if(@getimagesize($file->getPathname())){$image=imagecreatefrompng($file->getPathname());$GLOBALS['oss_flow_image_pixel']=imagecolorat($image,11,11);}if($switch=$GLOBALS['oss_flow_switch']){$GLOBALS['oss_flow_switch']=null;$switch();}return new StoredFile('files/test.txt',$this->profile['domain'].'/files/test.txt');}
    public function delete(string $key):bool{return true;}
}
