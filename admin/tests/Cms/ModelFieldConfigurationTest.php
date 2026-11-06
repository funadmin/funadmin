<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\ModelService;
use support\Db;
final class ModelFieldConfigurationTest extends CmsTestCase
{
    public function test_constraints_and_defaults_are_versioned_and_enforced(): void
    {
        $service=new ModelService();
        $model=$service->save(['code'=>'configured','name'=>'配置模型','fields'=>[
            ['name'=>'count','label'=>'数量','type'=>'number','min'=>0,'max'=>10,'default'=>0],
            ['name'=>'enabled','type'=>'boolean','required'=>true,'default'=>false],
            ['name'=>'source','type'=>'text','max_length'=>3,'description'=>'填写来源','placeholder'=>'请输入','default'=>'本站'],
        ]]);
        $version=(string)Db::table('cms_model_version')->where('model_id',$model['id'])->value('id');
        self::assertSame(['count'=>0.0,'enabled'=>false,'source'=>'本站'],$service->validate($version,[]));
        foreach([['count'=>11],['count'=>-1],['source'=>'四个中文字']] as $input){try{$service->validate($version,$input);self::fail('Invalid model value must be rejected');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
    public function test_invalid_field_constraints_and_defaults_are_rejected_at_schema_save(): void
    {
        foreach([
            ['type'=>'number','min'=>10,'max'=>1],['type'=>'number','default'=>'bad'],
            ['type'=>'number','min'=>2,'default'=>1],['type'=>'text','max_length'=>0],
            ['type'=>'text','max_length'=>2,'default'=>'超过长度'],['type'=>'boolean','default'=>'false'],
            ['type'=>'select','options'=>['A'],'default'=>'B'],['type'=>'select','options'=>[['bad']]],
            ['type'=>'boolean','default'=>''],['type'=>'number','default'=>''],
        ] as $field){try{(new ModelService())->save(['code'=>'invalid','fields'=>[['name'=>'value']+$field]]);self::fail('Invalid field definition must be rejected');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
    public function test_new_constraints_do_not_rewrite_existing_model_versions(): void
    {
        $service=new ModelService();$model=$service->save(['code'=>'history','fields'=>[['name'=>'text','type'=>'text']]]);
        $old=(string)Db::table('cms_model_version')->where('model_id',$model['id'])->value('id');
        $service->save(['id'=>$model['id'],'fields'=>[['name'=>'text','type'=>'text','max_length'=>2]]]);
        self::assertSame('历史完整文本',$service->validate($old,['text'=>'历史完整文本'])['text']);
    }
    public function test_explicit_null_clears_an_optional_default_and_does_not_bypass_required(): void
    {
        $service=new ModelService();$model=$service->save(['code'=>'nullable','fields'=>[['name'=>'source','type'=>'text','default'=>'本站'],['name'=>'flag','type'=>'boolean','required'=>true,'default'=>false]]]);
        $version=(string)Db::table('cms_model_version')->where('model_id',$model['id'])->value('id');
        self::assertNull($service->validate($version,['source'=>null])['source']);
        try{$service->validate($version,['flag'=>null]);self::fail('Required explicit null must be rejected');}catch(\InvalidArgumentException){self::assertTrue(true);}
    }
}
