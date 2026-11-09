<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\FormSchema;
use support\Db;
final class FormSchemaTest extends CmsTestCase
{
    public function test_install_is_idempotent_and_creates_all_tables(): void
    {
        self::assertSame(['cms_form','cms_form_submission','cms_form_file','cms_form_event'],FormSchema::tables());
        foreach(array_reverse(FormSchema::tables()) as $table)Db::schema()->dropIfExists($table);
        FormSchema::install();
        FormSchema::install();
        foreach(FormSchema::tables() as $table)self::assertTrue(Db::schema()->hasTable($table),$table.' must exist');
        self::assertTrue(Db::schema()->hasColumns('cms_form',['site_id','code','name','status','fields','texts','settings','lock_version','created_at','updated_at']));
        self::assertTrue(Db::schema()->hasColumns('cms_form_submission',['site_id','form_id','locale','fields','values','search_text','status','source_url','ip','user_agent','member_id','lock_version','handled_at','created_at','updated_at']));
        self::assertTrue(Db::schema()->hasColumns('cms_form_file',['site_id','submission_id','field','original_name','mime','size','sha256','path','created_at']));
        self::assertTrue(Db::schema()->hasColumns('cms_form_event',['site_id','submission_id','type','admin_id','body','from_status','to_status','created_at']));
    }
    public function test_form_code_is_unique_per_site_and_submission_status_defaults_to_new(): void
    {
        $now=date('Y-m-d H:i:s');$row=['site_id'=>1,'code'=>'contact','name'=>'Contact','fields'=>'[]','texts'=>'{}','settings'=>'{}','created_at'=>$now,'updated_at'=>$now];
        $form=(int)Db::table('cms_form')->insertGetId($row);
        Db::table('cms_form')->insert(array_replace($row,['site_id'=>2]));
        try{Db::table('cms_form')->insert($row);self::fail('Form code must be unique within a site');}catch(\Illuminate\Database\UniqueConstraintViolationException){self::assertTrue(true);}
        $id=(int)Db::table('cms_form_submission')->insertGetId(['site_id'=>1,'form_id'=>$form,'locale'=>'zh-cn','fields'=>'[]','values'=>'{}','search_text'=>'','created_at'=>$now,'updated_at'=>$now]);
        $submission=Db::table('cms_form_submission')->find($id);
        self::assertSame('new',$submission->status);self::assertSame(0,(int)$submission->lock_version);self::assertNull($submission->member_id);self::assertNull($submission->handled_at);
        self::assertSame(1,(int)Db::table('cms_form')->where('id',$form)->value('status'));
    }
}
