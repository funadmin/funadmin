<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\controller\SystemConfig;
use app\common\form\action\ListButtonExecutor;
use app\common\form\registry\FormRegistryFactory;
use app\common\service\database\MigrationService;
use app\common\service\security\SystemSecretStore;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

/** 列表动作二次确认密钥：首次使用时自动生成并持久化到 {前缀}system_secret，此后复用；不经配置接口暴露。 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FormListConfirmationSecretTest extends TestCase
{
    private string $database = '';

    protected function tearDown(): void
    {
        FormRegistryFactory::forgetCachedSecrets();
        if ($this->database !== '' && is_file($this->database)) unlink($this->database);
        Context::destroy();
    }

    public function test_secret_is_generated_once_persisted_and_reused_across_factory_builds(): void
    {
        $pdo = $this->storeDatabase();
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM fun_system_secret')->fetchColumn());

        $first = $this->secretOf((new FormRegistryFactory($this->config()))->listExecutor());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $first);
        $second = $this->secretOf((new FormRegistryFactory($this->config()))->listExecutor());
        self::assertSame($first, $second);

        // 模拟进程重启：清空进程缓存后仍从数据库读回同一密钥，不再生成。
        FormRegistryFactory::forgetCachedSecrets();
        self::assertSame($first, $this->secretOf((new FormRegistryFactory($this->config()))->listExecutor()));
        $rows = $pdo->query('SELECT name, value FROM fun_system_secret')->fetchAll(\PDO::FETCH_ASSOC);
        self::assertSame([['name' => SystemSecretStore::FORM_LIST_CONFIRMATION, 'value' => $first]], $rows);
    }

    public function test_concurrent_insert_loses_race_and_returns_the_existing_value(): void
    {
        $pdo = $this->storeDatabase();
        $winner = str_repeat('a', 64);
        $store = new SystemSecretStore($pdo, 'fun_system_secret', static function () use ($pdo, $winner): string {
            // 另一个 worker 在本进程读空后、插入前抢先写入。
            $pdo->prepare('INSERT INTO fun_system_secret (name, value, created_at) VALUES (?, ?, ?)')
                ->execute([SystemSecretStore::FORM_LIST_CONFIRMATION, $winner, date('Y-m-d H:i:s')]);
            return str_repeat('b', 64);
        });
        self::assertSame($winner, $store->get(SystemSecretStore::FORM_LIST_CONFIRMATION));
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM fun_system_secret')->fetchColumn());
    }

    public function test_missing_secret_table_is_reported_as_unavailable_without_leaking_details(): void
    {
        $this->database = sys_get_temp_dir() . '/funadmin-secret-' . bin2hex(random_bytes(6)) . '.sqlite';
        (new \PDO('sqlite:' . $this->database))->exec('CREATE TABLE fun_form_list_action_execution (id INTEGER PRIMARY KEY AUTOINCREMENT, scope VARCHAR(64) NOT NULL, digest VARCHAR(64) NOT NULL, state VARCHAR(16) NOT NULL, result TEXT, deleted_at DATETIME NULL, deleted_token INTEGER NOT NULL DEFAULT 0, UNIQUE (scope, deleted_token))');
        $this->expectExceptionMessage('FORM_LIST_CONFIRMATION_UNAVAILABLE');
        (new FormRegistryFactory($this->config()))->listExecutor();
    }

    public function test_migrations_create_the_secret_table_and_core_sql_is_forward_only(): void
    {
        (new MigrationService())->run();
        self::assertTrue(Db::schema()->hasTable('system_secret'));
        self::assertTrue(Db::table('migrations')->where('name', '003_system_secret.php')->exists());
        $file = base_path() . '/database/core/migrations/003_system_secret.sql';
        $sql = (string) file_get_contents($file);
        (new \ReflectionMethod(MigrationService::class, 'assertForwardOnly'))->invoke(new MigrationService(), $sql, $file);
        self::assertMatchesRegularExpression('/CREATE TABLE IF NOT EXISTS `fun_system_secret`/', $sql);
        self::assertMatchesRegularExpression('/PRIMARY KEY \(`name`\)/', $sql);
    }

    public function test_secret_is_never_exposed_by_system_config_endpoints(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        try {
            $table = Db::connection()->getTablePrefix() . 'system_secret';
            $secret = (new SystemSecretStore(Db::connection()->getPdo(), $table))->get(SystemSecretStore::FORM_LIST_CONFIRMATION);
            $outputs = [
                $this->controller('?pageSize=100')->index()->rawBody(),
                $this->controller('?keyword=form_list')->index()->rawBody(),
                $this->controller()->options()->rawBody(),
                $this->controller()->groups()->rawBody(),
            ];
            foreach (Db::table('config')->pluck('id') as $id) $outputs[] = $this->controller()->detail((int) $id)->rawBody();
            self::assertGreaterThan(4, count($outputs));
            foreach ($outputs as $output) {
                self::assertStringNotContainsString($secret, $output);
                self::assertStringNotContainsString(SystemSecretStore::FORM_LIST_CONFIRMATION, $output);
            }
            self::assertFalse(Db::table('config')->where('value', $secret)->exists());
        } finally {
            Db::rollBack();
        }
    }

    private function controller(string $query = ''): SystemConfig
    {
        $request = new Request("GET /admin/system/config$query HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        return new SystemConfig();
    }

    private function storeDatabase(): \PDO
    {
        $this->database = sys_get_temp_dir() . '/funadmin-secret-' . bin2hex(random_bytes(6)) . '.sqlite';
        $pdo = new \PDO('sqlite:' . $this->database, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE fun_form_list_action_execution (id INTEGER PRIMARY KEY AUTOINCREMENT, scope VARCHAR(64) NOT NULL, digest VARCHAR(64) NOT NULL, state VARCHAR(16) NOT NULL, result TEXT, deleted_at DATETIME NULL, deleted_token INTEGER NOT NULL DEFAULT 0, UNIQUE (scope, deleted_token))');
        $pdo->exec('CREATE TABLE fun_system_secret (name VARCHAR(64) PRIMARY KEY, value VARCHAR(255) NOT NULL, created_at DATETIME NULL)');
        return $pdo;
    }

    private function config(): array
    {
        return ['list_action_store' => [
            'dsn' => 'sqlite:' . $this->database, 'username' => null, 'password' => null,
            'table' => 'fun_form_list_action_execution', 'secret_table' => 'fun_system_secret',
        ]];
    }

    private function secretOf(ListButtonExecutor $executor): string
    {
        return (string) (new \ReflectionProperty(ListButtonExecutor::class, 'confirmationSecret'))->getValue($executor);
    }
}
