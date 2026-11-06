<?php
declare(strict_types=1);
namespace Tests\Oss;
require_once __DIR__.'/bootstrap.php';
use app\admin\plugins\oss\service\{Schema,ProfileService};
use Defuse\Crypto\Key;
use support\Db;
use PHPUnit\Framework\TestCase;
final class ProfileTest extends TestCase
{
    protected function setUp():void {parent::setUp();self::assertTrue(class_exists(Schema::class));Db::schema()->dropIfExists('oss_setting');Db::schema()->dropIfExists('oss_profile');Schema::install();}
    private function input():array{return ['name'=>'Alibaba','provider'=>'aliyun','bucket'=>'sample-bucket','endpoint'=>'https://oss-cn-hangzhou.aliyuncs.com','domain'=>'https://cdn.example.com','region'=>'cn-hangzhou','prefix'=>'assets','access_key'=>'test-access','secret_key'=>'very-private-key'];}
    public function test_secrets_are_encrypted_and_public_results_do_not_leak_them():void {
        $service=new ProfileService(Key::createNewRandomKey()->saveToAsciiSafeString());$saved=$service->save($this->input());
        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/',$saved['id']);self::assertTrue($saved['has_credentials']);
        self::assertStringNotContainsString('very-private-key',json_encode($saved));self::assertStringNotContainsString('test-access',json_encode($saved));
        $row=(array)Db::table('oss_profile')->first();self::assertStringNotContainsString('very-private-key',json_encode($row));self::assertStringNotContainsString('test-access',json_encode($row));
    }
    public function test_rename_and_empty_credentials_preserve_the_encrypted_secret():void {
        $service=new ProfileService(Key::createNewRandomKey()->saveToAsciiSafeString());$saved=$service->save($this->input());$cipher=Db::table('oss_profile')->value('credentials');
        $changed=$service->save(['id'=>$saved['id'],'name'=>'renamed','access_key'=>'','secret_key'=>'']);self::assertSame('renamed',$changed['name']);self::assertSame($cipher,Db::table('oss_profile')->value('credentials'));
    }
    public function test_a_historical_profile_cannot_be_repointed_to_a_different_bucket():void {
        $service=new ProfileService(Key::createNewRandomKey()->saveToAsciiSafeString());$saved=$service->save($this->input());$this->expectException(\InvalidArgumentException::class);$service->save(['id'=>$saved['id'],'bucket'=>'other-bucket']);
    }
    public function test_missing_encryption_key_rejects_credentials_without_persisting():void {
        $service=new ProfileService('');try{$service->save($this->input());self::fail('Expected missing key refusal');}catch(\RuntimeException){self::assertSame(0,Db::table('oss_profile')->count());}
    }
    public function test_no_selected_profile_does_not_claim_a_takeover():void {
        $service=new ProfileService('');self::assertNull($service->current());self::assertSame([], $service->drivers());self::assertSame([], $service->listing());
    }
    public function test_both_replacement_credentials_recover_a_profile_with_an_unreadable_old_cipher():void {
        $original=new ProfileService(Key::createNewRandomKey()->saveToAsciiSafeString());$saved=$original->save($this->input());
        $replacement=new ProfileService(Key::createNewRandomKey()->saveToAsciiSafeString());
        $repaired=$replacement->save(['id'=>$saved['id'],'access_key'=>'replacement-access','secret_key'=>'replacement-secret']);
        self::assertSame($saved['id'],$repaired['id']);self::assertSame('oss_'.$saved['id'],$replacement->driver('oss_'.$saved['id'])->name());
    }

}
