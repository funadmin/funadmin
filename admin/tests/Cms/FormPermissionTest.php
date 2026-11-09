<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\admin\authorization\service\PermissionCode;
use app\admin\plugins\cms\controller\Forms;
use app\cms\service\FormPermissionUpgrade;
use app\cms\service\PermissionCatalog;
use support\Db;
final class FormPermissionTest extends CmsTestCase
{
    private const FORM = ['cms:form:list','cms:form:manage','cms:form:submissions','cms:form:handle','cms:form:export','cms:form:delete'];

    public function test_presets_grant_form_operations_per_role(): void
    {
        self::assertSame(['name'=>'表单','menu'=>'list','actions'=>['list'=>'查看表单','manage'=>'管理表单','submissions'=>'查看提交','handle'=>'跟进处理','export'=>'导出提交','delete'=>'删除提交']],PermissionCatalog::groups()['form']);
        $presets=PermissionCatalog::presets();
        $form=static fn(string $role): array=>array_values(array_intersect($presets[$role][1],self::FORM));
        self::assertSame(self::FORM,$form('administrator'));
        self::assertSame(['cms:form:list','cms:form:submissions','cms:form:handle'],$form('editor'));
        self::assertSame(['cms:form:list','cms:form:submissions','cms:form:handle'],$form('publisher'));
        self::assertSame(['cms:form:list','cms:form:submissions'],$form('viewer'));
        // 新建站点照常按预设写入。
        $site=$this->site();
        self::assertSame(self::FORM,$this->formPermissions($site['id'],'administrator'));
        self::assertSame(['cms:form:list','cms:form:submissions','cms:form:handle'],$this->formPermissions($site['id'],'editor'));
        self::assertSame(['cms:form:list','cms:form:submissions'],$this->formPermissions($site['id'],'viewer'));
    }

    public function test_backfill_only_touches_existing_administrator_roles_and_is_idempotent(): void
    {
        $a=$this->site();$b=$this->site('second','second.test');$gone=$this->site('gone','gone.test');
        // 模拟升级前的已有站点：管理员与其他预设角色都没有表单权限；b 站管理员只缺导出与删除。
        Db::table('cms_site_role_permission')->where('permission_code','like','cms:form:%')->whereIn('site_id',[$a['id'],$gone['id']])->delete();
        Db::table('cms_site_role_permission')->where('site_id',$b['id'])->whereIn('permission_code',['cms:form:export','cms:form:delete'])->delete();
        Db::table('cms_site_role_permission')->where('site_id',$b['id'])->where('permission_code','like','cms:form:%')->whereIn('role_id',Db::table('cms_site_role')->where('site_id',$b['id'])->where('code','<>','administrator')->pluck('id'))->delete();
        $custom=Db::table('cms_site_role')->insertGetId(['site_id'=>$a['id'],'code'=>'custom','name'=>'Custom','status'=>1]);
        Db::table('cms_site_role_permission')->insert(['site_id'=>$a['id'],'role_id'=>$custom,'permission_code'=>'cms:content:list']);
        Db::table('cms_site')->where('id',$gone['id'])->update(['deleted_at'=>\app\cms\service\Input::now()]);
        $before=$this->others();

        self::assertSame(8,FormPermissionUpgrade::backfill());
        self::assertSame(self::FORM,$this->formPermissions($a['id'],'administrator'));
        self::assertSame(self::FORM,$this->formPermissions($b['id'],'administrator'));
        self::assertSame([],$this->formPermissions($gone['id'],'administrator'));
        self::assertSame($before,$this->others());
        self::assertSame(['cms:content:list'],Db::table('cms_site_role_permission')->where('role_id',$custom)->pluck('permission_code')->all());
        $rows=Db::table('cms_site_role_permission')->count();

        self::assertSame(0,FormPermissionUpgrade::backfill());
        self::assertSame($rows,Db::table('cms_site_role_permission')->count());
    }

    public function test_ceilings_map_to_forms_controller_codes(): void
    {
        $methods=[];
        foreach(self::FORM as $operation)foreach(PermissionCatalog::ceilings($operation) as $code){
            if($code==='cms:sites:context'){self::assertSame($code,PermissionCode::derive(\app\admin\plugins\cms\controller\Sites::class,'context'));continue;}
            $method=substr($code,strlen('cms:forms:'));
            self::assertTrue(method_exists(Forms::class,$method),$code);
            self::assertSame($code,PermissionCode::derive(Forms::class,$method));
            $methods[]=$method;
        }
        self::assertContains('cms:sites:context',PermissionCatalog::ceilings('cms:form:list'));
        self::assertSame(['delete','detail','export','file','index','invalidate','note','remove','save','status','submissions','summary','transition'],(static function(array $m): array{sort($m);return $m;})(array_values(array_unique($methods))));
        self::assertSame(['cms:forms:export'],PermissionCatalog::ceilings('cms:form:export'));
        self::assertSame(['cms:forms:remove'],PermissionCatalog::ceilings('cms:form:delete'));
    }

    /** @return list<string> 按 FORM 顺序排列 */
    private function formPermissions(string $siteId,string $role): array
    {
        return Db::table('cms_site_role_permission as p')->join('cms_site_role as r','r.id','=','p.role_id')->where('p.site_id',$siteId)->where('r.code',$role)->where('p.permission_code','like','cms:form:%')->pluck('p.permission_code')->sort(fn($x,$y)=>array_search($x,self::FORM,true)<=>array_search($y,self::FORM,true))->values()->all();
    }

    /** 非管理员角色的全部权限行，用于断言补齐不触碰它们。 */
    private function others(): array
    {
        return Db::table('cms_site_role_permission as p')->join('cms_site_role as r','r.id','=','p.role_id')->where('r.code','<>','administrator')->orderBy('p.site_id')->orderBy('p.role_id')->orderBy('p.permission_code')->get(['p.site_id','p.role_id','p.permission_code'])->map(fn($r)=>(array)$r)->all();
    }
}
