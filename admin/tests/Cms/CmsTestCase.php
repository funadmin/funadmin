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
        foreach(array_reverse(\app\cms\service\Schema::tables()) as $table) Db::schema()->dropIfExists($table);
        \app\cms\service\Schema::install();
        \app\cms\service\ModelService::seed();
    }
    protected function site(string $code='main',string $host='cms.test'): array
    {
        return (new \app\cms\service\SiteService())->save(['code'=>$code,'name'=>$code,'default_locale'=>'zh-cn','host'=>$host], '1');
    }
}
