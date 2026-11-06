<?php
declare(strict_types=1);
namespace Tests\Oss;
use app\common\storage\{StorageDriverRegistry,StorageDriverInterface,StoredFile};
use PHPUnit\Framework\TestCase;
use Webman\Http\UploadFile;
final class FakeCloudDriver implements StorageDriverInterface
{
    public function __construct(private string $id='oss_1234567890abcdef'){}
    public function name():string{return $this->id;}public function label():string{return 'Test cloud';}public function source():string{return 'oss';}public function available():bool{return true;}
    public function store(UploadFile $file,string $directory,string $rule=''):StoredFile{return new StoredFile('cloud/test.txt','https://cdn.example.com/cloud/test.txt');}
    public function delete(string $storageKey):bool{return true;}
}
final class StorageTakeoverTest extends TestCase
{
    public function test_current_upload_contribution_is_resolved_again_for_the_next_upload():void {
        $active=new FakeCloudDriver();$filter=static function($point,$value,$context=[])use(&$active){return $point==='storage.upload.resolve'?$active:$value;};
        $registry=new StorageDriverRegistry(null,$filter);self::assertTrue(method_exists($registry,'forUpload'));
        self::assertSame('oss_1234567890abcdef',$registry->forUpload('local')->name());$active=new FakeCloudDriver('oss_fedcba0987654321');self::assertSame('oss_fedcba0987654321',$registry->forUpload('local')->name());
    }
    public function test_missing_historical_driver_never_falls_back_to_local():void {
        $registry=new StorageDriverRegistry(null,static fn($point,$value,$context=[])=>$value);self::assertTrue(method_exists($registry,'require'));
        $this->expectException(\RuntimeException::class);$registry->require('oss_1234567890abcdef');
    }
    public function test_historical_driver_resolution_is_distinct_from_the_current_upload():void {
        $old=new FakeCloudDriver();$current=new FakeCloudDriver('oss_fedcba0987654321');
        $registry=new StorageDriverRegistry(null,static fn($point,$value,$context=[])=>match($point){'storage.upload.resolve'=>$current,'storage.driver.resolve'=>$context['name']===$old->name()?$old:$value,default=>$value});
        self::assertTrue(method_exists($registry,'require'));self::assertSame($old,$registry->require($old->name()));self::assertSame($current,$registry->forUpload('local'));
    }
}
