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
        self::assertTrue(Db::schema()->hasTable('ai_task'));
        foreach (['identity_tenant', 'identity_user', 'oauth_client', 'application_assignment', 'scope'] as $table) self::assertFalse(Db::schema()->hasTable($table), '身份模块已移除：' . $table);
        self::assertTrue(Db::schema()->hasTable('plugin'));
        self::assertTrue(Db::schema()->hasColumn('plugin', 'composer_conflict_ack'));
        foreach (['market_token', 'market_category', 'market_plugin', 'market_version', 'market_grant', 'market_order', 'market_payment_log', 'market_site', 'market_site_plugin', 'market_install_event', 'market_download'] as $table) self::assertFalse(Db::schema()->hasTable($table), '本地市场已移除：' . $table);
        self::assertFalse(Db::table('permission')->where('source_name', 'market')->exists(), '基线不得携带已移除市场的权限');
        self::assertFalse(Db::table('permission')->where('status', 0)->exists(), '基线不得携带停用权限墓碑行');
        require_once base_path('app/functions.php');
        Db::beginTransaction();
        try {
            $request = new \support\Request("POST /api/v2/member HTTP/1.1\r\n\r\n");
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
        $this->assertSoftDeleteSchema();
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
        foreach ([
            'admin' => ['dept_id'], 'admin_menu' => ['pid', 'permission_id'], 'department' => ['pid'],
            'dict_item' => ['type_id'], 'permission' => ['pid'], 'permission_field' => ['permission_id'],
            'auth_group_department' => ['role_id', 'dept_id'], 'auth_group_inherit' => ['role_id', 'parent_role_id'],
            'auth_group_field_permission' => ['role_id'],
        ] as $logical => $columns) {
            foreach ($columns as $column) self::assertSame('bigint unsigned', $snapshot['schema'][Db::connection()->getTablePrefix() . $logical]['columns'][$column]['COLUMN_TYPE']);
        }
        self::assertTrue(\app\common\service\database\CoreDatabaseAudit::compare($snapshot, $snapshot)['aligned']);
        self::assertFalse(Db::schema()->hasTable('migrations'), '根 SQL 不能通过 PHP fixture 入口创建登记表');
        $reportPath = getenv('CORE_REFERENCE_REPORT_PATH');
        if (is_string($reportPath) && $reportPath !== '') {
            $root = realpath(base_path('runtime/private'));
            if ($root === false || realpath(dirname($reportPath)) !== $root || is_link($reportPath)) throw new \RuntimeException('规范结构报告只允许私有 runtime 普通文件');
            $report = (new \app\common\service\database\CoreDatabaseAudit($directory))->inspect(Db::connection());
            unset($report['menus']);
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

    /** 005：每张核心表有 deleted_at（带前导索引）；id 主键表有 deleted_token，非主键唯一索引以它结尾；重放 005 结构不变并回填已删除行。 */
    private function assertSoftDeleteSchema(): void
    {
        $prefix = Db::connection()->getTablePrefix();
        $database = Db::connection()->getDatabaseName();
        $tables = [];
        foreach (glob(MigrationService::coreDirectory() . '/*.sql') as $file) {
            preg_match_all('/^CREATE TABLE (?:IF NOT EXISTS )?`fun_([a-z0-9_]+)`/m', (string) file_get_contents($file), $matches);
            array_push($tables, ...$matches[1]);
        }
        self::assertCount(66, $tables);
        $snapshot = static function () use ($tables, $prefix): array {
            $result = [];
            foreach ($tables as $table) $result[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', (string) ((array) Db::connection()->selectOne('SHOW CREATE TABLE `' . $prefix . $table . '`'))['Create Table']);
            return $result;
        };
        foreach ($tables as $table) {
            $physical = $prefix . $table;
            $columns = [];
            foreach (Db::select('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$database, $physical]) as $row) $columns[$row->COLUMN_NAME] = $row;
            self::assertSame('datetime', $columns['deleted_at']->COLUMN_TYPE ?? null, $table);
            self::assertSame('YES', $columns['deleted_at']->IS_NULLABLE, $table);
            $indexes = [];
            foreach (Db::select('SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$database, $physical]) as $row) {
                $indexes[$row->INDEX_NAME]['unique'] = (int) $row->NON_UNIQUE === 0;
                $indexes[$row->INDEX_NAME]['columns'][] = $row->COLUMN_NAME;
            }
            self::assertNotSame([], array_filter($indexes, static fn (array $index): bool => $index['columns'][0] === 'deleted_at'), $table . ' deleted_at 缺索引');
            if (($indexes['PRIMARY']['columns'] ?? []) === ['id']) {
                self::assertSame(['bigint unsigned', 'NO', '0'], [$columns['deleted_token']->COLUMN_TYPE ?? null, $columns['deleted_token']->IS_NULLABLE ?? null, $columns['deleted_token']->COLUMN_DEFAULT ?? null], $table);
            } else {
                self::assertArrayNotHasKey('deleted_token', $columns, $table);
            }
            foreach ($indexes as $name => $index) {
                if ($name === 'PRIMARY' || !$index['unique']) continue;
                self::assertSame('deleted_token', end($index['columns']), $table . '.' . $name);
            }
            if ($table === 'member') foreach (['username', 'mobile', 'email'] as $plain) self::assertArrayNotHasKey($plain, $indexes);
            if ($table === 'member_level') self::assertArrayNotHasKey('id', $indexes);
        }

        // 重放 005：结构不变；已软删除但 token 仍为 0 的行回填为自身 id，未删除行保持 0。
        $now = date('Y-m-d H:i:s');
        $live = (int) Db::table('config_group')->insertGetId(['name' => 'soft_live', 'title' => 'live', 'created_at' => $now, 'updated_at' => $now]);
        $gone = (int) Db::table('config_group')->insertGetId(['name' => 'soft_gone', 'title' => 'gone', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $now]);
        try {
            $before = $snapshot();
            Db::table('system_migration')->where('scope', 'core')->where('version', '005_soft_delete')->delete();
            self::assertSame(['005_soft_delete'], (new MigrationService())->runCore());
            self::assertEquals($before, $snapshot(), '重放 005 结构不变');
            self::assertSame(0, (int) Db::table('config_group')->where('id', $live)->value('deleted_token'));
            self::assertSame($gone, (int) Db::table('config_group')->where('id', $gone)->value('deleted_token'));
            Db::table('config_group')->insert(['name' => 'soft_gone', 'title' => 'again', 'created_at' => $now, 'updated_at' => $now]);
            try {
                Db::table('config_group')->insert(['name' => 'soft_gone', 'title' => 'dup', 'created_at' => $now, 'updated_at' => $now]);
                self::fail('未删除行之间仍须唯一');
            } catch (\Illuminate\Database\QueryException $error) {
                self::assertSame(1062, (int) ($error->errorInfo[1] ?? 0));
            }
        } finally {
            Db::table('config_group')->whereIn('name', ['soft_live', 'soft_gone'])->delete();
        }
    }
}
