<?php

declare(strict_types=1);

namespace Tests;

use app\common\form\action\ListButtonExecutor;
use app\common\form\registry\FormRegistryFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** config/form.php 让注册动作开箱可用：无需配置确认密钥（自动生成并持久化到数据库），原子存储指向已有执行表。 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FormListActionConfigTest extends TestCase
{
    private string $database = '';

    protected function tearDown(): void
    {
        if ($this->database !== '' && is_file($this->database)) unlink($this->database);
    }

    public function test_config_no_longer_carries_a_confirmation_secret_or_reads_env(): void
    {
        putenv('FORM_LIST_CONFIRMATION_SECRET=' . str_repeat('k', 40));
        self::assertArrayNotHasKey('list_confirmation_secret', $this->config());
        $source = (string) file_get_contents(config_path('form.php'));
        self::assertStringNotContainsString('FORM_LIST_CONFIRMATION_SECRET', $source);
        self::assertStringNotContainsString('hash_hmac', $source);
    }

    public function test_store_points_at_the_prefixed_execution_table_of_the_default_connection(): void
    {
        $store = $this->config()['list_action_store'];
        self::assertSame((getenv('DB_PREFIX') !== false ? getenv('DB_PREFIX') : 'fun_') . 'form_list_action_execution', $store['table']);
        self::assertSame('sqlite::memory:', $store['dsn']);
        putenv('DB_CONNECTION=mysql');
        putenv('DB_HOST=db.internal');
        putenv('DB_PORT=3307');
        putenv('DB_DATABASE=funadmin_app');
        putenv('DB_USERNAME=app');
        putenv('DB_PASSWORD=pw');
        self::assertSame(['dsn' => 'mysql:host=db.internal;port=3307;dbname=funadmin_app;charset=utf8mb4', 'username' => 'app', 'password' => 'pw',
            'table' => 'fun_form_list_action_execution', 'secret_table' => 'fun_system_secret'],
            $this->config()['list_action_store']);
    }

    public function test_production_factory_builds_the_list_executor_from_config(): void
    {
        $this->database = sys_get_temp_dir() . '/funadmin-list-store-' . bin2hex(random_bytes(6)) . '.sqlite';
        $pdo = new \PDO('sqlite:' . $this->database);
        $pdo->exec('CREATE TABLE fun_form_list_action_execution (id INTEGER PRIMARY KEY AUTOINCREMENT, scope VARCHAR(64) NOT NULL, digest VARCHAR(64) NOT NULL, state VARCHAR(16) NOT NULL, result TEXT, deleted_at DATETIME NULL, deleted_token INTEGER NOT NULL DEFAULT 0, UNIQUE (scope, deleted_token))');
        $pdo->exec('CREATE TABLE fun_system_secret (name VARCHAR(64) PRIMARY KEY, value VARCHAR(255) NOT NULL, created_at DATETIME NULL)');
        putenv('DB_DATABASE=' . $this->database);
        putenv('DB_PREFIX=fun_');
        \Webman\Config::load(base_path() . '/config', ['route']);

        self::assertInstanceOf(ListButtonExecutor::class, FormRegistryFactory::production()->listExecutor());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', (string) $pdo->query('SELECT value FROM fun_system_secret')->fetchColumn());
    }

    private function config(): array
    {
        return require config_path('form.php');
    }
}
