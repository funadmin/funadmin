<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudGenerator;
use PHPUnit\Framework\TestCase;

final class CrudGenerationExecutionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/funadmin-crud-generation-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_cli_validate_preview_generate_and_token_file_permissions(): void
    {
        $definition = CrudDefinitionFixture::make('console-cli')->toArray();
        $definition['tableIdentity'] = ['source' => 'created', 'kind' => 'physical'];
        file_put_contents($this->root . '/definition.json', json_encode($definition));
        $validate = new \Symfony\Component\Console\Tester\CommandTester(new \app\admin\development\command\CrudValidate($this->root));
        self::assertSame(0, $validate->execute(['definition' => $this->root . '/definition.json']), $validate->getDisplay());
        self::assertSame(1, $validate->execute(['definition' => $this->root . '/missing.json']));
        $preview = new \Symfony\Component\Console\Tester\CommandTester(new \app\admin\development\command\CrudPreview($this->root));
        self::assertSame(1, $preview->execute(['definition' => $this->root . '/missing.json']));
        self::assertSame(0, $preview->execute(['definition' => $this->root . '/definition.json']), $preview->getDisplay());
        $plan = json_decode($preview->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        file_put_contents($this->root . '/token', $plan['sensitive']['confirmToken']);
        chmod($this->root . '/token', 0644);
        $generate = new \Symfony\Component\Console\Tester\CommandTester(new \app\admin\development\command\CrudGenerate($this->root));
        $arguments = ['definition' => $this->root . '/definition.json', '--confirm-token-file' => $this->root . '/token'];
        self::assertSame(1, $generate->execute($arguments));
        self::assertStringContainsString('0600', $generate->getDisplay());
        self::assertDirectoryDoesNotExist($this->root . '/app');
        chmod($this->root . '/token', 0600);
        self::assertSame(0, $generate->execute($arguments), $generate->getDisplay());
        self::assertFileExists($this->root . '/app/admin/generated/console_cli/model/ConsoleCli.php');
        self::assertDirectoryDoesNotExist($this->root . '/app/admin/model');
        self::assertSame('written', json_decode($generate->getDisplay(), true)['write']['status']);
        self::assertSame(1, $generate->execute($arguments));
        $mcp = new \app\common\service\McpService($this->root);
        $result = $mcp->handleCrud('definition.json');
        self::assertTrue($result['success']);
        self::assertArrayNotHasKey('confirmToken', $result['data']['plan']);
    }

    public function test_preview_confirm_generate_produces_only_native_php_and_rejects_replay(): void
    {
        $definition = CrudDefinitionFixture::make('console-generation');
        $generator = new CrudGenerator($this->root);
        $plan = $generator->plan($definition);
        self::assertNotEmpty($plan['confirmToken']);
        self::assertDirectoryDoesNotExist($this->root . '/app');
        $result = $generator->generate($definition, $plan['confirmToken']);
        self::assertSame('written', $result['write']['status']);
        foreach ($plan['files'] as $file) {
            self::assertFileExists($this->root . '/' . $file['path']);
            if (str_ends_with($file['path'], '.php')) {
                $source = file_get_contents($this->root . '/' . $file['path']);
                self::assertStringNotContainsString('think\\', $source, $file['path']);
                token_get_all($source, TOKEN_PARSE);
            }
        }
        $rejected = false;
        try { $generator->generate($definition, $plan['confirmToken']); }
        catch (\Throwable $error) { $rejected = true; self::assertStringContainsString('token', $error->getMessage()); }
        self::assertTrue($rejected, '重复确认 token 应拒绝');
    }

    public function test_atomic_writer_rolls_back_files_after_failure_and_rejects_path_escape(): void
    {
        file_put_contents($this->root . '/existing.txt', 'before');
        $tokens = new \app\common\crud\ConfirmationToken($this->root, 'test-confirmation-secret');
        $plan = (new \app\common\crud\GenerationPlanner($this->root, $tokens))->plan(CrudDefinitionFixture::make(), ['existing.txt' => 'after', 'new.txt' => 'new']);
        $writer = new \app\common\crud\AtomicWriter($this->root, static function (): void { throw new \RuntimeException('模拟写入故障'); }, $tokens);
        try { $writer->write($plan, $plan['confirmToken'], ['existing.txt']); self::fail('故障注入必须中止'); }
        catch (\RuntimeException $error) { self::assertStringContainsString('模拟写入故障', $error->getMessage()); }
        self::assertSame('before', file_get_contents($this->root . '/existing.txt'));
        self::assertFileDoesNotExist($this->root . '/new.txt');
        $this->expectException(\InvalidArgumentException::class);
        \app\common\crud\PathGuard::resolve($this->root, '../escape.php', '项目目录');
    }

    public function test_changed_target_blocks_old_confirmation_without_overwriting_local_edits(): void
    {
        $definition = CrudDefinitionFixture::make('console-drift');
        $generator = new CrudGenerator($this->root);
        $plan = $generator->plan($definition);
        $path = $this->root . '/app/admin/model/generated/ConsoleDrift.php';
        mkdir(dirname($path), 0700, true);
        file_put_contents($path, 'manual');
        $rejected = false;
        try { $generator->generate($definition, $plan['confirmToken']); }
        catch (\Throwable) { $rejected = true; }
        self::assertTrue($rejected, '文件漂移必须阻断');
        self::assertSame('manual', file_get_contents($path));
    }
}
