<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;

/** 身份中心已移除：核心账号流程不再依赖任何身份代码、路由、菜单或数据表。 */
final class IdentityRemovalTest extends TestCase
{
    private const IDENTITY_TABLES = ['application_assignment', 'application_database', 'application_domain', 'application_permission', 'application_role', 'application_role_permission', 'application_user_permission', 'application_user_role', 'backchannel_logout_delivery', 'client_grant', 'client_scope', 'client_secret', 'enterprise_application', 'identity_admin_link', 'identity_audit_log', 'identity_consent', 'identity_credential', 'identity_login_attempt', 'identity_member_link', 'identity_migration_audit', 'identity_sso_config', 'identity_tenant', 'identity_user', 'identity_user_department', 'oauth_authorization', 'oauth_authorization_code', 'oauth_authorization_scope', 'oauth_client', 'oauth_token', 'oauth_token_scope', 'oidc_client_session', 'oidc_session', 'oidc_signing_key', 'redirect_uri', 'scope'];

    public function test_identity_source_directories_and_routes_are_gone(): void
    {
        foreach (['app/identity', 'app/admin/identity', 'app/common/service/identity', 'app/common/model/identity'] as $directory) {
            self::assertDirectoryDoesNotExist(base_path($directory), $directory);
        }
        $routes = (string) file_get_contents(base_path('config/route.php'));
        self::assertStringNotContainsString("Route::group('/identity'", $routes);
        self::assertStringNotContainsString('app\\\\identity\\\\', $routes);
    }

    public function test_core_account_code_has_no_identity_references(): void
    {
        foreach (['app/ExceptionHandle.php', 'app/admin/system/controller/SystemMember.php', 'app/admin/authorization/controller/SystemAdmin.php',
            'app/common/service/member/MemberAuthService.php', 'app/common/service/member/MemberAccountService.php'] as $file) {
            self::assertDoesNotMatchRegularExpression('/\\\\identity\\\\|IdentityAdapter|identity_admin_link/', (string) file_get_contents(base_path($file)), $file);
        }
    }

    public function test_removal_migration_drops_identity_tables_and_retires_menus_and_permissions(): void
    {
        (new MigrationService())->run();
        foreach (self::IDENTITY_TABLES as $table) self::assertFalse(Db::schema()->hasTable($table), $table);
        self::assertSame(0, Db::table('admin_menu')->whereNull('deleted_at')->where('status', 1)->where(static function ($query): void {
            $query->where('href', '/applications')->orWhere('href', 'like', '/applications/%');
        })->count());
        self::assertSame(0, Db::table('permission')->whereNull('deleted_at')->where('status', 1)->where(static function ($query): void {
            $query->where('obj', 'like', 'admin/identity.%')->orWhere('obj', 'like', 'identity/%');
        })->count());
        // 重复执行迁移必须幂等，且不会误删身份表以外的任何表。
        $tablesBefore = $this->tableNames();
        (new MigrationService())->run();
        self::assertSame($tablesBefore, $this->tableNames());
    }

    /** @return list<string> */
    private function tableNames(): array
    {
        $names = array_map(static fn (array $table): string => (string) $table['name'], Db::schema()->getTables());
        sort($names);
        return $names;
    }
}
