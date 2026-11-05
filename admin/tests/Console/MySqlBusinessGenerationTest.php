<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\command\BusinessGenerationRecover;
use app\admin\development\exception\GenerationInterruptionException;
use app\admin\development\repository\DatabaseGenerationStateRepository;
use app\admin\development\repository\GeneratedFileBaselineRepository;
use app\admin\development\service\GenerationResourceTransaction;
use app\admin\development\service\GenerationTransactionService;
use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\ThreeWayMergePlanner;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

final class MySqlBusinessGenerationTest extends TestCase
{
    private bool $ready = false;
    private string $root;

    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要独立 Console MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        BusinessGenerationFixture::create();
        $this->ready = true;
        $this->root = sys_get_temp_dir() . '/funadmin-mysql-wal-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        if (!$this->ready) return;
        while (Db::connection()->transactionLevel() > 0) Db::connection()->rollBack();
        BusinessGenerationFixture::drop();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_concurrent_generation_claim_has_exactly_one_winner_in_mysql(): void
    {
        [$module, $generation] = BusinessGenerationFixture::seed();
        $code = 'require "tests/Console/mysql-bootstrap.php"; $state = new \\app\\admin\\development\\repository\\DatabaseGenerationStateRepository(); echo $state->claimGeneration((int)$argv[1], (int)$argv[2], "child") ? "won" : "lost";';
        $children = [];
        for ($i = 0; $i < 2; $i++) {
            $process = proc_open([PHP_BINARY, '-r', $code, (string) $module, (string) $generation], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            self::assertIsResource($process);
            fclose($pipes[0]);
            $children[] = [$process, $pipes];
        }
        $results = [];
        foreach ($children as [$process, $pipes]) {
            $results[] = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $error);
        }
        sort($results);
        self::assertSame(['lost', 'won'], $results);
        self::assertSame('running', Db::table('crud_generation')->where('id', $generation)->value('status'));
    }

    public function test_resource_installer_executes_only_hash_bound_sql_with_connection_prefix(): void
    {
        Db::schema()->create('language_line', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->string('locale'); $table->string('key'); $table->text('value'); $table->unique(['locale', 'key']);
        });
        try {
            $sql = "INSERT IGNORE INTO `fun_language_line` (`locale`,`key`,`value`) VALUES ('zh-cn','console.resource','测试资源');";
            $path = 'permissions.sql';
            file_put_contents($this->root . '/' . $path, $sql);
            $definition = ['generationTargets' => ['permissionMigration' => $path]];
            $manifest = ['definitionHash' => CrudDefinition::fromArray($definition)->hash(), 'files' => [['path' => $path, 'hash' => hash('sha256', $sql)]]];
            $installer = new \app\common\crud\CrudResourceInstaller($this->root);
            self::assertSame('applied', $installer->apply($definition, $manifest)['resourceApplyStatus']);
            self::assertSame('测试资源', Db::table('language_line')->where('key', 'console.resource')->value('value'));
            self::assertSame('applied', $installer->apply($definition, $manifest)['resourceApplyStatus']);
            self::assertSame(1, Db::table('language_line')->count());
            file_put_contents($this->root . '/' . $path, $sql . ' DELETE FROM `fun_language_line`;');
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('哈希');
            $installer->apply($definition, $manifest);
        } finally { Db::schema()->dropIfExists('language_line'); }
    }

    public static function interruptionStages(): array
    {
        return [['after_resource_commit', true], ['after_blob_materialized', false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('interruptionStages')]
    public function test_mysql_wal_recovery_preserves_commit_or_rolls_back_uncommitted_files(string $checkpoint, bool $committed): void
    {
        [$module, $generation] = BusinessGenerationFixture::seed();
        $remote = "<?php // mysql generated\n";
        $plan = (new ThreeWayMergePlanner($this->root))->plan([['path' => 'generated.php', 'artifactType' => 'model', 'remoteContent' => $remote]]);
        $plan['definitionHash'] = str_repeat('a', 64);
        $bundle = ['plan' => $plan, 'remoteContents' => ['generated.php' => $remote], 'mergedTextContents' => ['generated.php' => $remote],
            'definitionHash' => str_repeat('a', 64), 'schemaHash' => str_repeat('b', 64), 'registryHash' => str_repeat('c', 64),
            'templateVersion' => 'native', 'migrationHash' => str_repeat('d', 64), 'resourcesHash' => hash('sha256', CrudDefinition::canonicalJson([])), 'resources' => []];
        $tokens = new ConfirmationToken($this->root);
        $state = new DatabaseGenerationStateRepository();
        $baselines = new GeneratedFileBaselineRepository($this->root, $state);
        $resources = new GenerationResourceTransaction();
        $service = new GenerationTransactionService($this->root, $tokens, $baselines, $state, $resources, static fn (): array => $bundle,
            static function (string $point) use ($checkpoint): void { if ($point === $checkpoint) throw new GenerationInterruptionException('mysql interruption'); });
        $interrupted = false;
        try { $service->execute($module, $generation, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle)), fn (): bool => $state->claimGeneration($module, $generation, 'cli')); }
        catch (GenerationInterruptionException) { $interrupted = true; }
        self::assertTrue($interrupted);
        $resources->rollback();
        $id = basename(glob($this->root . '/runtime/private/business-development/wal/*.json')[0], '.json');
        self::assertSame($committed, $state->isGenerationCommitted($module, $generation, $id, $plan['planDigest']));
        self::assertSame(0, Db::connection()->transactionLevel());
        $command = new CommandTester(new BusinessGenerationRecover($this->root));
        self::assertSame(0, $command->execute(['transaction' => $id]), $command->getDisplay());
        self::assertSame($committed ? 'completed' : 'rolled_back', json_decode($command->getDisplay(), true)['state']);
        if ($committed) {
            self::assertSame($remote, file_get_contents($this->root . '/generated.php'));
            self::assertSame('published', Db::table('business_module')->where('id', $module)->value('lifecycle_status'));
            $record = $baselines->baselines($module)[0];
            self::assertSame($remote, $baselines->load($record['base_storage_path'], $record['base_hash']));
        } else {
            self::assertFileDoesNotExist($this->root . '/generated.php');
            self::assertSame('rolled_back', Db::table('crud_generation')->where('id', $generation)->value('recovery_status'));
            self::assertSame([], $baselines->baselines($module));
            self::assertSame([], glob($this->root . '/runtime/private/business-development/blobs/*/*.blob'));
        }
        self::assertSame(0, $command->execute(['transaction' => $id]));
    }
}
