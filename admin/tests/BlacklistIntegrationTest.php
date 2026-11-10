<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\service\AdminSessionService;
use app\common\model\Blacklist;
use DomainException;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;

final class BlacklistIntegrationTest extends TestCase
{
    use \Tests\Support\RefreshCoreDatabase;

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        Db::beginTransaction();
        foreach (['login_attempts', 'admin_auth_tokens', 'browser_sessions', 'admin'] as $table) {
            Db::table($table)->delete();
        }
        Db::table('admin')->insert([
            'id' => 1,
            'username' => 'blacklist_admin',
            'password' => password_hash('blacklist-test-password', PASSWORD_DEFAULT),
            'status' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    public function test_migration_creates_blacklist_table_and_model_uses_logical_table_name(): void
    {
        self::assertTrue(Db::schema()->hasTable('blacklist'));
        $item = Blacklist::forceCreate(['ip' => '198.51.100.10', 'remark' => '测试规则', 'status' => 1]);
        self::assertSame('198.51.100.10', $item->toApiData()['ip']);
        self::assertSame(1, Db::table('blacklist')->where('id', $item->id)->count());
    }

    public function test_enabled_exact_ip_blocks_login_but_disabled_and_deleted_rules_do_not(): void
    {
        Blacklist::forceCreate(['ip' => '198.51.100.20', 'remark' => '启用规则', 'status' => 1]);
        self::assertTrue(Blacklist::isBlocked('198.51.100.20'));
        self::assertFalse(Blacklist::isBlocked('198.51.100.21'));

        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);
        (new AdminSessionService())->login('blacklist_admin', 'blacklist-test-password', '198.51.100.20');
    }

    public function test_blacklist_rejects_invalid_payload_and_soft_deleted_rows_leave_active_scope(): void
    {
        self::assertSame('IP/规则不能为空', Blacklist::validateAttributes(['ip' => '', 'remark' => '']));
        $item = Blacklist::forceCreate(['ip' => '198.51.100.30', 'remark' => '', 'status' => 1]);
        $item->delete();
        self::assertFalse(Blacklist::isBlocked('198.51.100.30'));
        self::assertCount(1, Blacklist::onlyTrashed()->where('id', $item->id)->get());
    }
}
