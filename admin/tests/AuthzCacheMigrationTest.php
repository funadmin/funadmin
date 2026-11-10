<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\AuthzCache;
use app\admin\authorization\service\CasbinService;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;

/**
 * 授权版本与迁移、跨进程：
 * - 005 之前（system_secret 没有 deleted_at）版本可读、可换；005 自身触发的换版本不打断迁移。
 * - 换版本遇到结构错误时写入照常完成，本进程关闭 worker 缓存。
 * - 两个独立进程（各自的静态缓存）经共享库中的版本看到彼此的授权写入。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AuthzCacheMigrationTest extends TestCase
{
    private string $directory = '';
    private string $database = '';

    protected function setUp(): void
    {
        Context::destroy();
        $this->directory = sys_get_temp_dir() . '/authz-migrations-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        foreach (['001_baseline.php', '002_code_generator.php', '003_system_secret.php', '004_plugin_admin_web_directory.php'] as $file) {
            copy(base_path() . '/database/migrations/' . $file, $this->directory . '/' . $file);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) unlink($file);
        if (is_dir($this->directory)) rmdir($this->directory);
        if ($this->database !== '' && is_file($this->database)) unlink($this->database);
        Context::destroy();
    }

    public function test_version_works_before_005_and_005_bumps_without_breaking(): void
    {
        (new MigrationService())->run($this->directory);
        self::assertFalse(Db::schema()->hasColumn('system_secret', 'deleted_at'), '夹具必须是 005 之前的结构');

        $first = AuthzCache::version();
        self::assertIsString($first);
        self::assertNotSame('', trim($first));
        Db::table('auth_group')->insert(['id' => 5, 'pid' => 0, 'name' => 'op', 'code' => 'op', 'level' => 10, 'data_scope' => 'self', 'status' => 1]);
        $second = AuthzCache::version();
        self::assertNotSame($first, $second, '005 之前的授权写入同样换版本');

        // 005 改写授权表结构，期间触发的换版本不得打断迁移。
        copy(base_path() . '/database/migrations/005_soft_delete.php', $this->directory . '/005_soft_delete.php');
        self::assertSame(['005_soft_delete.php'], (new MigrationService())->run($this->directory));
        self::assertTrue(Db::schema()->hasColumn('system_secret', 'deleted_at'));
        $third = AuthzCache::version();
        self::assertNotSame($second, $third, '005 改动授权表后版本已换');

        CasbinService::instance()->syncAdminRoles(2, [5]);
        self::assertNotSame($third, AuthzCache::version());
    }

    public function test_bump_schema_failure_never_breaks_the_write_and_disables_the_worker_cache(): void
    {
        (new MigrationService())->run($this->directory);
        // 版本表结构异常（缺 value 列）：读取为 null，换版本失败被吞下。
        Db::schema()->drop('system_secret');
        Db::statement('CREATE TABLE fun_system_secret (name VARCHAR(64) PRIMARY KEY, other VARCHAR(10) NULL)');
        Db::table('system_secret')->insert(['name' => 'authz_version', 'other' => 'x']);
        self::assertNull(AuthzCache::version());

        Db::statement("UPDATE fun_auth_group SET name = 'renamed' WHERE id = 1");
        Db::transaction(static function (): void {
            Db::table('permission')->where('id', 1)->update(['name' => 'changed']);
        });
        self::assertSame('changed', Db::table('permission')->where('id', 1)->value('name'), '写入与提交照常完成');

        // 恢复正常的版本表后，本进程仍不使用 worker 缓存。
        Db::schema()->drop('system_secret');
        Db::statement('CREATE TABLE fun_system_secret (name VARCHAR(64) PRIMARY KEY, value VARCHAR(255) NOT NULL, created_at DATETIME NULL)');
        self::assertNotNull(AuthzCache::version());
        CasbinService::instance()->permissionCodesForAdmin(2);
        self::assertSame(0, AuthzCache::size());
    }

    public function test_two_worker_processes_see_each_others_writes_through_the_shared_version(): void
    {
        $this->database = sys_get_temp_dir() . '/authz-workers-' . bin2hex(random_bytes(4)) . '.sqlite';
        touch($this->database);
        $a = $this->worker();
        $b = $this->worker();
        self::assertSame(['ok' => true], $this->send($a, 'seed'));

        $warm = $this->send($b, 'codes');
        self::assertFalse($warm['target']);
        self::assertGreaterThan(0, $warm['size'], 'B 已建立自己的 worker 缓存');
        self::assertSame($warm, $this->send($b, 'codes'), 'B 命中自己的 worker 缓存');

        // A 进程写入：只有 A 的监听器换版本，B 的静态缓存只能经共享库中的版本得知变化。
        self::assertSame(['ok' => true], $this->send($a, 'grant'));
        self::assertTrue($this->send($b, 'codes')['target']);
        foreach ([$a, $b] as $worker) {
            fclose($worker['pipes'][0]);
            fclose($worker['pipes'][1]);
            proc_close($worker['process']);
        }
    }

    /** @return array{process: resource, pipes: array<int, resource>} */
    private function worker(): array
    {
        $process = proc_open([PHP_BINARY, base_path() . '/tests/fixtures/authz-worker.php', $this->database], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, base_path());
        self::assertIsResource($process);
        return ['process' => $process, 'pipes' => $pipes];
    }

    private function send(array $worker, string $command): array
    {
        fwrite($worker['pipes'][0], $command . "\n");
        fflush($worker['pipes'][0]);
        $line = fgets($worker['pipes'][1]);
        self::assertIsString($line, '工作进程无输出：' . $command);
        return json_decode($line, true, flags: JSON_THROW_ON_ERROR);
    }
}
