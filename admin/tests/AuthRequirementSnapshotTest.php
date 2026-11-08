<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;
use Tests\Support\AuthRequirementSnapshot;

/**
 * 后台与会员 API 的登录 / 权限要求快照。改造鉴权写法时，任何接口要求变化都会在这里暴露。
 * 有意调整接口要求时，先人工确认差异，再运行 php tests/fixtures/update-auth-requirements.php 重新生成 fixture。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AuthRequirementSnapshotTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/fixtures/auth-requirements.json';

    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
    }

    public function test_snapshot_cannot_be_regenerated_by_the_test_itself(): void
    {
        $source = (string) file_get_contents(__FILE__);
        self::assertStringNotContainsString('UPDATE_AUTH' . '_SNAPSHOT', $source, '快照只能由人工确认后重新生成，测试不得自行改写');
    }

    public function test_every_registered_route_is_covered_by_the_snapshot(): void
    {
        \Webman\Route::load([config_path()]);
        $expected = json_decode((string) file_get_contents(self::FIXTURE), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];
        foreach (\Webman\Route::getRoutes() as $route) {
            $callback = $route->getCallback();
            if (!is_array($callback) || !is_string($callback[0]) || AuthRequirementSnapshot::isBusinessModule($callback[0])) continue;
            foreach ($route->getMethods() as $method) {
                $key = $method . ' ' . $route->getPath() . ' => ' . $callback[0] . '::' . $callback[1];
                if (!array_key_exists($key, $expected)) $missing[] = $key;
            }
        }
        self::assertSame([], $missing, '快照必须覆盖全部路由（含插件会员接口）');
    }

    public function test_auth_requirements_match_snapshot(): void
    {
        $this->assertMatchesFixture(AuthRequirementSnapshot::collect());
    }

    private function assertMatchesFixture(array $actual): void
    {
        $expected = json_decode((string) file_get_contents(self::FIXTURE), true, flags: JSON_THROW_ON_ERROR);
        $changes = [];
        foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $key) {
            if (($expected[$key] ?? null) !== ($actual[$key] ?? null)) {
                $changes[] = $key . "\n  before: " . json_encode($expected[$key] ?? null, JSON_UNESCAPED_UNICODE) . "\n  after:  " . json_encode($actual[$key] ?? null, JSON_UNESCAPED_UNICODE);
            }
        }
        self::assertSame([], $changes, "接口登录/权限要求发生变化：\n" . implode("\n", $changes));
        self::assertGreaterThan(350, count($actual));
    }
}
