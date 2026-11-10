<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\controller\SystemMember;
use app\common\model\UpgradeManifest;
use app\common\plugin\model\PluginVersionHistory;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

/**
 * ThinkPHP 模型默认允许批量赋值，Eloquent 默认全部 guarded：迁移遗留的 Model::create() 会抛
 * MassAssignmentException。这些写路径必须真正落库。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EloquentMassAssignmentRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    public function test_admin_member_create_persists_member_and_relations(): void
    {
        Db::table('member_group')->insert(['id' => 77, 'name' => '批量赋值组', 'status' => 1]);
        $levelId = (int) Db::table('member_level')->where('status', 1)->value('id');
        if ($levelId === 0) {
            $levelId = (int) Db::table('member_level')->insertGetId(['name' => '默认等级', 'status' => 1]);
        }
        $body = json_encode(['username' => 'mass_member', 'mobile' => '13800009999', 'email' => 'mass@example.com', 'sex' => '1', 'groupIds' => [77], 'levelId' => $levelId, 'status' => 1], JSON_THROW_ON_ERROR);
        $request = new Request("POST /admin/system/member HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);

        $payload = json_decode((new SystemMember())->create()->rawBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $payload['code'], (string) ($payload['msg'] ?? ''));
        $member = Db::table('member')->where('username', 'mass_member')->first();
        self::assertNotNull($member);
        self::assertSame('13800009999', $member->mobile);
        self::assertSame([77], array_map('intval', Db::table('member_group_relation')->where('member_id', $member->id)->pluck('group_id')->all()));
    }

    public function test_plugin_version_history_can_be_recorded(): void
    {
        // PluginPackageService::record() 在成功操作后调用 PluginVersionHistory::create()。
        PluginVersionHistory::create(['plugin_code' => 'massdemo', 'version' => '1.2.3', 'package_hash' => str_repeat('a', 64)]);
        self::assertSame('1.2.3', Db::table('plugin_version_history')->where('plugin_code', 'massdemo')->value('version'));
    }

    public function test_upgrade_manifest_can_be_recorded(): void
    {
        UpgradeManifest::create(['manifest_id' => 'mass-manifest', 'payload' => ['version' => '9.9.9'], 'expires_at' => date('Y-m-d H:i:s', time() + 60), 'consumed_at' => null]);
        self::assertSame(['version' => '9.9.9'], UpgradeManifest::where('manifest_id', 'mass-manifest')->firstOrFail()->payload);
    }
}
