<?php
declare(strict_types=1);
namespace Tests\Oss;
use app\common\storage\{StorageDriverRegistry,StorageDriverInterface,StoredFile};
use app\common\model\Attach;
use app\admin\system\controller\SystemAttachment;
use app\common\service\database\MigrationService;
use support\{Db,Request,Context};
use Webman\Http\UploadFile;
use PHPUnit\Framework\TestCase;
final class DeleteDriver implements StorageDriverInterface
{
    public array $deleted=[];public function __construct(private bool $succeed){}
    public function name():string{return 'oss_1234567890abcdef';}public function label():string{return 'cloud';}public function source():string{return 'oss';}public function available():bool{return true;}
    public function store(UploadFile $file,string $directory,string $rule=''):StoredFile{throw new \LogicException();}
    public function delete(string $key):bool{$this->deleted[]=$key;return $this->succeed;}
}
final class DeletionTest extends TestCase
{
    protected function setUp():void{parent::setUp();(new MigrationService())->run();Db::beginTransaction();}
    protected function tearDown():void{Db::rollBack();Context::destroy();parent::tearDown();}
    private function attachment(string $key='cloud/test.txt'):Attach{return Attach::forceCreate(['group_id'=>1,'name'=>'test.txt','original_name'=>'test.txt','path'=>'https://cdn.example.com/test.txt','url'=>'https://cdn.example.com/test.txt','storage_key'=>$key,'driver'=>'oss_1234567890abcdef','ext'=>'txt','size'=>1,'status'=>1]);}
    private function call(array $ids,?DeleteDriver $driver):array{
        $body=json_encode(['ids'=>$ids]);$request=new Request("DELETE /admin/system/attachment HTTP/1.1\r\nHost: admin.test\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);Context::set(\Webman\Http\Request::class,$request);
        $registry=new StorageDriverRegistry(null,static fn($point,$value,$context=[])=>$value);if($driver)$registry->register($driver);
        return json_decode((new SystemAttachment($registry))->delete()->rawBody(),true);
    }
    public function test_unavailable_cloud_does_not_delete_the_attachment_record():void{$row=$this->attachment();$response=$this->call([$row->id],null);self::assertSame(422,$response['code']);self::assertNotNull(Attach::find($row->id));}
    public function test_cloud_delete_failure_keeps_the_attachment_record():void{$row=$this->attachment();$response=$this->call([$row->id],new DeleteDriver(false));self::assertSame(422,$response['code']);self::assertNotNull(Attach::find($row->id));}
    public function test_missing_cloud_object_key_cannot_remove_its_record():void{$row=$this->attachment('');$response=$this->call([$row->id],new DeleteDriver(true));self::assertSame(422,$response['code']);self::assertNotNull(Attach::find($row->id));}
    public function test_shared_cloud_object_is_deleted_once_for_the_selected_group():void{$a=$this->attachment();$b=$this->attachment();$driver=new DeleteDriver(true);$response=$this->call([$a->id,$b->id],$driver);self::assertSame(200,$response['code']);self::assertSame(['cloud/test.txt'],$driver->deleted);self::assertNull(Attach::find($a->id));self::assertNull(Attach::find($b->id));}
}
