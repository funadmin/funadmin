<?php

declare(strict_types=1);

// 仅替换持久化边界：不初始化应用，不连接或修改真实数据库。
namespace app\common\model {
    final class SystemMigration
    {
        public static array $rows = [];
        private array $filters = [];
        public static function __callStatic(string $method, array $args): self
        {
            $query = new self();
            return $query->__call($method, $args);
        }
        public function __call(string $method, array $args): mixed
        {
            if ($method !== 'where') throw new \RuntimeException('意外查询：' . $method);
            $this->filters[$args[0]] = $args[1];
            return $this;
        }
        public function column(string $field): array
        {
            return array_column($this->matching(), $field);
        }
        public function find(): ?object
        {
            $rows = $this->matching();
            return $rows === [] ? null : (object) reset($rows);
        }
        private function matching(): array
        {
            return array_values(array_filter(self::$rows, fn (array $row): bool => array_intersect_assoc($row, $this->filters) === $this->filters));
        }
        public static function create(array $row): void { self::$rows[] = $row; }
    }
}

namespace think\facade {
    final class Db
    {
        public static bool $ready = true;
        public static array $executed = [];
        public static function connect(): self { return new self(); }
        public function getTables(): array { return self::$ready ? ['test_system_migration'] : []; }
        public static function transaction(callable $callback): void { $callback(); }
        public static function execute(string $sql): void { self::$executed[] = $sql; }
        public static function query(string $sql, array $bind = [], bool $master = false): array
        {
            if (!str_contains($sql, '@@character_set_client')) throw new \RuntimeException('意外查询');
            return [['client'=>'utf8mb4','connection_charset'=>'utf8mb4','results_charset'=>'utf8mb4']];
        }
    }
}

namespace {
    function config(string $key): mixed
    {
        return match ($key) {
            'database.connections.mysql.prefix' => 'test_',
            'funadmin.mysqlPrefix' => 'fun_',
            default => throw new RuntimeException('意外配置读取：' . $key),
        };
    }

    require dirname(__DIR__) . '/vendor/autoload.php';

    use app\common\model\SystemMigration;
    use app\common\plugin\sdk\Manifest;
    use app\common\plugin\sdk\PluginScaffolder;
    use app\common\service\MigrationService;
    use app\admin\plugin\service\PluginInfrastructureService;
    use think\facade\Db;

    function migrationExpect(bool $ok, string $message): void
    {
        if (!$ok) throw new RuntimeException($message);
    }
    function migrationReject(callable $callback, string $message): void
    {
        try { $callback(); } catch (Throwable $exception) {
            migrationExpect(str_contains($exception->getMessage(), $message), '异常不匹配：' . $exception->getMessage());
            return;
        }
        throw new RuntimeException('未拒绝：' . $message);
    }

    $root = sys_get_temp_dir() . '/funadmin-empty-migration-' . bin2hex(random_bytes(6));
    $failures = [];
    $cases = 0;
    $test = static function (string $name, callable $callback) use (&$failures, &$cases): void {
        $cases++;
        SystemMigration::$rows = [];
        Db::$executed = [];
        Db::$ready = true;
        try { $callback(); echo "PASS {$name}\n"; }
        catch (Throwable $exception) { $failures[] = $name; echo "FAIL {$name}: {$exception->getMessage()}\n"; }
    };
    try {
        // 关闭非必要应用层生成，避免无关 namespace 问题干扰迁移行为测试。
        (new PluginScaffolder($root))->scaffold('demo', '无数据库插件', false, false, false);
        $manifest = Manifest::fromDirectory($root . '/demo');
        $path = $root . '/demo/database/migrations';
        $service = new MigrationService();
        $infrastructure = new PluginInfrastructureService();
        $noop = ['executed' => [], 'version' => ''];
        $test('脚手架空目录允许首次安装及重复迁移', static function () use ($infrastructure, $manifest, $noop): void {
            migrationExpect($infrastructure->migrate($manifest) === $noop, '首次迁移应为空');
            migrationExpect($infrastructure->migrate($manifest) === $noop, '重复迁移应为空');
            migrationExpect(Db::$executed === [] && SystemMigration::$rows === [], '不得执行 SQL 或登记假迁移');
        });
        $test('其他插件历史不妨碍当前空插件', static function () use ($infrastructure, $manifest, $noop): void {
            SystemMigration::$rows = [['scope' => 'plugin:other', 'version' => '001_old', 'checksum' => 'old']];
            migrationExpect($infrastructure->migrate($manifest) === $noop, '必须按 scope 隔离');
        });
        $test('core 空目录仍拒绝', static fn () => migrationReject(fn () => $service->runDirectory($path), '没有 SQL 文件'));
        $test('缺少核心仓库仍拒绝', static function () use ($infrastructure, $manifest): void {
            Db::$ready = false;
            migrationReject(fn () => $infrastructure->migrate($manifest), '核心 migration');
        });
        $test('已登记迁移全部丢失仍拒绝', static function () use ($infrastructure, $manifest): void {
            SystemMigration::$rows = [['scope' => 'plugin:demo', 'version' => '001_old', 'checksum' => 'old']];
            migrationReject(fn () => $infrastructure->migrate($manifest), '已登记的 migration 文件缺失');
        });
        $test('已登记迁移部分丢失在执行前拒绝', static function () use ($infrastructure, $manifest, $path): void {
            SystemMigration::$rows = [['scope' => 'plugin:demo', 'version' => '001_old', 'checksum' => 'old']];
            file_put_contents($path . '/002_new.sql', 'CREATE TABLE fun_probe (id INT);');
            try {
                migrationReject(fn () => $infrastructure->migrate($manifest), '已登记的 migration 文件缺失');
                migrationExpect(Db::$executed === [], '文件缺失不得先执行 pending');
            } finally { unlink($path . '/002_new.sql'); }
        });
        $test('非空迁移执行、幂等及 checksum 仍有效', static function () use ($infrastructure, $manifest, $path): void {
            file_put_contents($path . '/001_probe.sql', 'CREATE TABLE fun_probe (id INT);');
            try {
                migrationExpect($infrastructure->migrate($manifest) === ['executed' => ['001_probe'], 'version' => '001_probe'], '应执行真实迁移');
                migrationExpect(count(Db::$executed) === 1 && count(SystemMigration::$rows) === 1, '只执行和登记一次');
                migrationExpect($infrastructure->migrate($manifest) === ['executed' => [], 'version' => '001_probe'], '重复迁移应幂等');
                file_put_contents($path . '/001_probe.sql', 'CREATE TABLE fun_changed (id INT);');
                migrationReject(fn () => $infrastructure->migrate($manifest), '内容发生变化');
            } finally { unlink($path . '/001_probe.sql'); }
        });
        $test('重复数字版本仍拒绝', static function () use ($infrastructure, $manifest, $path): void {
            file_put_contents($path . '/001_a.sql', 'CREATE TABLE fun_a (id INT);');
            file_put_contents($path . '/001_b.sql', 'CREATE TABLE fun_b (id INT);');
            try { migrationReject(fn () => $infrastructure->migrate($manifest), '数字版本重复'); }
            finally { unlink($path . '/001_a.sql'); unlink($path . '/001_b.sql'); }
        });
        $test('声明目录在校验后丢失仍拒绝', static function () use ($infrastructure, $manifest, $path): void {
            rename($path, $path . '-saved');
            try { migrationReject(fn () => $infrastructure->migrate($manifest), '目录不存在'); }
            finally { rename($path . '-saved', $path); }
        });
        $data = $manifest->toArray();
        unset($data['migrations']);
        file_put_contents($root . '/demo/plugin.json', json_encode($data, JSON_THROW_ON_ERROR));
        $undeclared = Manifest::fromDirectory($root . '/demo');
        $test('未声明迁移且无历史允许无数据库插件', static function () use ($infrastructure, $undeclared, $noop): void {
            migrationExpect($infrastructure->migrate($undeclared) === $noop, '无声明无历史应为空');
        });
        $test('移除迁移声明不得隐藏历史丢失', static function () use ($infrastructure, $undeclared): void {
            SystemMigration::$rows = [['scope' => 'plugin:demo', 'version' => '001_old', 'checksum' => 'old']];
            migrationReject(fn () => $infrastructure->migrate($undeclared), '目录不存在');
        });
    } finally {
        if (is_dir($root)) {
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            rmdir($root);
        }
    }
    echo 'plugin empty migration tests: ' . ($failures === [] ? 'PASS' : 'FAIL') . " ({$cases} cases, " . count($failures) . " failures)\n";
    exit($failures === [] ? 0 : 1);
}
