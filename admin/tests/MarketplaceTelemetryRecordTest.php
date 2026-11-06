<?php

declare(strict_types=1);

use app\common\plugin\marketplace\MarketplaceTelemetry;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;

final class MarketplaceTelemetryRecordTest extends TestCase
{
    protected function setUp(): void { (new MigrationService())->run(); Db::beginTransaction(); }
    protected function tearDown(): void { Db::rollBack(); }

    public function testPluginRecordReturnsOnlyTelemetryFieldsIncludingSoftDeleted(): void
    {
        Db::table('plugin')->insert(['code'=>'telemetryfixture','name'=>'遥测','version'=>'1.2.0','source'=>'cloud','status'=>0,'lifecycle_state'=>'disabled','deleted_at'=>date('Y-m-d H:i:s')]);
        self::assertSame(['code'=>'telemetryfixture','version'=>'1.2.0','source'=>'cloud'], MarketplaceTelemetry::pluginRecord('telemetryfixture'));
        self::assertNull(MarketplaceTelemetry::pluginRecord('missingfixture'));
    }
}
