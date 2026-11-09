<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\SiteRoleSchema;
use app\cms\service\SiteRoleService;
use app\cms\service\SiteAuthorization;
use app\cms\service\PermissionCatalog;
use support\Db;
final class SiteRoleTest extends CmsTestCase
{
    protected function setUp(): void {
        parent::setUp();SiteRoleSchema::install();(new \app\common\service\database\MigrationService())->run();
        self::registerHostPermissions();
        foreach([91,92] as $id){Db::table('admin')->insertOrIgnore(['id'=>$id,'username'=>'cms_role_'.$id,'password'=>'unused','status'=>1]);Db::table('auth_group')->insertOrIgnore(['id'=>$id,'code'=>'cms_role_'.$id,'name'=>'CMS role','status'=>1]);$c=\app\admin\authorization\service\CasbinService::instance();$c->syncAdminRoles($id,[$id]);$c->syncRolePermissions($id,Db::table('permission')->where('source_name','cms')->pluck('id')->all());}
    }
    public function test_site_union_host_ceiling_and_immediate_revocation(): void {
        $a=$this->site();$b=$this->site('second','second.test');$r=new SiteRoleService();$auth=new SiteAuthorization();
        $editor=$r->saveRole($a['id'],'1',['code'=>'custom','name'=>'Editor','permissions'=>['cms:content:list','cms:content:edit']]);
        $publisher=$r->saveRole($a['id'],'1',['code'=>'pub','name'=>'Publisher','permissions'=>['cms:content:publish']]);
        $viewer=$r->saveRole($b['id'],'1',['code'=>'view','name'=>'Viewer','permissions'=>['cms:content:list']]);
        $r->assign($a['id'],'1','91',[$editor['id'],$publisher['id']]);$r->assign($b['id'],'1','91',[$viewer['id']]);
        self::assertContains('cms:content:edit',$auth->effective($a['id'],'91'));self::assertContains('cms:content:publish',$auth->effective($a['id'],'91'));
        self::assertSame(['cms:content:list'],$auth->effective($b['id'],'91'));
        $c=\app\admin\authorization\service\CasbinService::instance();$c->syncRolePermissions(91,self::permissionIds(['cms:content:list','cms:content:detail','cms:content:index','cms:content:lookups','cms:content:revisions','cms:content:translation','cms:sites:context']));
        self::assertSame(['cms:content:list'],$auth->effective($a['id'],'91'));
        $r->remove($a['id'],'1','91');self::assertSame([],$auth->effective($a['id'],'91'));self::assertNotEmpty($auth->effective($b['id'],'91'));
        self::assertSame(6,Db::table('cms_site_auth_audit')->count());
    }
    public function test_cross_site_role_unknown_code_and_membership_only_are_rejected(): void {
        $a=$this->site();$b=$this->site('other','other.test');$r=new SiteRoleService();
        $role=$r->saveRole($a['id'],'1',['code'=>'reader','name'=>'Reader','permissions'=>['cms:content:list']]);
        try{$r->assign($b['id'],'1','91',[$role['id']]);self::fail('Cross-site role accepted');}catch(\InvalidArgumentException $e){self::assertSame(422,$e->getCode());}
        try{$r->saveRole($a['id'],'1',['code'=>'bad','name'=>'Bad','permissions'=>['system:admin:delete']]);self::fail('Unknown permission accepted');}catch(\InvalidArgumentException $e){self::assertSame(422,$e->getCode());}
        Db::table('cms_site_admin')->insert(['site_id'=>$a['id'],'admin_id'=>92]);self::assertSame([],(new SiteAuthorization())->effective($a['id'],'92'));
    }
    public function test_bootstrap_seeds_only_standard_roles_and_never_assigns_members(): void {
        $s=$this->site();Db::table('cms_site_role')->where('site_id',$s['id'])->delete();Db::table('cms_site_admin')->insert(['site_id'=>$s['id'],'admin_id'=>91]);
        SiteRoleSchema::bootstrap();SiteRoleSchema::bootstrap();
        self::assertSame(['administrator','editor','publisher','viewer'],Db::table('cms_site_role')->where('site_id',$s['id'])->orderBy('code')->pluck('code')->all());
        self::assertArrayNotHasKey('legacy',PermissionCatalog::presets());
        self::assertSame(0,Db::table('cms_site_role_member')->where('admin_id',91)->count(),'站点成员只通过角色分配获得权限');
        self::assertSame([],(new SiteAuthorization())->effective($s['id'],'91'));
    }
    public function test_non_super_cannot_self_assign_or_edit_a_role_that_affects_self(): void {
        $s=$this->site();$r=new SiteRoleService();$role=$r->saveRole($s['id'],'1',['code'=>'delegate','name'=>'Delegate','permissions'=>PermissionCatalog::operations()]);$r->assign($s['id'],'1','91',[$role['id']]);
        foreach(['assign','role'] as $op){try{if($op==='assign')$r->assign($s['id'],'91','91',[$role['id']]);else $r->saveRole($s['id'],'91',['id'=>$role['id'],'code'=>'delegate','name'=>'Changed','permissions'=>PermissionCatalog::operations()]);self::fail('Self elevation accepted');}catch(\InvalidArgumentException $e){self::assertSame(403,$e->getCode());}}
    }
    public function test_inactive_registered_operations_do_not_remain_effective(): void {
        $site=$this->site();$r=new SiteRoleService();$role=$r->saveRole($site['id'],'1',['code'=>'active','name'=>'Active','permissions'=>['cms:content:list']]);$r->assign($site['id'],'1','91',[$role['id']]);
        Db::table('permission')->where('code','cms:content:list')->update(['status'=>0]);
        self::assertSame([],(new SiteAuthorization())->effective($site['id'],'91',['cms:content:list']));
        $this->expectException(\InvalidArgumentException::class);$r->saveRole($site['id'],'1',['code'=>'badactive','name'=>'Bad','permissions'=>['cms:content:list']]);
    }
    public function test_host_discovery_filter_hides_menu_without_its_view_operation(): void {
        $site=$this->site();$r=new SiteRoleService();$role=$r->saveRole($site['id'],'1',['code'=>'delegator','name'=>'Delegator','permissions'=>['cms:access:members']]);$r->assign($site['id'],'1','91',[$role['id']]);
        $map=require dirname(__DIR__,2).'/plugins/cms/hooks/permissions.php';
        $grant=['cms:access:assign','cms:access:candidates','cms:access:index','cms:access:remove','cms:access:save-role','cms:sites:context'];
        self::assertSame(['system:admin:list'],$map['scope'](array_merge(['system:admin:list'],$grant),['admin_id'=>91]));
    }
    public function test_host_discovery_keeps_cms_codes_when_site_role_schema_is_missing_so_controllers_report_409(): void {
        $map=require dirname(__DIR__,2).'/plugins/cms/hooks/permissions.php';
        $this->site();$codes=['system:admin:list','cms:content:index','cms:sites:context'];
        \support\Db::schema()->drop('cms_site_role_permission');
        // 站点权限表缺失属于"CMS 尚未完成升级"：不能把宿主授予的 CMS 码全部移除（否则先被 403 掩盖），由控制器返回 409。
        self::assertSame($codes,$map['scope']($codes,['admin_id'=>91]));
    }
    public function test_host_discovery_still_fails_closed_on_unexpected_errors(): void {
        $map=require dirname(__DIR__,2).'/plugins/cms/hooks/permissions.php';
        self::assertSame(['system:admin:list'],$map['scope'](['system:admin:list','cms:content:index'],['admin_id'=>'not-an-id']));
    }
    public function test_site_membership_is_managed_only_through_role_assignment(): void {
        self::assertFalse(method_exists(\app\cms\service\SiteService::class,'grant'));
        self::assertFalse(method_exists(\app\admin\plugins\cms\controller\Sites::class,'grant'));
        self::assertNotContains('cms:sites:grant',array_merge(PermissionCatalog::hostCodes(),...array_map(PermissionCatalog::ceilings(...),PermissionCatalog::operations())));
    }
    public function test_a_edits_site_one_and_reads_site_two_while_b_publishes_site_two_only(): void {
        $a=$this->site();$b=$this->site('site_two','site-two.test');$r=new SiteRoleService();$auth=new SiteAuthorization();
        $editor=$r->saveRole($a['id'],'1',['code'=>'a_editor','name'=>'Editor','permissions'=>['cms:content:list','cms:content:edit']]);
        $viewer=$r->saveRole($b['id'],'1',['code'=>'a_viewer','name'=>'Viewer','permissions'=>['cms:content:list']]);
        $publisher=$r->saveRole($b['id'],'1',['code'=>'b_publisher','name'=>'Publisher','permissions'=>['cms:content:list','cms:content:publish']]);
        $r->assign($a['id'],'1','91',[$editor['id']]);$r->assign($b['id'],'1','91',[$viewer['id']]);$r->assign($b['id'],'1','92',[$publisher['id']]);
        self::assertContains('cms:content:edit',$auth->effective($a['id'],'91'));self::assertNotContains('cms:content:publish',$auth->effective($a['id'],'91'));
        self::assertSame(['cms:content:list'],$auth->effective($b['id'],'91'));self::assertContains('cms:content:publish',$auth->effective($b['id'],'92'));self::assertSame([],$auth->effective($a['id'],'92'));
        $this->expectException(\InvalidArgumentException::class);$auth->assert($a['id'],'92','cms:content:publish');
    }
}
