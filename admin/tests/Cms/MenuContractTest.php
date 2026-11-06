<?php
declare(strict_types=1);
namespace Tests\Cms;
use app\common\plugin\sdk\Manifest;
use app\admin\plugin\service\PluginInfrastructureService;
use app\admin\authorization\model\AdminMenu;
use support\Db;
final class MenuContractTest extends \PHPUnit\Framework\TestCase
{
    private function data(): array { return json_decode(file_get_contents(dirname(__DIR__,2).'/plugins/cms/plugin.json'),true); }
    private function validate(array $data): void { (new \ReflectionMethod(Manifest::class,'validateAdminWeb'))->invoke(null,dirname(__DIR__,2).'/plugins/cms',$data['adminWeb'],$data); }
    public function test_nested_menu_rejects_unknown_permission(): void {
        $d=$this->data();$d['adminWeb']['menu']=[['name'=>'CMS','path'=>'/plugin/cms','menulist'=>[['name'=>'Bad','path'=>'/plugin/cms/manage','permission'=>'cms:unknown:list']]]];
        $this->expectException(\RuntimeException::class);$this->validate($d);
    }
    public function test_leaf_requires_matching_route_and_directory_requires_namespace(): void {
        $d=$this->data();$d['adminWeb']['menu']=[['name'=>'CMS','path'=>'/plugin/other','menulist'=>[['name'=>'Page','path'=>'/plugin/cms/missing','permission'=>'cms:site:list']]]];
        $this->expectException(\RuntimeException::class);$this->validate($d);
    }
    public function test_depth_is_bounded(): void {
        $d=$this->data();$node=$d['adminWeb']['menu'][0];for($i=0;$i<9;$i++)$node=['name'=>'Directory','path'=>'/plugin/cms/d'.$i,'menulist'=>[$node]];$d['adminWeb']['menu']=[$node];
        $this->expectException(\RuntimeException::class);$this->validate($d);
    }
    public function test_nested_children_survive_repeat_registration_and_removed_child_is_disabled(): void {
        (new \app\common\service\database\MigrationService())->run();
        $p=new PluginInfrastructureService();$permissions=[['code'=>'nestedtest:page:list','name'=>'Page'],['code'=>'nestedtest:other:list','name'=>'Other']];
        $child=['name'=>'Page','path'=>'/plugin/nestedtest/page','permission'=>'nestedtest:page:list'];$other=['name'=>'Other','path'=>'/plugin/nestedtest/other','permission'=>'nestedtest:other:list'];
        $menus=[['name'=>'Nested','path'=>'/plugin/nestedtest','menulist'=>[$child,$other]]];
        $p->registerResources($permissions,$menus,'nestedtest');$p->registerResources($permissions,$menus,'nestedtest');
        self::assertSame(3,AdminMenu::where('source_name','nestedtest')->where('status',1)->count());
        $menus[0]['menulist']=[$child];$p->registerResources($permissions,$menus,'nestedtest');
        self::assertSame(2,AdminMenu::where('source_name','nestedtest')->where('status',1)->count());
        Db::table('admin')->insertOrIgnore(['id'=>1,'username'=>'menu_root','password'=>'unused','status'=>1]);
        $tree=(new \app\admin\authorization\service\AuthorizationService())->menus(['id'=>1]);$root=array_values(array_filter($tree,fn($m)=>$m['path']==='/plugin/nestedtest'))[0];self::assertSame('M',$root['type']);
    }
    public function test_permission_filter_can_remove_but_never_add_host_rights(): void {
        $root=sys_get_temp_dir().'/cms-menu-hook-'.bin2hex(random_bytes(5));mkdir($root.'/plugins/scope/hooks',0755,true);
        file_put_contents($root.'/plugins/scope/hooks/filter.php', '<?php return ["scope"=>fn($codes,$context)=>["cms:content:list","system:admin:delete"]];');
        $records=['scope'=>['code'=>'scope','status'=>1,'lifecycle_state'=>'enabled']];
        $manifests=['scope'=>['serverHooks'=>[['id'=>'scope','type'=>'filter','point'=>'admin.permission_codes','file'=>'hooks/filter.php','handler'=>'scope']]]];
        (new \app\common\plugin\sdk\PluginActivationCompiler($root.'/runtime',$root.'/plugins'))->compile($records,$manifests);
        $container=\support\Container::instance();$property=new \ReflectionProperty($container,'instances');$previous=$property->getValue($container);$instances=$previous;unset($instances[\app\common\plugin\hook\HookRuntime::class]);$property->setValue($container,$instances);
        $container->addDefinitions([\app\common\plugin\hook\HookRuntime::class=>static fn()=>new \app\common\plugin\hook\HookRuntime(new \app\common\plugin\sdk\PluginActivationReader($root.'/runtime'),$root.'/plugins')]);
        try {
            $service=new \app\admin\authorization\service\AuthorizationService();
            self::assertSame(['cms:content:list'],$service->scopedPermissionCodes(92,['cms:content:list','cms:content:save']));
        } finally {
            $property->setValue($container,$previous);
            $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($root);
        }
    }
    public function test_failing_permission_filter_only_hides_its_own_plugin_permissions(): void {
        (new \app\common\service\database\MigrationService())->run();
        Db::table('permission')->whereIn('code',['broken:item:list','scope:item:list'])->delete();
        Db::table('permission')->insert([
            ['code'=>'broken:item:list','obj'=>'broken','act'=>'list','name'=>'Broken','resource_type'=>'capability','source_type'=>'plugin','source_name'=>'broken'],
            ['code'=>'scope:item:list','obj'=>'scope','act'=>'list','name'=>'Scope','resource_type'=>'capability','source_type'=>'plugin','source_name'=>'scope'],
        ]);
        $root=sys_get_temp_dir().'/cms-menu-hook-'.bin2hex(random_bytes(5));
        mkdir($root.'/plugins/broken/hooks',0755,true);mkdir($root.'/plugins/scope/hooks',0755,true);
        file_put_contents($root.'/plugins/broken/hooks/filter.php','<?php return ["boom"=>function(){throw new \\RuntimeException("boom");}];');
        file_put_contents($root.'/plugins/scope/hooks/filter.php','<?php return ["scope"=>fn($codes)=>array_values(array_diff($codes,["cms:content:save"]))];');
        $records=['broken'=>['code'=>'broken','status'=>1,'lifecycle_state'=>'enabled'],'scope'=>['code'=>'scope','status'=>1,'lifecycle_state'=>'enabled']];
        $manifests=[
            'broken'=>['serverHooks'=>[['id'=>'boom','type'=>'filter','point'=>'admin.permission_codes','file'=>'hooks/filter.php','handler'=>'boom','priority'=>1]]],
            'scope'=>['serverHooks'=>[['id'=>'scope','type'=>'filter','point'=>'admin.permission_codes','file'=>'hooks/filter.php','handler'=>'scope','priority'=>2]]],
        ];
        (new \app\common\plugin\sdk\PluginActivationCompiler($root.'/runtime',$root.'/plugins'))->compile($records,$manifests);
        $container=\support\Container::instance();$property=new \ReflectionProperty($container,'instances');$previous=$property->getValue($container);$instances=$previous;unset($instances[\app\common\plugin\hook\HookRuntime::class]);$property->setValue($container,$instances);
        $container->addDefinitions([\app\common\plugin\hook\HookRuntime::class=>static fn()=>new \app\common\plugin\hook\HookRuntime(new \app\common\plugin\sdk\PluginActivationReader($root.'/runtime'),$root.'/plugins',static function(){})]);
        try {
            $service=new \app\admin\authorization\service\AuthorizationService();
            self::assertSame(['cms:content:list','scope:item:list','system:admin:list'],$service->scopedPermissionCodes(92,['cms:content:list','cms:content:save','broken:item:list','scope:item:list','system:admin:list']));
        } finally {
            $property->setValue($container,$previous);
            Db::table('permission')->whereIn('code',['broken:item:list','scope:item:list'])->delete();
            $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($root);
        }
    }
}
