<?php
declare(strict_types=1);
namespace Tests\Cms;
use app\common\plugin\sdk\Manifest;
use app\admin\plugins\cms\controller\Sites;
use PHPUnit\Framework\TestCase;
final class CommunityWiringTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_installed_member_and_qa_routes_coexist_with_the_cms_page_catch_all(): void
    {
        \Webman\Route::load([config_path()]);
        foreach (['/cms/member/context','/cms/member/identity','/cms/member/submissions'] as $path) {
            $route=\Webman\Route::dispatch('GET',$path);
            self::assertSame(\FastRoute\Dispatcher::FOUND,$route[0]);
            self::assertSame(\app\cms\controller\MemberContent::class,$route[1]['callback'][0]);
        }
        self::assertSame(\app\qa\controller\Questions::class,\Webman\Route::dispatch('GET','/qa/api/questions')[1]['callback'][0]);
        self::assertSame(\app\cms\controller\Index::class,\Webman\Route::dispatch('GET','/cms/news/example.html')[1]['callback'][0]);
    }
    public function test_member_submission_menu_and_reviewer_context_are_declared(): void
    {
        $root=dirname(__DIR__,2).'/plugins/cms';
        $data=json_decode(file_get_contents($root.'/plugin.json'),true,flags:JSON_THROW_ON_ERROR);
        self::assertSame('1.3.0',$data['version']);
        self::assertContains('cms:submission:review',array_column($data['adminWeb']['permissions'],'code'));
        $route=array_values(array_filter($data['adminWeb']['routes'],fn($r)=>$r['path']==='/plugin/cms/contributions'));
        self::assertCount(1,$route);self::assertSame('Contributions',$route[0]['component']);
        (new \ReflectionMethod(Manifest::class,'validateAdminWeb'))->invoke(null,$root,$data['adminWeb'],$data);
        // 投稿审核员进入站点上下文：投稿列表站点操作覆盖 Sites::context 接口。
        self::assertContains(\app\admin\authorization\service\PermissionCode::derive(Sites::class,'context'),\app\cms\service\PermissionCatalog::scope([\app\admin\authorization\service\PermissionCode::derive(Sites::class,'context')],['cms:submission:list']));
    }
    public function test_qa_manifest_requires_upgraded_cms_and_has_a_real_moderation_page(): void
    {
        $root=dirname(__DIR__,2).'/plugins/qa';
        self::assertFileExists($root.'/plugin.json');
        $data=json_decode(file_get_contents($root.'/plugin.json'),true,flags:JSON_THROW_ON_ERROR);
        self::assertSame('>=1.3.0',$data['requires']['plugins']['cms']);
        self::assertSame(['qa:moderation:index','qa:moderation:review'],array_column($data['adminWeb']['permissions'],'code'));
        (new \ReflectionMethod(Manifest::class,'validateAdminWeb'))->invoke(null,$root,$data['adminWeb'],$data);
        self::assertFileExists($root.'/'.$data['entry']['file']);
    }
}
