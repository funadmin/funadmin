<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\PermissionCatalog;
use app\cms\service\SiteRoleService;
use app\cms\service\SiteAuthorization;
use app\admin\authorization\service\CasbinService;
use support\Context;
use support\Db;
use support\Request;
final class SiteRoleDelegationTest extends CmsTestCase
{
    public function test_delegation_respects_host_descendants_shared_roles_and_own_ceiling(): void
    {
        (new \app\common\service\database\MigrationService())->run();
        self::registerHostPermissions();
        foreach([91=>0,92=>91,93=>0] as $id=>$parent){Db::table('admin')->insert(['id'=>$id,'username'=>'delegation'.$id,'password'=>'unused','status'=>1]);Db::table('auth_group')->insert(['id'=>$id,'pid'=>$parent,'code'=>'delegation'.$id,'name'=>'Delegate','status'=>1]);CasbinService::instance()->syncAdminRoles($id,[$id]);CasbinService::instance()->syncRolePermissions($id,Db::table('permission')->where('source_name','cms')->pluck('id')->all());}
        $site=$this->site();$id=$site['id'];$r=new SiteRoleService();$own=['cms:access:list','cms:access:roles','cms:access:members','cms:content:list'];
        $role=$r->saveRole($id,'1',['code'=>'delegate','name'=>'Delegate','permissions'=>$own]);$r->assign($id,'1','91',[$role['id']]);
        $reader=$r->saveRole($id,'1',['code'=>'shared','name'=>'Shared','permissions'=>['cms:content:list']]);$r->assign($id,'1','92',[$reader['id']]);$r->assign($id,'1','93',[$reader['id']]);
        $high=$r->saveRole($id,'1',['code'=>'high','name'=>'High','permissions'=>['cms:content:publish']]);
        $request=new Request("GET /admin/plugin/cms/access HTTP/1.1\r\nHost: test\r\n\r\n");$request->context['admin']=['id'=>91];Context::set(\Webman\Http\Request::class,$request);
        try {
            self::assertSame(['92'],array_column($r->candidates($id,'91'),'id'));
            foreach([
                fn()=>$r->assign($id,'91','93',[$reader['id']]),
                fn()=>$r->assign($id,'91','92',[$high['id']]),
                fn()=>$r->saveRole($id,'91',['id'=>$reader['id'],'code'=>'shared','name'=>'Changed','permissions'=>['cms:content:list']]),
                fn()=>$r->saveRole($id,'91',['code'=>'overgrant','name'=>'Overgrant','permissions'=>['cms:content:publish']]),
                fn()=>$r->assign($id,'91','91',[$role['id']]),
            ] as $attempt){$before=Db::table('cms_site_auth_audit')->count();try{$attempt();self::fail('Forbidden delegation accepted');}catch(\InvalidArgumentException $e){self::assertSame(403,$e->getCode());}self::assertSame($before,Db::table('cms_site_auth_audit')->count());}
            $local=$r->saveRole($id,'91',['code'=>'local','name'=>'Local','permissions'=>['cms:content:list']]);$r->assign($id,'91','92',[$local['id']]);self::assertSame(['cms:content:list'],(new SiteAuthorization())->effective($id,'92'));
            $r->saveRole($id,'91',['id'=>$local['id'],'code'=>'local','name'=>'Disabled','status'=>0,'permissions'=>['cms:content:list']]);self::assertSame([],(new SiteAuthorization())->effective($id,'92'));
        }finally{Context::destroy();}
    }
}
