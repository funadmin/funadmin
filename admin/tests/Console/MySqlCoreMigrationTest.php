<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\database\MigrationService;
use app\common\service\install\InstallSupport;
use PHPUnit\Framework\TestCase;
use support\Db;

final class MySqlCoreMigrationTest extends TestCase
{
    public function test_authoritative_root_sql_replays_and_repeats_without_archive(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要一次性核心 MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $directory = MigrationService::coreDirectory();
        $expected = array_map(static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), glob($directory . '/*.sql'));
        sort($expected);
        $service = new MigrationService();
        $admin = ['username' => 'root_installer', 'password' => 'local-install-fixture-123', 'email' => 'installer@example.test'];
        try {
            InstallSupport::installSchemaAndAdmin(base_path('database/migrations'), $admin);
            self::fail('MySQL 正式安装不得回退到 PHP 骨架');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('核心 SQL', $error->getMessage());
            self::assertSame([], Db::connection()->select('SHOW TABLES'));
        }
        InstallSupport::installSchemaAndAdmin($directory, $admin);
        self::assertSame($admin['username'], Db::table('admin')->value('username'));
        self::assertTrue(password_verify($admin['password'], Db::table('admin')->value('password')));
        self::assertFalse(Db::table('admin_menu')->where('href', '/admin/management')->exists());
        $browser = new \app\admin\authentication\service\BrowserAuthService();
        $session = $browser->bootstrap('');
        $loggedIn = $browser->login($session['cookie'], $session['csrfToken'], $admin['username'], $admin['password'], '127.0.0.1');
        self::assertSame($admin['username'], $browser->user($loggedIn['cookie'])['username']);
        $before = (array) Db::table('admin')->first();
        try {
            InstallSupport::installSchemaAndAdmin($directory, $admin);
            self::fail('已有管理员必须在重放基线前拒绝安装');
        } catch (\DomainException $error) {
            self::assertSame(409, $error->getCode());
            self::assertSame($before, (array) Db::table('admin')->first());
        }
        self::assertSame([], $service->runCore());
        $lockConfig = Db::connection()->getConfig();
        $competitor = new \PDO('mysql:host=' . $lockConfig['host'] . ';port=' . ($lockConfig['port'] ?? 3306) . ';dbname=' . Db::connection()->getDatabaseName(), $lockConfig['username'], $lockConfig['password']);
        $lockName = 'migration:' . substr(hash('sha256', Db::connection()->getDatabaseName()), 0, 40);
        $claim = $competitor->prepare('SELECT GET_LOCK(?, 0)');
        $claim->execute([$lockName]);
        self::assertSame(1, (int) $claim->fetchColumn());
        try {
            $service->runCore();
            self::fail('竞争连接已持锁时核心迁移必须拒绝');
        } catch (\RuntimeException $error) {
            self::assertSame('数据库迁移正在执行', $error->getMessage());
        } finally {
            $release = $competitor->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
        self::assertSame([], $service->runCore());
        self::assertNull(Db::connection()->selectOne('SELECT IS_USED_LOCK(?) owner', [$lockName])->owner);
        self::assertTrue(Db::schema()->hasTable('core_database_adoption'), '缺少独立接管登记表');
        self::assertSame(0, Db::table('core_database_adoption')->count(), '新安装不得伪造存量接管记录');
        self::assertTrue(Db::schema()->hasTable('ai_task'));
        foreach (['identity_tenant', 'identity_user', 'oauth_client', 'application_assignment', 'scope'] as $table) self::assertFalse(Db::schema()->hasTable($table), '身份模块已移除：' . $table);
        self::assertTrue(Db::schema()->hasTable('plugin'));
        self::assertTrue(Db::schema()->hasColumn('plugin', 'composer_conflict_ack'));
        foreach (['market_category', 'market_plugin', 'market_version', 'market_grant', 'market_token', 'market_order', 'market_payment_log', 'market_site', 'market_site_plugin', 'market_install_event', 'market_download'] as $table) self::assertTrue(Db::schema()->hasTable($table), '缺少历史兼容结构：' . $table);
        foreach (['market:plugin:view', 'market:plugin:manage', 'market:category:manage', 'market:grant:manage', 'market:order:view', 'market:order:manage', 'market:setting:manage', 'market:stat:view'] as $code) self::assertTrue(Db::table('permission')->where('code', $code)->where('source_type', 'plugin')->where('source_name', 'market')->exists(), $code);
        self::assertFalse(Db::table('permission')->where('source_type', 'plugin')->where('source_name', 'market')->where('status', 1)->exists());
        require_once base_path('app/functions.php');
        Db::beginTransaction();
        try {
            $request = new \support\Request("POST /api/v3/auth/register HTTP/1.1\r\n\r\n");
            $request->connection = (new \ReflectionClass(\Workerman\Connection\TcpConnection::class))->newInstanceWithoutConstructor();
            $request->context['session'] = new \Workerman\Protocols\Http\Session(bin2hex(random_bytes(16)));
            \support\Context::set(\Webman\Http\Request::class, $request);
            $id = (new \app\common\service\member\MemberAccountService())->register(['username' => 'mysqlbuyer', 'email' => 'mysqlbuyer@example.com', 'password' => 'buyer-password-123', 'password_confirm' => 'buyer-password-123'], '127.0.0.' . random_int(10, 200));
            self::assertGreaterThan(time() - 60, (int) Db::table('member')->where('id', $id)->value('last_login'));
        } finally {
            Db::rollBack();
            \support\Context::destroy();
        }
        self::assertTrue(Db::schema()->hasTable('admin_auth_tokens'), '核心迁移缺少原生认证补充表');
        self::assertTrue(Db::schema()->hasTable('browser_sessions'));
        self::assertTrue(Db::schema()->hasTable('login_attempts'));
        self::assertSame(1, Db::table('admin')->count());
        self::assertFalse(Db::table('admin_menu')->whereNotNull('deleted_at')->exists(), '基线不得携带软删除菜单墓碑行');
        self::assertFalse(Db::table('permission')->whereNotNull('deleted_at')->exists(), '基线不得携带软删除权限墓碑行');
        self::assertSame(count($expected), Db::table('system_migration')->where('scope', 'core')->count());
        foreach (glob($directory . '/*.sql') as $file) {
            self::assertSame(hash_file('sha256', $file), Db::table('system_migration')->where('scope', 'core')->where('version', pathinfo($file, PATHINFO_FILENAME))->value('checksum'));
        }
        $audit = new \app\common\service\database\CoreDatabaseAudit($directory);
        $snapshot = $audit->inspect(Db::connection());
        self::assertArrayHasKey('metadata', $snapshot['schema'][Db::connection()->getTablePrefix() . 'plugin_operation']['columns']);
        self::assertArrayHasKey('package_size', $snapshot['schema'][Db::connection()->getTablePrefix() . 'plugin_version_history']['columns']);
        self::assertArrayHasKey('manifest', $snapshot['schema'][Db::connection()->getTablePrefix() . 'plugin_version_history']['columns']);
        self::assertSame('varchar(255)', $snapshot['schema'][Db::connection()->getTablePrefix() . 'admin_log']['columns']['username']['COLUMN_TYPE']);
        self::assertSame('tinyint(1)', $snapshot['schema'][Db::connection()->getTablePrefix() . 'admin']['columns']['status']['COLUMN_TYPE']);
        foreach (['admin_menu' => ['sort_order' => 'bigint'], 'permission' => ['sort_order' => 'bigint'],
            'attach' => ['sort_order' => 'bigint', 'size' => 'bigint'], 'attach_group' => ['sort_order' => 'bigint'],
            'member' => ['login_num' => 'bigint'], 'member_level' => ['discount' => 'int', 'sort_order' => 'bigint'],
            'auth_group' => ['level' => 'int unsigned', 'name' => 'varchar(100)'], 'admin_log' => ['admin_id' => 'bigint unsigned'],
        ] as $table => $columns) foreach ($columns as $column => $type) self::assertSame($type, $snapshot['schema'][Db::connection()->getTablePrefix() . $table]['columns'][$column]['COLUMN_TYPE']);
        foreach (['plugin' => ['version' => 'varchar(64)', 'error_stage' => 'varchar(80)', 'recovery_path' => 'varchar(1024)', 'lifecycle_state' => 'varchar(40)', 'operation_token' => 'varchar(128)'],
            'plugin_operation' => ['stage' => 'varchar(80)', 'operation_token' => 'varchar(128)'],
            'plugin_resource' => ['resource_type' => 'varchar(40)', 'source_path' => 'varchar(1024)'],
            'plugin_version_history' => ['package_path' => 'varchar(1024)'],
            'upgrade_task' => ['idempotency_token' => 'varchar(128)', 'status' => 'varchar(40)', 'stage' => 'varchar(80)', 'backup_path' => 'varchar(1024)', 'db_backup_path' => 'varchar(1024)'],
            'upgrade_manifest' => ['manifest_id' => 'varchar(96)'],
        ] as $table => $columns) foreach ($columns as $column => $type) self::assertSame($type, $snapshot['schema'][Db::connection()->getTablePrefix() . $table]['columns'][$column]['COLUMN_TYPE']);
        self::assertNotEmpty($snapshot['server_identity']['uuid'] ?? '');
        self::assertStringContainsString('CREATE TABLE', $snapshot['schema'][Db::connection()->getTablePrefix() . 'admin']['create_sql'] ?? '');
        self::assertNotEmpty($snapshot['protected_row_hashes']['admin'] ?? '');
        self::assertNotEmpty($snapshot['schema'][Db::connection()->getTablePrefix() . 'language_line']['columns']['ns']['GENERATION_EXPRESSION'] ?? '');
        $pluginCode = \app\admin\authorization\service\PermissionCode::derive(\app\admin\plugin\controller\SystemPlugin::class, 'installed');
        self::assertSame(\app\admin\authorization\service\PermissionCode::resource($pluginCode)['obj'], Db::table('permission')->where('code', $pluginCode)->whereNull('deleted_at')->where('status', 1)->value('obj'));
        $referenceMenus = \app\common\service\database\CoreSeedPlan::readSnapshot(Db::connection(), $snapshot['schema']);
        self::assertSame([], \app\common\service\database\CoreMenuPlan::diagnose($referenceMenus), '新安装也必须通过菜单原生绑定契约，不能只在接管时纠偏');
        $normalizedMenus = \app\common\service\database\CoreMenuPlan::normalizeReference($referenceMenus);
        self::assertSame([], \app\common\service\database\CoreMenuPlan::diagnose($normalizedMenus));
        foreach ([
            'admin' => ['dept_id'], 'admin_menu' => ['pid', 'permission_id'], 'department' => ['pid'],
            'dict_item' => ['type_id'], 'permission' => ['pid'], 'permission_field' => ['permission_id'],
            'auth_group_department' => ['role_id', 'dept_id'], 'auth_group_inherit' => ['role_id', 'parent_role_id'],
            'auth_group_field_permission' => ['role_id'],
        ] as $logical => $columns) {
            foreach ($columns as $column) self::assertSame('bigint unsigned', $snapshot['schema'][Db::connection()->getTablePrefix() . $logical]['columns'][$column]['COLUMN_TYPE']);
        }
        self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $snapshot)['aligned']);
        $cliReference = base_path('runtime/private/core-cli-reference-' . bin2hex(random_bytes(8)) . '.json');
        $cliPlanPath = null;
        file_put_contents($cliReference, json_encode($snapshot + ['seed_rows' => $referenceMenus], JSON_THROW_ON_ERROR));
        chmod($cliReference, 0600);
        try {
            $command = new \Symfony\Component\Console\Tester\CommandTester(new \app\admin\system\command\CoreDatabase(Db::connection()));
            self::assertSame(0, $command->execute(['action' => 'preflight', '--reference' => $cliReference]), $command->getDisplay());
            $cliResult = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
            $cliPlanPath = $cliResult['plan'];
            self::assertSame(Db::connection()->getDatabaseName(), $cliResult['database']);
            self::assertSame('incremental', $cliResult['mode']);
            self::assertFalse($cliResult['apply_allowed']);
            self::assertSame(0600, fileperms($cliPlanPath) & 0777);
            self::assertSame(1, $command->execute(['action' => 'backup', '--reference' => $cliReference, '--plan' => $cliPlanPath, '--confirm' => str_repeat('0', 64)]));
            self::assertStringContainsString('漂移', $command->getDisplay());
        } finally {
            if ($cliPlanPath !== null && is_file($cliPlanPath)) unlink($cliPlanPath);
            unlink($cliReference);
        }
        $rebuildDatabase = 'webman_console_test_' . bin2hex(random_bytes(6));
        $config = Db::connection()->getConfig();
        $control = Db::connection()->getPdo();
        $control->exec('CREATE DATABASE `' . $rebuildDatabase . '`');
        try {
            $pdo = new \PDO('mysql:host=' . $config['host'] . ';port=' . ($config['port'] ?? 3306) . ';dbname=' . $rebuildDatabase . ';charset=utf8mb4', $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $target = new \Illuminate\Database\MySqlConnection($pdo, $rebuildDatabase, 'rebuild_', ['driver' => 'mysql']);
            $before = $audit->inspect($target);
            $plan = \app\common\service\database\CoreSchemaPlan::build($snapshot, $before);
            self::assertSame([], $plan['blockers']);
            \app\common\service\database\CoreSchemaPlan::assertCurrent($plan, $snapshot, $before);
            foreach ($plan['operations'] as $operation) $target->statement($operation['sql']);
            $rebuilt = $audit->inspect($target);
            $difference = \app\common\service\database\CoreDatabaseAudit::compare($snapshot, $rebuilt);
            self::assertTrue($difference['aligned'], json_encode($difference, JSON_UNESCAPED_UNICODE));
            self::assertSame(0, $target->table('admin')->count(), '结构计划禁止偷偷复制管理员种子');
            $target->statement('ALTER TABLE `rebuild_application_assignment` ALTER CHECK `ck_application_assignment_subject` NOT ENFORCED');
            $checkDrift = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame(['CHECKS_REQUIRES_REVIEW'], array_column($checkDrift['blockers'], 'code'));
            $target->statement('ALTER TABLE `rebuild_application_assignment` ALTER CHECK `ck_application_assignment_subject` ENFORCED');
            self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $audit->inspect($target))['aligned']);
            self::assertSame(0, $target->table('system_migration')->count(), '结构接管禁止伪造历史执行');
            $target->table('admin')->insert(['id' => 7, 'username' => 'preserved_account', 'password' => 'fixture-credential-do-not-replace', 'status' => 1]);
            $preserved = (array) $target->table('admin')->first();
            $target->statement('ALTER TABLE `rebuild_admin` DROP COLUMN `real_name`');
            unset($preserved['real_name']);
            $missingColumn = $audit->inspect($target);
            $additive = \app\common\service\database\CoreSchemaPlan::build($snapshot, $missingColumn);
            self::assertSame([], $additive['blockers']);
            self::assertCount(1, $additive['operations']);
            self::assertSame('add_column', $additive['operations'][0]['kind']);
            foreach ($additive['operations'] as $operation) $target->statement($operation['sql']);
            $after = (array) $target->table('admin')->first();
            self::assertArrayHasKey('real_name', $after);
            unset($after['real_name']);
            self::assertSame($preserved, $after, '增量补列不得改变已有账号/ID/凭据');
            self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $audit->inspect($target))['aligned']);
            $target->statement('ALTER TABLE `rebuild_admin_auth_tokens` DROP INDEX `idx_admin_auth_tokens_admin`');
            $missingIndex = $audit->inspect($target);
            $indexPlan = \app\common\service\database\CoreSchemaPlan::build($snapshot, $missingIndex);
            self::assertSame([], $indexPlan['blockers']);
            self::assertCount(1, $indexPlan['operations']);
            self::assertSame('add_index', $indexPlan['operations'][0]['kind']);
            foreach ($indexPlan['operations'] as $operation) $target->statement($operation['sql']);
            self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $audit->inspect($target))['aligned']);
            $target->statement('ALTER TABLE `rebuild_dict_type` DROP INDEX `uk_dict_type_code`');
            $uniquePlan = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $uniquePlan['blockers']);
            self::assertSame([], \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $uniquePlan));
            $target->table('dict_type')->insert([['code' => 'duplicate_fixture', 'name' => '甲'], ['code' => 'duplicate_fixture', 'name' => '乙']]);
            $failures = \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $uniquePlan);
            self::assertSame('DUPLICATE_UNIQUE_ROWS', $failures[0]['code']);
            self::assertSame(2, $target->table('dict_type')->where('code', 'duplicate_fixture')->count(), '重复预检不得删除数据');
            $target->table('dict_type')->where('code', 'duplicate_fixture')->delete();
            self::assertSame([], \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $uniquePlan));
            foreach ($uniquePlan['operations'] as $operation) $target->statement($operation['sql']);
            self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $audit->inspect($target))['aligned']);
            $target->statement('ALTER TABLE `rebuild_admin` MODIFY COLUMN `real_name` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL');
            $target->table('admin')->where('id', 7)->update(['real_name' => '保留姓名']);
            $expansion = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $expansion['blockers']);
            self::assertCount(1, $expansion['operations']);
            self::assertSame('widen_column', $expansion['operations'][0]['kind']);
            foreach ($expansion['operations'] as $operation) $target->statement($operation['sql']);
            self::assertSame('保留姓名', $target->table('admin')->where('id', 7)->value('real_name'));
            $longName = str_repeat('姓名', 20);
            $target->table('admin')->where('id', 7)->update(['real_name' => $longName]);
            self::assertSame($longName, $target->table('admin')->where('id', 7)->value('real_name'));
            self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $audit->inspect($target))['aligned']);
            $target->statement('ALTER TABLE `rebuild_language_line` DROP INDEX `idx_locale_ns`, DROP COLUMN `ns`');
            $generated = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $generated['blockers']);
            self::assertSame(['add_column', 'add_index'], array_column($generated['operations'], 'kind'));
            foreach ($generated['operations'] as $operation) $target->statement($operation['sql']);
            $target->table('language_line')->insert(['locale' => 'zh-CN', 'key' => 'crud.fixture.title', 'value' => '生成列测试']);
            self::assertSame('crud.fixture', $target->table('language_line')->where('key', 'crud.fixture.title')->value('ns'));
            self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $audit->inspect($target))['aligned']);
            $target->statement('ALTER TABLE `rebuild_admin_department` DROP FOREIGN KEY `fk_admin_department_admin`');
            $foreignKey = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $foreignKey['blockers']);
            self::assertSame([], \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $foreignKey));
            $department = $target->table('department')->insertGetId(['name' => '外键测试部门']);
            $target->table('admin_department')->insert(['admin_id' => 99999, 'dept_id' => $department]);
            $orphans = \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $foreignKey);
            self::assertSame('ORPHAN_FOREIGN_KEY_ROWS', $orphans[0]['code']);
            self::assertSame(1, $target->table('admin_department')->count(), '外键预检不得清理孤儿数据');
            $target->table('admin_department')->where('admin_id', 99999)->update(['admin_id' => 7]);
            self::assertSame([], \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $foreignKey));
            foreach ($foreignKey['operations'] as $operation) $target->statement($operation['sql']);
            self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $audit->inspect($target))['aligned']);
            $target->table('admin_department')->where('admin_id', 7)->delete();
            $target->table('department')->where('id', $department)->delete();
            $target->table('admin')->where('id', 7)->update(['email' => 'preserved@example.test']);
            $target->statement('ALTER TABLE `rebuild_admin` MODIFY COLUMN `email` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\'');
            $nullable = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $nullable['blockers']);
            self::assertSame('relax_nullable_column', $nullable['operations'][0]['kind']);
            foreach ($nullable['operations'] as $operation) $target->statement($operation['sql']);
            self::assertSame('preserved@example.test', $target->table('admin')->where('id', 7)->value('email'));
            $target->table('admin')->where('id', 7)->update(['email' => null]);
            self::assertNull($target->table('admin')->where('id', 7)->value('email'));
            self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $audit->inspect($target))['aligned']);
            $target->statement('ALTER TABLE `rebuild_plugin_operation` DROP COLUMN `source`');
            $requiredColumn = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $requiredColumn['blockers']);
            self::assertSame('empty_table', $requiredColumn['preflight'][0]['kind']);
            self::assertSame([], \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $requiredColumn));
            $target->table('plugin_operation')->insert(['plugin_code' => 'fixture', 'operation' => 'install', 'package_hash' => str_repeat('a', 64), 'result' => 'success']);
            self::assertSame('TABLE_NO_LONGER_EMPTY', \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $requiredColumn)[0]['code']);
            self::assertNotEmpty(\app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target))['blockers']);
            $target->table('plugin_operation')->where('plugin_code', 'fixture')->delete();
            foreach ($requiredColumn['operations'] as $operation) $target->statement($operation['sql']);
            self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $audit->inspect($target))['aligned']);
            $target->statement('ALTER TABLE `rebuild_department` MODIFY COLUMN `pid` int unsigned NOT NULL DEFAULT \'0\'');
            $target->table('department')->insert(['name' => '引用扩容测试', 'pid' => 4000000000]);
            $unsigned = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $unsigned['blockers']);
            self::assertSame('widen_column', $unsigned['operations'][0]['kind']);
            foreach ($unsigned['operations'] as $operation) $target->statement($operation['sql']);
            self::assertSame(4000000000, (int) $target->table('department')->where('name', '引用扩容测试')->value('pid'));
            $target->table('department')->where('name', '引用扩容测试')->update(['pid' => 5000000000]);
            self::assertSame(5000000000, (int) $target->table('department')->where('name', '引用扩容测试')->value('pid'));
            $target->table('department')->where('name', '引用扩容测试')->delete();
            self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $audit->inspect($target))['aligned']);
            $target->statement("SET time_zone = '+00:00'");
            $target->table('admin')->where('id', 7)->update(['created_at' => '2026-01-02 03:04:05']);
            $target->statement('ALTER TABLE `rebuild_admin` MODIFY COLUMN `created_at` timestamp NULL DEFAULT NULL');
            $timestampPlan = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $timestampPlan['blockers']);
            self::assertSame('timestamp_to_datetime_utc', $timestampPlan['operations'][0]['kind']);
            $target->statement("SET time_zone = '+08:00'");
            self::assertSame('2026-01-02 11:04:05', $target->table('admin')->where('id', 7)->value('created_at'));
            self::assertSame('UTC_SESSION_REQUIRED', \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $timestampPlan)[0]['code']);
            $target->statement("SET time_zone = '+00:00'");
            self::assertSame([], \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $timestampPlan));
            foreach ($timestampPlan['operations'] as $operation) $target->statement($operation['sql']);
            self::assertSame('2026-01-02 03:04:05', $target->table('admin')->where('id', 7)->value('created_at'));
            self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $audit->inspect($target))['aligned']);
            $target->table('admin')->where('id', 7)->update(['avatar' => '/keep-avatar']);
            $target->statement("ALTER TABLE `rebuild_admin` MODIFY COLUMN `avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");
            $defaults = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $defaults['blockers']);
            foreach ($defaults['operations'] as $operation) $target->statement($operation['sql']);
            self::assertSame('/keep-avatar', $target->table('admin')->where('id', 7)->value('avatar'));
            $target->statement('ALTER TABLE `rebuild_admin_log` MODIFY COLUMN `url` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\', ADD KEY `admin_log_status_index` (`status`)');
            $compatibility = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $compatibility['blockers']);
            self::assertSame([], $compatibility['operations']);
            self::assertCount(2, $compatibility['reviewed_differences']);
            $target->statement('ALTER TABLE `rebuild_admin_log` MODIFY COLUMN `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\', DROP KEY `admin_log_status_index`');
            $target->statement('ALTER TABLE `rebuild_auth_group_inherit` DROP PRIMARY KEY, ADD COLUMN `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, ADD UNIQUE KEY `auth_group_inherit_role_id_parent_role_id_unique` (`role_id`, `parent_role_id`)');
            $target->table('auth_group_inherit')->insert(['role_id' => 8, 'parent_role_id' => 9]);
            $pivot = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $pivot['blockers']);
            self::assertSame([], $pivot['operations']);
            self::assertCount(3, $pivot['reviewed_differences']);
            self::assertSame(1, (int) $target->table('auth_group_inherit')->value('id'));
            $target->table('auth_group_inherit')->delete();
            $target->statement("ALTER TABLE `rebuild_plugin_operation` MODIFY COLUMN `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'running'");
            $runtimeAlignment = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $runtimeAlignment['blockers']);
            self::assertSame('empty_runtime_column_alignment', $runtimeAlignment['operations'][0]['kind']);
            self::assertSame([], \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $runtimeAlignment));
            $target->table('plugin_operation')->insert(['plugin_code' => 'keep-history', 'operation' => 'install', 'source' => 'local', 'package_hash' => str_repeat('a', 64), 'result' => 'failed']);
            self::assertSame('TABLE_NO_LONGER_EMPTY', \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $runtimeAlignment)[0]['code']);
            self::assertNotEmpty(\app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target))['blockers']);
            self::assertSame('running', $target->table('plugin_operation')->value('status'));
            $target->table('plugin_operation')->where('plugin_code', 'keep-history')->delete();
            foreach ($runtimeAlignment['operations'] as $operation) $target->statement($operation['sql']);
            self::assertSame('tinyint(1)', $audit->inspect($target)['schema']['rebuild_plugin_operation']['columns']['status']['COLUMN_TYPE']);
            $target->statement('ALTER TABLE `rebuild_admin` MODIFY COLUMN `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL');
            $target->table('admin')->where('id', 7)->update(['ip' => '非ASCII']);
            $ascii = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $ascii['blockers']);
            self::assertSame('NON_ASCII_COLUMN_VALUES', \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $ascii)[0]['code']);
            self::assertSame('非ASCII', $target->table('admin')->where('id', 7)->value('ip'));
            $target->table('admin')->where('id', 7)->update(['ip' => '2001:db8::1']);
            self::assertSame([], \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $ascii));
            foreach ($ascii['operations'] as $operation) $target->statement($operation['sql']);
            self::assertSame('2001:db8::1', $target->table('admin')->where('id', 7)->value('ip'));
            $target->statement("ALTER TABLE `rebuild_permission` MODIFY COLUMN `resource_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'route'");
            $target->table('permission')->insert(['code' => 'fixture:enum', 'name' => '保留枚举异常', 'resource_type' => 'unknown']);
            $enumPlan = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $enumPlan['blockers']);
            self::assertSame('INVALID_ENUM_VALUES', \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $enumPlan)[0]['code']);
            self::assertSame('unknown', $target->table('permission')->where('code', 'fixture:enum')->value('resource_type'));
            $target->table('permission')->where('code', 'fixture:enum')->update(['resource_type' => 'capability']);
            self::assertSame([], \app\common\service\database\CoreSchemaPlan::checkPreflight($target, $enumPlan));
            foreach ($enumPlan['operations'] as $operation) $target->statement($operation['sql']);
            self::assertSame('capability', $target->table('permission')->where('code', 'fixture:enum')->value('resource_type'));
            $target->table('permission')->where('code', 'fixture:enum')->delete();
            $target->statement("ALTER TABLE `rebuild_admin_menu` MODIFY COLUMN `sort_order` int unsigned NOT NULL DEFAULT '999'");
            $target->table('admin_menu')->insert(['name' => '数值域测试', 'href' => '/numeric-fixture', 'sort_order' => 4000000000]);
            $numeric = \app\common\service\database\CoreSchemaPlan::build($snapshot, $audit->inspect($target));
            self::assertSame([], $numeric['blockers']);
            self::assertSame('widen_numeric_domain', $numeric['operations'][0]['kind']);
            foreach ($numeric['operations'] as $operation) $target->statement($operation['sql']);
            self::assertSame(4000000000, (int) $target->table('admin_menu')->where('href', '/numeric-fixture')->value('sort_order'));
            $target->table('admin_menu')->where('href', '/numeric-fixture')->update(['sort_order' => -100]);
            self::assertSame(-100, (int) $target->table('admin_menu')->where('href', '/numeric-fixture')->value('sort_order'));
            $target->table('admin_menu')->where('href', '/numeric-fixture')->delete();
            self::assertTrue(method_exists(\app\common\service\database\CoreSeedPlan::class, 'readSnapshot'), '缺少真实回放种子提取');
            $referenceSeeds = \app\common\service\database\CoreSeedPlan::readSnapshot(Db::connection(), $snapshot['schema']);
            self::assertArrayNotHasKey('admin', $referenceSeeds);
            self::assertArrayNotHasKey('casbin_rule', $referenceSeeds);
            self::assertArrayNotHasKey('identity_credential', $referenceSeeds);
            self::assertArrayNotHasKey('ns', $referenceSeeds['language_line'][0], '生成列不得作为插入字段');
            $firstType = $referenceSeeds['dict_type'][0];
            $target->table('dict_type')->insert(['id' => 500, 'code' => $firstType['code'], 'name' => '保留自定义字典', 'status' => 0]);
            $rootUserMenu = null;
            foreach ($referenceSeeds['admin_menu'] as $candidate) {
                parse_str($candidate['query'], $meta);
                if (($meta['component'] ?? '') === 'system/user/index') { $rootUserMenu = $candidate; break; }
            }
            self::assertNotNull($rootUserMenu);
            $target->table('admin_menu')->insert(['id' => 500, 'pid' => 0, 'permission_id' => 0, 'app_name' => 'admin', 'name' => '已有管理员页', 'href' => '/admin/system/user', 'query' => 'name=SystemAdmin&component=system%2Fuser%2Findex', 'source_type' => 'admin_web', 'source_name' => 'authorization', 'status' => 1]);
            $actualSeeds = \app\common\service\database\CoreSeedPlan::readSnapshot($target, $audit->inspect($target)['schema']);
            $seedPlan = \app\common\service\database\CoreSeedPlan::build($referenceSeeds, $actualSeeds);
            self::assertSame(500, $seedPlan['id_map']['dict_type'][$firstType['id']]);
            self::assertSame(500, $seedPlan['id_map']['admin_menu'][$rootUserMenu['id']]);
            $seedIssues = [];
            foreach ($seedPlan['blockers'] as $issue) {
                $rows = array_column($referenceSeeds[$issue['table']], null, 'id');
                $row = $rows[$issue['id']] ?? [];
                $seedIssues[] = $issue + ['pid' => $row['pid'] ?? null, 'name' => $row['name'] ?? null];
            }
            self::assertSame([], $seedPlan['blockers'], json_encode($seedIssues, JSON_UNESCAPED_UNICODE));
            $target->transaction(function () use ($target, $referenceSeeds, $seedPlan): void {
                \app\common\service\database\CoreSeedPlan::apply($target, $referenceSeeds, $seedPlan);
            });
            self::assertSame('fixture-credential-do-not-replace', $target->table('admin')->where('id', 7)->value('password'));
            self::assertSame('保留自定义字典', $target->table('dict_type')->where('id', 500)->value('name'));
            self::assertSame(0, (int) $target->table('dict_type')->where('id', 500)->value('status'));
            self::assertSame('已有管理员页', $target->table('admin_menu')->where('id', 500)->value('name'));
            self::assertSame(1, $target->table('admin_menu')->where('query', 'like', '%system%2Fuser%2Findex%')->count());
            self::assertSame(2, $target->table('dict_item')->where('type_id', 500)->count());
            $repeatSeeds = \app\common\service\database\CoreSeedPlan::readSnapshot($target, $audit->inspect($target)['schema']);
            $repeat = \app\common\service\database\CoreSeedPlan::build($referenceSeeds, $repeatSeeds);
            self::assertSame([], $repeat['blockers']);
            self::assertSame([], $repeat['inserts'], '种子接管必须幂等');
            self::assertSame(0, $target->table('casbin_rule')->count(), '不能从基线授予角色权限');
            self::assertSame(0, $target->table('system_migration')->count(), '种子接管不能伪造历史迁移');
            $menuPlan = \app\common\service\database\CoreMenuPlan::build($referenceSeeds, $repeatSeeds);
            self::assertSame([], $menuPlan['blockers']);
            self::assertNotEmpty($menuPlan['updates']);
            $casbinBefore = $target->table('casbin_rule')->count();
            $target->transaction(function () use ($target, $referenceSeeds, $menuPlan): void {
                try {
                    $tampered = $menuPlan;
                    $tampered['updates'][0]['values']['href'] = '/tampered';
                    \app\common\service\database\CoreMenuPlan::apply($target, $referenceSeeds, $tampered);
                    self::fail('篡改菜单计划必须拒绝');
                } catch (\RuntimeException $error) { self::assertStringContainsString('漂移', $error->getMessage()); }
            });
            try {
                $target->transaction(function () use ($target, $referenceSeeds, $menuPlan): void {
                    \app\common\service\database\CoreMenuPlan::apply($target, $referenceSeeds, $menuPlan);
                    throw new \RuntimeException('menu rollback fixture');
                });
            } catch (\RuntimeException $error) { self::assertSame('menu rollback fixture', $error->getMessage()); }
            self::assertSame($menuPlan['digest'], \app\common\service\database\CoreMenuPlan::build($referenceSeeds, \app\common\service\database\CoreSeedPlan::readSnapshot($target, $audit->inspect($target)['schema']))['digest']);
            $target->transaction(fn () => \app\common\service\database\CoreMenuPlan::apply($target, $referenceSeeds, $menuPlan));
            $menuAfter = \app\common\service\database\CoreSeedPlan::readSnapshot($target, $audit->inspect($target)['schema']);
            self::assertSame([], \app\common\service\database\CoreMenuPlan::build($referenceSeeds, $menuAfter)['updates']);
            self::assertSame([], \app\common\service\database\CoreMenuPlan::diagnose($menuAfter));
            self::assertSame('已有管理员页', $target->table('admin_menu')->where('id', 500)->value('name'));
            self::assertSame($casbinBefore, $target->table('casbin_rule')->count());
            self::assertSame('fixture-credential-do-not-replace', $target->table('admin')->where('id', 7)->value('password'));
            $adoptionReference = $snapshot + ['seed_rows' => $referenceSeeds];
            $adoptionReference['seed_rows']['dict_type'][] = ['id' => 999998, 'code' => 'adoption_transaction_fixture', 'name' => '接管事务种子', 'status' => 1];
            $target->statement('ALTER TABLE `rebuild_admin` DROP COLUMN `real_name`');
            $target->statement('ALTER TABLE `rebuild_admin_auth_tokens` DROP INDEX `idx_admin_auth_tokens_admin`');
            $target->statement('DROP TABLE `rebuild_core_database_adoption`');
            $target->statement('DROP TABLE `rebuild_login_attempts`');
            $target->statement('ALTER TABLE `rebuild_oauth_token_scope` DROP FOREIGN KEY `fk_oauth_token_scope_token`');
            $target->statement('ALTER TABLE `rebuild_oauth_token` DROP FOREIGN KEY `fk_oauth_token_parent`');
            $target->statement('DROP TABLE `rebuild_oauth_token`');
            $adoptionSnapshot = $audit->inspect($target);
            $adoptionPlan = \app\common\service\database\CoreAdoptionPlan::build($adoptionReference, $adoptionSnapshot, $menuAfter);
            self::assertSame('adopt', $adoptionPlan['mode']);
            self::assertSame([], $adoptionPlan['blockers']);
            self::assertSame([], $adoptionPlan['executed_versions']);
            $backupRoot = sys_get_temp_dir() . '/core-adoption-backup-' . bin2hex(random_bytes(8));
            mkdir($backupRoot, 0700);
            try {
                $backupService = new \app\common\service\database\CoreAdoptionBackup($backupRoot);
                $receipt = $backupService->capture($target, $adoptionReference, $adoptionPlan, $adoptionPlan['digest']);
                $evidence = $backupService->verify($receipt, $adoptionPlan['digest'], $adoptionPlan['target_digest']);
                self::assertSame($rebuildDatabase, $evidence['database']);
                self::assertSame($adoptionSnapshot['server_identity'], $evidence['server_identity']);
                self::assertArrayHasKey('admin_menu', $evidence['metadata']);
                self::assertArrayHasKey('casbin_rule', $evidence['metadata']);
                self::assertArrayNotHasKey('admin', $evidence['metadata'], '元数据备份不得复制管理员凭据');
                self::assertStringNotContainsString('fixture-credential-do-not-replace', file_get_contents($receipt['path']));
                self::assertFalse($target->getSchemaBuilder()->hasTable('core_database_adoption'), '备份阶段不得提前建接管表');
                $executor = new \app\common\service\database\CoreAdoptionService($backupService);
                try {
                    $executor->apply($target, $adoptionReference, $adoptionPlan, str_repeat('0', 64), $receipt, true);
                    self::fail('错误确认摘要不得应用');
                } catch (\RuntimeException $error) { self::assertStringContainsString('漂移', $error->getMessage()); }
                self::assertFalse($target->getSchemaBuilder()->hasTable('core_database_adoption'));
                try {
                    $executor->apply($target, $adoptionReference, $adoptionPlan, $adoptionPlan['digest'], $receipt, false);
                    self::fail('未进入维护窗口不得应用');
                } catch (\RuntimeException $error) { self::assertStringContainsString('维护窗口', $error->getMessage()); }
                $lockStatement = $control->prepare('SELECT GET_LOCK(?, 0)');
                $adoptionLock = 'migration:' . substr(hash('sha256', $rebuildDatabase), 0, 40);
                $lockStatement->execute([$adoptionLock]);
                self::assertSame(1, (int) $lockStatement->fetchColumn());
                try {
                    $executor->apply($target, $adoptionReference, $adoptionPlan, $adoptionPlan['digest'], $receipt, true);
                    self::fail('竞争锁必须在登记接管和插入种子前拒绝');
                } catch (\RuntimeException $error) { self::assertSame('数据库迁移正在执行', $error->getMessage()); }
                finally { $release = $control->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$adoptionLock]); }
                self::assertSame(0, $target->table('dict_type')->where('code', 'adoption_transaction_fixture')->count());
                self::assertFalse($target->getSchemaBuilder()->hasTable('core_database_adoption'));
                $target->table('dict_type')->where('id', 500)->update(['name' => '应用前漂移']);
                try {
                    $executor->apply($target, $adoptionReference, $adoptionPlan, $adoptionPlan['digest'], $receipt, true);
                    self::fail('目标数据漂移必须拒绝');
                } catch (\RuntimeException $error) { self::assertStringContainsString('漂移', $error->getMessage()); }
                self::assertNull($target->selectOne('SELECT IS_USED_LOCK(?) owner', [$adoptionLock])->owner);
                $target->table('dict_type')->where('id', 500)->update(['name' => '保留自定义字典']);
                $ddlCheckpoints = 0;
                $interrupted = new \app\common\service\database\CoreAdoptionService($backupService, static function (string $point) use (&$ddlCheckpoints): void {
                    if ($point === 'ddl_checkpoint' && ++$ddlCheckpoints === 3) throw new \RuntimeException('DDL checkpoint interruption');
                });
                try {
                    $interrupted->apply($target, $adoptionReference, $adoptionPlan, $adoptionPlan['digest'], $receipt, true);
                    self::fail('故障应保留结构阶段证据');
                } catch (\RuntimeException $error) { self::assertSame('DDL checkpoint interruption', $error->getMessage()); }
                self::assertArrayHasKey('real_name', $audit->inspect($target)['schema']['rebuild_admin']['columns']);
                self::assertSame(0, $target->table('dict_type')->where('code', 'adoption_transaction_fixture')->count());
                self::assertSame('structure', $target->table('core_database_adoption')->value('stage'));
                self::assertNull($target->selectOne('SELECT IS_USED_LOCK(?) owner', [$adoptionLock])->owner);
                $target->table('dict_type')->where('id', 500)->update(['name' => '结构阶段后漂移']);
                try {
                    $executor->apply($target, $adoptionReference, $adoptionPlan, $adoptionPlan['digest'], $receipt, true);
                    self::fail('续跑不得忽略阶段后的数据漂移');
                } catch (\RuntimeException $error) { self::assertStringContainsString('漂移', $error->getMessage()); }
                $target->table('dict_type')->where('id', 500)->update(['name' => '保留自定义字典']);
                $changedReplay = $adoptionReference;
                $changedReplay['seed_rows']['dict_type'][] = ['id' => 999997, 'code' => 'must-not-import', 'name' => '伪造规范源'];
                try {
                    $executor->apply($target, $changedReplay, $adoptionPlan, $adoptionPlan['digest'], $receipt, true);
                    self::fail('续跑规范回放报告漂移必须拒绝');
                } catch (\RuntimeException $error) { self::assertStringContainsString('漂移', $error->getMessage()); }
                $metadataFailure = new \app\common\service\database\CoreAdoptionService($backupService, static function (string $point): void {
                    if ($point === 'metadata_applied') throw new \RuntimeException('metadata interruption');
                });
                try {
                    $metadataFailure->apply($target, $adoptionReference, $adoptionPlan, $adoptionPlan['digest'], $receipt, true);
                    self::fail('元数据故障必须回滚');
                } catch (\RuntimeException $error) { self::assertSame('metadata interruption', $error->getMessage()); }
                self::assertSame(0, $target->table('dict_type')->where('code', 'adoption_transaction_fixture')->count());
                self::assertSame('structure', $target->table('core_database_adoption')->value('stage'));
                self::assertArrayHasKey('idx_admin_auth_tokens_admin', $audit->inspect($target)['schema']['rebuild_admin_auth_tokens']['indexes']);
                $adopted = $executor->apply($target, $adoptionReference, $adoptionPlan, $adoptionPlan['digest'], $receipt, true);
                self::assertSame('complete', $adopted['stage']);
                self::assertTrue($target->getSchemaBuilder()->hasTable('login_attempts'));
                self::assertSame(1, $target->table('dict_type')->where('code', 'adoption_transaction_fixture')->count());
                self::assertSame(1, $target->table('core_database_adoption')->count());
                self::assertSame(0, $target->table('system_migration')->count(), '独立接管不能伪造任何已执行核心版本');
                self::assertSame('fixture-credential-do-not-replace', $target->table('admin')->where('id', 7)->value('password'));
                self::assertSame([], \app\common\service\database\CoreMenuPlan::diagnose(\app\common\service\database\CoreSeedPlan::readSnapshot($target, $audit->inspect($target)['schema'])));
                self::assertSame($adopted, $executor->apply($target, $adoptionReference, $adoptionPlan, $adoptionPlan['digest'], $receipt, true));
                $manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
                $previousDefault = $manager->getDefaultConnection();
                $managerConfig = \Illuminate\Container\Container::getInstance()['config'];
                $connections = $managerConfig['database.connections'];
                $connections['core_adopted_fixture'] = array_replace($config, ['database' => $rebuildDatabase, 'prefix' => 'rebuild_']);
                $managerConfig['database.connections'] = $connections;
                $manager->setDefaultConnection('core_adopted_fixture');
                try {
                    self::assertSame([], (new MigrationService())->runCore(), '接管库后续迁移不得重放基线');
                    self::assertSame(0, Db::table('system_migration')->count());
                } finally {
                    $manager->setDefaultConnection($previousDefault);
                    $manager->purge('core_adopted_fixture');
                }
                $target->table('dict_type')->where('id', 500)->update(['name' => '完成后种子漂移']);
                try {
                    $executor->apply($target, $adoptionReference, $adoptionPlan, $adoptionPlan['digest'], $receipt, true);
                    self::fail('完成后种子变更不得被重复应用覆盖');
                } catch (\RuntimeException $error) { self::assertStringContainsString('漂移', $error->getMessage()); }
                self::assertSame('完成后种子漂移', $target->table('dict_type')->where('id', 500)->value('name'));
                $target->table('dict_type')->where('id', 500)->update(['name' => '保留自定义字典']);
                $target->statement('ALTER TABLE `rebuild_admin` DROP COLUMN `real_name`');
                $pendingSnapshot = $audit->inspect($target);
                $pendingRows = \app\common\service\database\CoreSeedPlan::readSnapshot($target, $pendingSnapshot['schema']);
                $pendingPlan = \app\common\service\database\CoreAdoptionPlan::build($adoptionReference, $pendingSnapshot, $pendingRows);
                $pendingReceipt = $backupService->capture($target, $adoptionReference, $pendingPlan, $pendingPlan['digest']);
                $crash = new \app\common\service\database\CoreAdoptionService($backupService, static function (string $point): void {
                    if ($point === 'ddl_applied') throw new \RuntimeException('uncheckpointed DDL interruption');
                });
                try {
                    $crash->apply($target, $adoptionReference, $pendingPlan, $pendingPlan['digest'], $pendingReceipt, true);
                    self::fail('DDL 后检查点前故障必须保留 pending');
                } catch (\RuntimeException $error) { self::assertSame('uncheckpointed DDL interruption', $error->getMessage()); }
                $pending = json_decode($target->table('core_database_adoption')->where('plan_digest', $pendingPlan['digest'])->value('evidence'), true, 512, JSON_THROW_ON_ERROR);
                self::assertNotNull($pending['pending']);
                self::assertArrayHasKey('real_name', $audit->inspect($target)['schema']['rebuild_admin']['columns']);
                try {
                    $executor->apply($target, $adoptionReference, $pendingPlan, $pendingPlan['digest'], $pendingReceipt, true);
                    self::fail('未明确完成的 DDL 不得自动重放');
                } catch (\RuntimeException $error) { self::assertStringContainsString('pending', $error->getMessage()); }
                self::assertNull($target->selectOne('SELECT IS_USED_LOCK(?) owner', [$adoptionLock])->owner);
            } finally {
                foreach (glob($backupRoot . '/*') as $file) unlink($file);
                rmdir($backupRoot);
            }
            $menuAfter = \app\common\service\database\CoreSeedPlan::readSnapshot($target, $audit->inspect($target)['schema']);
            $repeatSeeds = $menuAfter;
            $changedReference = $referenceSeeds;
            $changedReference['dict_type'][] = ['id' => 999999, 'code' => 'rollback_fixture', 'name' => '回滚测试', 'status' => 1];
            $rollbackPlan = \app\common\service\database\CoreSeedPlan::build($changedReference, $repeatSeeds);
            $countBefore = $target->table('dict_type')->count();
            try {
                $target->transaction(function () use ($target, $changedReference, $rollbackPlan): void {
                    \app\common\service\database\CoreSeedPlan::apply($target, $changedReference, $rollbackPlan);
                    throw new \RuntimeException('fixture rollback after inserts');
                });
                self::fail('故障应回滚');
            } catch (\RuntimeException $error) {
                self::assertSame('fixture rollback after inserts', $error->getMessage());
                self::assertSame($countBefore, $target->table('dict_type')->count());
            }
            $target->table('dict_type')->where('id', $target->table('dict_type')->min('id'))->update(['name' => '保留人工编辑']);
            try {
                $target->transaction(fn () => \app\common\service\database\CoreSeedPlan::apply($target, $changedReference, $rollbackPlan));
                self::fail('旧种子计划必须拒绝同数量数据漂移');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('漂移', $error->getMessage());
                self::assertSame($countBefore, $target->table('dict_type')->count());
            }
        } finally {
            unset($target, $pdo);
            $control->exec('DROP DATABASE `' . $rebuildDatabase . '`');
        }
        self::assertFalse(Db::schema()->hasTable('migrations'), '根 SQL 不能通过 PHP fixture 入口创建登记表');
        $reportPath = getenv('CORE_REFERENCE_REPORT_PATH');
        if (is_string($reportPath) && $reportPath !== '') {
            $root = realpath(base_path('runtime/private'));
            if ($root === false || realpath(dirname($reportPath)) !== $root || is_link($reportPath)) throw new \RuntimeException('规范结构报告只允许私有 runtime 普通文件');
            $report = (new \app\common\service\database\CoreDatabaseAudit($directory))->inspect(Db::connection());
            unset($report['menus']);
            $report['seed_rows'] = \app\common\service\database\CoreSeedPlan::readSnapshot(Db::connection(), $report['schema']);
            $handle = fopen($reportPath, 'x');
            if ($handle === false) throw new \RuntimeException('规范结构报告已存在或不可写');
            try {
                if (!chmod($reportPath, 0600) || fwrite($handle, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) throw new \RuntimeException('无法保存规范结构报告');
            } finally { fclose($handle); }
        }
        $upgradeRoot = sys_get_temp_dir() . '/core-upgrade-' . bin2hex(random_bytes(8));
        mkdir($upgradeRoot, 0700);
        try {
            $keys = sodium_crypto_sign_keypair();
            $signer = new \app\admin\system\service\UpgradeManifestVerifier(base64_encode(sodium_crypto_sign_publickey($keys)));
            $files = ['app/core-version.php' => hash('sha256', '<?php return "core-fixture";')];
            $checksums = $audit->manifest()['checksums'];
            foreach ($checksums as $name => $hash) $files['database/core/migrations/' . $name] = $hash;
            $manifest = ['version' => '99.0.0', 'files' => $files, 'coreMigrations' => $checksums];
            $manifest['signature'] = ['algorithm' => 'ed25519', 'value' => base64_encode(sodium_crypto_sign_detached($signer->canonicalPayload($manifest), sodium_crypto_sign_secretkey($keys)))];
            $archive = $upgradeRoot . '/release.zip';
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($archive, \ZipArchive::CREATE));
            $zip->addFromString('app/core-version.php', '<?php return "core-fixture";');
            foreach ($checksums as $name => $hash) $zip->addFile($directory . '/' . $name, 'database/core/migrations/' . $name);
            $zip->addFromString('upgrade.json', json_encode($manifest, JSON_THROW_ON_ERROR));
            $zip->close();
            $beforeUpgrade = $audit->inspect(Db::connection());
            $upgrade = new \app\admin\system\service\UpgradeService(projectRoot: $upgradeRoot, runtimeRoot: $upgradeRoot . '/runtime', manifestVerifier: $signer);
            $result = $upgrade->upload($archive, 'core-upgrade-' . bin2hex(random_bytes(12)));
            self::assertSame('success', $result['status']);
            self::assertSame([], $result['metadata']['migrations']);
            self::assertSame($checksums, $result['metadata']['coreMigrations']);
            self::assertSame(['app/core-version.php'], $result['metadata']['files']);
            self::assertFileExists($upgradeRoot . '/app/core-version.php');
            self::assertDirectoryDoesNotExist($upgradeRoot . '/database', '只读根 SQL 禁止复制进 Webman 本地 migration 目录');
            self::assertSame($beforeUpgrade['protected_row_hashes'], $audit->inspect(Db::connection())['protected_row_hashes']);
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($upgradeRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($upgradeRoot);
        }
        self::assertFalse(Db::table('admin_menu')->whereNull('deleted_at')->where('href', '/admin/management')->exists(), '原型后台管理菜单不得出现在基线中');
        foreach (['provinces', 'region'] as $geography) {
            self::assertSame(13, Db::table($geography)->whereBetween('id', [839001, 839013])->where('pid', 830000)->count());
            self::assertFalse(Db::table($geography)->where('id', 839000)->exists());
        }
        Db::table('system_migration')->insert(['scope' => 'core', 'version' => '002_missing_fixture', 'checksum' => str_repeat('0', 64), 'executed_at' => time()]);
        try {
            $service->runCore();
            self::fail('核心已登记版本缺失必须阻断，禁止无待执行项假成功');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('已登记的 migration 文件缺失', $error->getMessage());
        } finally {
            Db::table('system_migration')->where('version', '002_missing_fixture')->delete();
        }
        Db::table('system_migration')->where('version', '001_baseline')->delete();
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('仅允许空库');
            $service->runCore();
        } finally {
            self::assertSame(1, Db::table('admin')->count(), '非空库拒绝不得覆盖管理员');
        }
    }
}
