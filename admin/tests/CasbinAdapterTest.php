<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\repository\casbin\DatabaseAdapter;
use app\admin\authorization\service\CasbinService;
use app\common\service\database\MigrationService;
use Casbin\Model\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CasbinAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        foreach (['permission', 'casbin_rule', 'auth_group'] as $table) Db::table($table)->delete();
        Db::table('auth_group')->insert(['id' => 2, 'name' => '查看者', 'code' => 'reader', 'status' => 1]);
        Db::table('permission')->insert(['id' => 10, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo/page', 'act' => 'read', 'resource_type' => 'capability']);
    }

    public function test_incremental_adapter_writes_are_idempotent_and_domain_scoped(): void
    {
        $adapter = new DatabaseAdapter('default');
        $other = new DatabaseAdapter('other');
        foreach ([$adapter, $other] as $instance) {
            $domain = $instance === $adapter ? 'default' : 'other';
            $instance->addPolicy('p', 'p', ['role:2', $domain, 'demo/page', 'read']);
            $instance->addPolicy('p', 'p', ['role:2', $domain, 'demo/page', 'read']);
        }
        self::assertSame(2, Db::table('casbin_rule')->count());
        $adapter->removeFilteredPolicy('p', 'p', 0, 'role:2');
        self::assertSame(['other'], Db::table('casbin_rule')->whereNull('deleted_at')->pluck('v1')->all());
        $other->removePolicy('p', 'p', ['role:2', 'other', 'demo/page', 'read']);
        self::assertSame(0, Db::table('casbin_rule')->whereNull('deleted_at')->count());
    }

    public function test_adapter_cannot_write_another_domain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DatabaseAdapter('default'))->addPolicy('p', 'p', ['role:2', 'other', 'demo/page', 'read']);
    }

    public function test_filtered_snapshot_cannot_overwrite_the_policy_store(): void
    {
        $adapter = new DatabaseAdapter('default');
        $adapter->addPolicy('p', 'p', ['role:2', 'default', 'demo/page', 'read']);
        try {
            $adapter->savePolicy(new Model());
            self::fail('不允许使用过滤快照覆盖授权');
        } catch (\LogicException $error) {
            self::assertSame(1, Db::table('casbin_rule')->count());
        }
    }

    public function test_fresh_database_resources_authorize_through_the_real_engine(): void
    {
        Db::table('permission')->delete();
        (require base_path() . '/database/migrations/001_baseline.php')();
        $code = \app\admin\authorization\service\PermissionCode::derive(\app\admin\authorization\controller\SystemRole::class, 'index');
        $permission = Db::table('permission')->where('code', $code)->first();
        self::assertNotNull($permission);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [2]);
        $casbin->syncRolePermissions(2, [(int) $permission->id]);
        self::assertTrue($casbin->enforceAdmin(2, $permission->obj, $permission->act));
        self::assertFalse($casbin->enforceAdmin(2, $permission->obj, 'delete'));
    }

    public function test_a_persistent_worker_observes_committed_revocation_from_another_process(): void
    {
        /** @var \Webman\Database\DatabaseManager $manager */
        $manager = EloquentModel::getConnectionResolver();
        $previous = $manager->getDefaultConnection();
        $database = tempnam(runtime_path(), 'casbin-worker-');
        $name = 'casbin_worker_test';
        $settings = ['driver' => 'sqlite', 'database' => $database, 'prefix' => 'worker_'];
        /** @var \ArrayAccess $config */
        $config = \Illuminate\Container\Container::getInstance()['config'];
        $connections = $config['database.connections'];
        $connections[$name] = $settings;
        $config['database.connections'] = $connections;
        $manager->setDefaultConnection($name);
        $process = null;
        $pipes = [];
        try {
            (new MigrationService())->run();
            Db::table('auth_group')->insert(['id' => 2, 'name' => '查看者', 'code' => 'reader', 'status' => 1]);
            Db::table('permission')->insert(['id' => 90001, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo/page', 'act' => 'read', 'resource_type' => 'capability']);
            $writer = new CasbinService();
            $writer->syncAdminRoles(2, [2]);
            $writer->syncRolePermissions(2, [90001]);
            $code = <<<'PHP'
require 'tests/bootstrap.php';
$manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
$config = \Illuminate\Container\Container::getInstance()['config'];
$connections = $config['database.connections'];
$connections['casbin_worker_test'] = json_decode($argv[1], true);
$config['database.connections'] = $connections;
$manager->setDefaultConnection('casbin_worker_test');
$reader = new \app\admin\authorization\service\CasbinService();
while (fgets(STDIN) !== false) {
    echo ($reader->enforceAdmin(2, 'demo/page', 'read') ? 'allow' : 'deny') . "\n";
    flush();
}
PHP;
            $process = proc_open([PHP_BINARY, '-r', $code, json_encode($settings, JSON_THROW_ON_ERROR)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            self::assertIsResource($process);
            stream_set_timeout($pipes[1], 10);
            $read = static function () use ($pipes): string {
                fwrite($pipes[0], "check\n");
                return trim((string) fgets($pipes[1]));
            };
            self::assertSame('allow', $read());
            Db::beginTransaction();
            $writer->syncRolePermissions(2, []);
            self::assertSame('allow', $read(), '未提交的撤权不能污染其他 worker');
            Db::rollBack();
            self::assertSame('allow', $read());
            $writer->syncRolePermissions(2, []);
            self::assertSame('deny', $read(), '同一常驻进程必须看到其他连接已提交的撤权');
            $writer->syncRolePermissions(2, [90001]);
            self::assertSame('allow', $read());
            fclose($pipes[0]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            $process = null;
            self::assertSame(0, $status, $error);
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            $manager->setDefaultConnection($previous);
            $manager->purge($name);
            unlink($database);
        }
    }
}
