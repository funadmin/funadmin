<?php

declare(strict_types=1);

namespace Tests;

use app\admin\plugin\service\PluginInfrastructureService;
use app\common\form\action\ListActionStore;
use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;
use app\common\model\MemberApiSession;
use app\common\model\MemberApiSessionUsed;
use app\common\model\PhpMigration;
use app\common\plugin\model\PluginResource;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginScaffolder;
use app\common\service\database\MigrationService;
use app\common\service\member\MemberApiSessionService;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use support\Db;

/**
 * 其余核心模型统一软删除：核心表对应的每个模型都使用 LaravelSoftDelete；
 * 插件资源登记、会话等基础设施记录删除时只做标记，同一唯一值可以重新登记。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SoftDeleteCoreModelsTest extends TestCase
{
    /** 不扫描的目录：插件代码与生成的业务模块（不在本次范围内）。 */
    private const EXCLUDED = ['app/admin/business/', 'app/admin/plugins/', 'app/cms/', 'app/document/', 'app/qa/'];

    /**
     * 映射到非核心表的模型：PHP 迁移执行记录表 migrations 由迁移执行器自行创建（不在核心 SQL 中，没有 deleted_at），
     * 迁移链路在补列之前就要读写它，不能启用软删除。
     */
    private const NON_CORE_MODELS = [PhpMigration::class => 'migrations'];

    public function test_every_core_table_model_uses_soft_delete(): void
    {
        $root = dirname(__DIR__);
        $coreTables = [];
        foreach (glob($root . '/database/core/migrations/*.sql') ?: [] as $file) {
            preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`fun_([a-z0-9_]+)`/', (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $table) $coreTables[$table] = true;
        }
        self::assertGreaterThan(60, count($coreTables));

        $models = [];
        foreach (['app/admin', 'app/common', 'app/api'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                if (!str_ends_with($relative, '.php') || array_filter(self::EXCLUDED, static fn (string $prefix): bool => str_starts_with($relative, $prefix))) continue;
                $source = (string) file_get_contents($file->getPathname());
                if (!preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) || !preg_match('/^(?:final\s+|abstract\s+)*class\s+(\w+)/m', $source, $class)) continue;
                $name = $namespace[1] . '\\' . $class[1];
                if (!class_exists($name) || !is_subclass_of($name, Model::class)) continue;
                $reflection = new ReflectionClass($name);
                if ($reflection->isAbstract() || $name === BaseModel::class) continue;
                $models[$name] = $reflection->newInstanceWithoutConstructor()->getTable();
            }
        }
        self::assertGreaterThan(50, count($models));

        $missing = [];
        foreach ($models as $class => $table) {
            if (isset(self::NON_CORE_MODELS[$class])) {
                self::assertSame(self::NON_CORE_MODELS[$class], $table);
                self::assertArrayNotHasKey($table, $coreTables, $class . ' 被登记为非核心表模型');
                continue;
            }
            self::assertArrayHasKey($table, $coreTables, $class . ' 映射的表 ' . $table . ' 不是核心表');
            if (!in_array(LaravelSoftDelete::class, $this->traits($class), true)) $missing[] = $class . ' (' . $table . ')';
        }
        self::assertSame([], $missing, '核心表模型未使用 LaravelSoftDelete');
    }

    public function test_plugin_resource_reregistered_after_uninstall_reinstall(): void
    {
        (new MigrationService())->run();
        $root = sys_get_temp_dir() . '/funadmin-sdres-' . bin2hex(random_bytes(6));
        (new PluginScaffolder($root . '/plugins'))->scaffold('sdres');
        $infrastructure = new PluginInfrastructureService(projectRoot: $root);
        try {
            $manifest = Manifest::fromDirectory($root . '/plugins/sdres');
            $infrastructure->publishResources($manifest, 'install-1');
            $infrastructure->completePublishedResources('install-1');
            $installed = $this->liveTargets();
            self::assertNotSame([], $installed);
            self::assertContains('native_app', array_unique(array_column($installed, 'resource_type')));

            // 原样重新发布不新增登记行（按差异同步），已有行的 id 不变。
            $infrastructure->publishResources($manifest, 'update-1');
            $infrastructure->completePublishedResources('update-1');
            self::assertSame($installed, $this->liveTargets());
            self::assertSame(0, PluginResource::onlyTrashed()->count());

            // 卸载：登记只做软删除，记录保留。
            $infrastructure->removePublishedResources('sdres', 'uninstall-1');
            $infrastructure->completePublishedResources('uninstall-1');
            self::assertSame(0, PluginResource::query()->where('plugin_code', 'sdres')->count());
            $trashed = PluginResource::onlyTrashed()->where('plugin_code', 'sdres')->get();
            self::assertCount(count($installed), $trashed);
            foreach ($trashed as $row) {
                self::assertNotNull($row->deleted_at);
                self::assertSame((int) $row->id, (int) Db::table('plugin_resource')->where('id', $row->id)->value('deleted_token'));
            }

            // 重新安装：相同 target_path 可以再次登记为新的有效行。
            $infrastructure->publishResources($manifest, 'install-2');
            $infrastructure->completePublishedResources('install-2');
            $reinstalled = $this->liveTargets();
            self::assertSame(array_keys($installed), array_keys($reinstalled));
            self::assertSame([], array_intersect(array_column($installed, 'id'), array_column($reinstalled, 'id')));
            self::assertSame(count($installed), PluginResource::onlyTrashed()->count());
            self::assertArrayNotHasKey('deleted_token', PluginResource::query()->firstOrFail()->toArray());
        } finally {
            $this->removeTree($root);
        }
    }

    public function test_expired_sessions_are_soft_deleted(): void
    {
        putenv('MEMBER_JWT_SECRET=' . bin2hex(random_bytes(32)));
        putenv('MEMBER_REFRESH_JWT_SECRET=' . bin2hex(random_bytes(32)));
        \Webman\Config::load(config_path(), ['route']);
        (new MigrationService())->run();
        $member = (int) Db::table('member')->insertGetId([
            'username' => 'sdsession', 'password' => '', 'status' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $service = new MemberApiSessionService();
        $pair = $service->open($member, '127.0.0.1');
        $session = MemberApiSession::query()->where('member_id', $member)->firstOrFail();
        $rotated = $service->rotate($pair['refresh_token']);
        self::assertNotNull($rotated);
        $used = MemberApiSessionUsed::query()->where('session_id', $session->session_id)->firstOrFail();
        self::assertSame(hash('sha256', $pair['refresh_token']), $used->refresh_hash);

        // 过期会话：删除只做标记，记录保留，不再能刷新或认证。
        MemberApiSession::query()->where('id', $session->id)->update(['refresh_expires_at' => time() - 10]);
        MemberApiSession::query()->where('refresh_expires_at', '<=', time())->delete();
        MemberApiSessionUsed::query()->where('session_id', $session->session_id)->delete();
        $row = Db::table('member_api_session')->where('id', $session->id)->first();
        self::assertNotNull($row->deleted_at);
        self::assertSame((int) $session->id, (int) $row->deleted_token);
        self::assertNull(MemberApiSession::query()->find($session->id));
        self::assertTrue(MemberApiSession::withTrashed()->findOrFail($session->id)->trashed());
        self::assertSame(1, MemberApiSessionUsed::onlyTrashed()->where('session_id', $session->session_id)->count());
        self::assertNull($service->rotate($rotated['refresh_token']));
        self::assertNull($service->authenticate($rotated['access_token']));

        // 软删除后相同的唯一值（session_id / refresh_hash）可以重新写入。
        MemberApiSession::query()->insert([
            'session_id' => $session->session_id, 'member_id' => $member, 'refresh_hash' => $session->refresh_hash,
            'refresh_expires_at' => time() + 60, 'ip' => '', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        MemberApiSessionUsed::query()->insert(['refresh_hash' => $used->refresh_hash, 'session_id' => $session->session_id, 'used_at' => date('Y-m-d H:i:s')]);
        self::assertSame(1, MemberApiSession::query()->where('session_id', $session->session_id)->count());
        self::assertSame(2, MemberApiSession::withTrashed()->where('session_id', $session->session_id)->count());
        self::assertSame(2, MemberApiSessionUsed::withTrashed()->where('refresh_hash', $used->refresh_hash)->count());

        // 列表动作的幂等占位：释放只做软删除，同一请求可以重新占位。
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE list_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, scope VARCHAR(64) NOT NULL, digest VARCHAR(64) NOT NULL, state VARCHAR(16) NOT NULL, result TEXT, deleted_at DATETIME NULL, deleted_token INTEGER NOT NULL DEFAULT 0, UNIQUE (scope, deleted_token))');
        $store = new ListActionStore($pdo, 'list_actions');
        $digest = str_repeat('a', 64);
        self::assertTrue($store->claim('scope-1', $digest)['claimed']);
        $store->release('scope-1', $digest);
        $released = $pdo->query('SELECT id, deleted_at, deleted_token FROM list_actions')->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(1, $released);
        self::assertNotNull($released[0]['deleted_at']);
        self::assertSame((int) $released[0]['id'], (int) $released[0]['deleted_token']);
        self::assertTrue($store->claim('scope-1', $digest)['claimed']);
        $store->finish('scope-1', $digest, 'success', ['ok' => true]);
        self::assertSame(['claimed' => false, 'state' => 'success', 'result' => ['ok' => true]], $store->claim('scope-1', $digest));
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM list_actions')->fetchColumn());
    }

    /** @return array<string, array{id: int, resource_type: string}> */
    private function liveTargets(): array
    {
        $rows = [];
        foreach (PluginResource::query()->where('plugin_code', 'sdres')->orderBy('target_path')->get() as $row) {
            $rows[(string) $row->target_path] = ['id' => (int) $row->id, 'resource_type' => (string) $row->resource_type];
        }
        return $rows;
    }

    /** @return list<string> */
    private function traits(string $class): array
    {
        $traits = [];
        for ($reflection = new ReflectionClass($class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            $traits = array_merge($traits, $reflection->getTraitNames());
        }
        return $traits;
    }

    private function removeTree(string $root): void
    {
        if (!is_dir($root)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($root);
    }
}
