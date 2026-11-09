<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use support\Db;
abstract class CmsTestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::assertTrue(class_exists(\app\cms\service\Schema::class),'CMS schema must exist');
        foreach(array_reverse(\app\cms\service\SiteRoleSchema::tables()) as $table) Db::schema()->dropIfExists($table);
        foreach(array_reverse(\app\cms\service\Schema::tables()) as $table) Db::schema()->dropIfExists($table);
        \app\cms\service\Schema::install();
        \app\cms\service\SiteRoleSchema::install();
        \app\cms\service\TemplateTagRemarkSchema::install();
        \app\cms\service\ModelService::seed();
    }
    /**
     * 宿主角色直接以权限码授权：站点操作码作为能力权限，CMS 后台方法推导出的接口权限码作为路由权限。
     *
     * @param list<string> $codes
     * @return list<int>
     */
    protected static function permissionIds(array $codes): array
    {
        return array_map('intval',Db::table('permission')->whereIn('code',array_values(array_unique($codes)))->where('status',1)->pluck('id')->all());
    }
    /** 登记 CMS 站点操作（能力权限）与 CMS 后台方法推导出的接口权限码，与插件启用时一致。 */
    protected static function registerHostPermissions(): void
    {
        \app\admin\authorization\service\ResourceRegistryService::instance()->registerPermissions(array_map(fn($c)=>['code'=>$c,'name'=>$c],\app\cms\service\PermissionCatalog::operations()),'plugin','cms');
        (new \app\admin\authorization\service\PermissionSyncService())->sync('cms');
    }
    protected function site(string $code='main',string $host='cms.test'): array
    {
        return (new \app\cms\service\SiteService())->save(['code'=>$code,'name'=>$code,'default_locale'=>'zh-cn','host'=>$host], '1');
    }
}
