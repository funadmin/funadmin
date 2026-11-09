<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{ContributionSchema,ContributionPolicy,ContributionService,PermissionCatalog,SiteRoleService,SiteRoleSchema};
use app\admin\authorization\service\{CasbinService,ResourceRegistryService};
use support\Db;
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class ContributionAuthorizationTest extends CmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();ContributionSchema::install();(new \app\common\service\database\MigrationService())->run();
        self::registerHostPermissions();
        Db::table('admin')->insert(['id'=>91,'username'=>'submission_reviewer','password'=>'unused','status'=>1]);
        Db::table('auth_group')->insert(['id'=>91,'code'=>'submission_reviewer','name'=>'Reviewer','status'=>1]);
        CasbinService::instance()->syncAdminRoles(91,[91]);
        CasbinService::instance()->syncRolePermissions(91,Db::table('permission')->where('source_name','cms')->pluck('id')->all());
        Db::table('member')->insert(['id'=>701,'username'=>'contributor','sex'=>'0','level_id'=>1,'status'=>1]);
    }
    private function denied(callable $call): void {try{$call();self::fail('Expected forbidden');}catch(\InvalidArgumentException $e){self::assertSame(403,$e->getCode());}}
    public function test_review_requires_both_site_assignment_and_host_capability_and_never_grants_old_roles(): void
    {
        $a=$this->site()['id'];$b=$this->site('other','other.test')['id'];$p=new ContributionPolicy();$p->save($a,'1',['enabled'=>true,'model_codes'=>['article']]);$p->save($b,'1',['enabled'=>true,'model_codes'=>['article']]);
        $v=new ContributionService();$payload=['request_key'=>'review_test','title'=>'Title','body_html'=>'Body','model_code'=>'article','data'=>[]];
        $first=$v->submit($a,'zh-cn','701','content',$payload);$other=$v->submit($b,'zh-cn','701','content',$payload);
        $roles=new SiteRoleService();$role=$roles->saveRole($a,'1',['code'=>'review_only','name'=>'Review only','permissions'=>['cms:submission:list','cms:submission:review']]);$roles->assign($a,'1','91',[$role['id']]);
        $this->denied(fn()=>$v->review($b,'91',$other['id'],0,'publish'));
        $this->denied(fn()=>$v->reviewContent($a,'91',$first['id'],0,'publish'));
        CasbinService::instance()->syncRolePermissions(91,self::permissionIds(['cms:submission:list','cms:contributions:index','cms:sites:context']));
        $this->denied(fn()=>$v->review($a,'91',$first['id'],0,'reject','No capability'));
        CasbinService::instance()->syncRolePermissions(91,Db::table('permission')->where('source_name','cms')->pluck('id')->all());
        self::assertSame('rejected',$v->review($a,'91',$first['id'],0,'reject','Reason')['status']);
        Db::table('cms_site_role_permission')->where('role_id',$role['id'])->where('permission_code','cms:submission:review')->delete();SiteRoleSchema::seedSite($a);
        self::assertFalse(Db::table('cms_site_role_permission')->where('role_id',$role['id'])->where('permission_code','cms:submission:review')->exists());
    }
}
