<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\service\FormCrudDefinitionFactory;
use app\common\crud\AtomicWriter;
use app\common\crud\ConfirmationToken;
use app\common\crud\CrudGenerator;
use app\common\crud\GenerationPlanner;
use PHPUnit\Framework\TestCase;

final class CrudFrontendWorkspaceTest extends TestCase
{
    private string $workspace;
    private string $backend;
    private string $frontend;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/crud-workspace-' . bin2hex(random_bytes(6));
        $this->backend = $this->workspace . '/backend';
        $this->frontend = $this->workspace . '/web';
        mkdir($this->backend, 0700, true);
        mkdir($this->frontend . '/src/admin/views/business', 0700, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->workspace, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->workspace);
    }

    public function test_factory_generation_writes_into_sibling_frontend_and_detects_its_local_edits(): void
    {
        $definition = (new FormCrudDefinitionFactory())->create([
            'form_key' => 'workspace_product', 'name' => 'Product', 'table_name' => 'fun_workspace_product', 'connection' => 'mysql',
            'fields' => [['field_name' => 'title', 'label' => 'Title', 'type' => 'input', 'column_type' => 'varchar(80)', 'nullable' => 0]],
        ]);
        $generator = new CrudGenerator($this->backend);
        $plan = $generator->plan($definition);
        $generator->generate($definition, $plan['confirmToken']);
        self::assertFileExists($this->frontend . '/src/admin/api/business/workspace-product.ts');
        self::assertFileExists($this->frontend . '/src/admin/views/business/workspace-product/index.vue');
        self::assertFileExists($this->backend . '/app/admin/business/workspace_product/controller/WorkspaceProductController.php');
        self::assertDirectoryDoesNotExist($this->backend . '/web');
        file_put_contents($this->frontend . '/src/admin/views/business/workspace-product/index.vue', 'local edit');
        $next = $generator->plan($definition);
        $view = array_column($next['files'], null, 'path')['web/src/admin/views/business/workspace-product/index.vue'];
        self::assertSame('conflict', $view['status']);
        self::assertSame(hash('sha256', 'local edit'), $view['previousHash']);
    }

    public function test_atomic_failure_restores_existing_frontend_and_backend_files(): void
    {
        mkdir($this->frontend . '/src/admin/api/business', 0700, true);
        file_put_contents($this->frontend . '/src/admin/api/business/product.ts', 'old api');
        file_put_contents($this->backend . '/backend.txt', 'old backend');
        $tokens = new ConfirmationToken($this->backend, 'workspace-test');
        $files = ['web/src/admin/api/business/product.ts' => 'new api', 'backend.txt' => 'new backend'];
        $plan = (new GenerationPlanner($this->backend, $tokens))->plan(CrudDefinitionFixture::make(), $files);
        $writer = new AtomicWriter($this->backend, static function (array $file): void {
            if ($file['path'] === 'backend.txt') throw new \RuntimeException('injected after both writes');
        }, $tokens);
        try {
            $writer->write($plan, $plan['confirmToken'], array_keys($files));
            self::fail('Injected failure must rollback');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('injected after both writes', $error->getMessage());
        }
        self::assertSame('old api', file_get_contents($this->frontend . '/src/admin/api/business/product.ts'));
        self::assertSame('old backend', file_get_contents($this->backend . '/backend.txt'));
    }

    public function test_frontend_symlink_cannot_redirect_generated_files(): void
    {
        mkdir($this->workspace . '/outside', 0700);
        symlink($this->workspace . '/outside', $this->frontend . '/src/admin/views/business/product');
        $this->expectException(\InvalidArgumentException::class);
        (new GenerationPlanner($this->backend))->plan(CrudDefinitionFixture::make(), [
            'web/src/admin/views/business/product/index.vue' => 'unsafe',
        ]);
    }

    public function test_preview_cannot_be_reused_after_frontend_root_changes(): void
    {
        $tokens = new ConfirmationToken($this->backend, 'workspace-root-binding');
        $plan = (new GenerationPlanner($this->backend, $tokens))->plan(CrudDefinitionFixture::make(), [
            'web/src/admin/api/business/product.ts' => 'api',
        ]);
        rename($this->frontend, $this->workspace . '/previous-frontend');
        mkdir($this->backend . '/web', 0700, true);
        try {
            (new AtomicWriter($this->backend, tokens: $tokens))->write($plan, $plan['confirmToken']);
            self::fail('A changed frontend root must invalidate the preview');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('生成源码目录已变化', $error->getMessage());
        }
        self::assertFileDoesNotExist($this->backend . '/web/src/admin/api/business/product.ts');
    }

    public function test_managed_recovery_restores_backend_and_sibling_frontend_after_interruption(): void
    {
        self::assertSame(':memory:', \support\Db::connection()->getDatabaseName());
        BusinessGenerationFixture::create();
        try {
            $ids = BusinessGenerationFixture::seed();
            mkdir($this->frontend . '/src/admin/api/business', 0700, true);
            $frontendPath = 'web/src/admin/api/business/product.ts';
            file_put_contents($this->frontend . '/src/admin/api/business/product.ts', 'old api');
            file_put_contents($this->backend . '/example.php', 'old backend');
            $contents = [$frontendPath => 'new api', 'example.php' => 'new backend'];
            $plan = (new \app\common\crud\ThreeWayMergePlanner($this->backend))->plan([
                ['path' => $frontendPath, 'artifactType' => 'api', 'baseContent' => 'old api', 'remoteContent' => 'new api'],
                ['path' => 'example.php', 'artifactType' => 'model', 'baseContent' => 'old backend', 'remoteContent' => 'new backend'],
            ]);
            $plan['definitionHash'] = str_repeat('a', 64);
            $bundle = ['plan' => $plan, 'remoteContents' => $contents, 'mergedTextContents' => $contents,
                'definitionHash' => str_repeat('a', 64), 'schemaHash' => str_repeat('b', 64), 'registryHash' => str_repeat('c', 64),
                'templateVersion' => 'native', 'migrationHash' => str_repeat('d', 64), 'resourcesHash' => hash('sha256', '[]'), 'resources' => []];
            $state = new \app\admin\development\repository\DatabaseGenerationStateRepository();
            $resources = new \app\admin\development\service\GenerationResourceTransaction();
            $tokens = new ConfirmationToken($this->backend);
            $service = new \app\admin\development\service\GenerationTransactionService(
                $this->backend, $tokens, new \app\admin\development\repository\GeneratedFileBaselineRepository($this->backend, $state),
                $state, $resources, static fn (): array => $bundle,
                static function (string $checkpoint): void {
                    if ($checkpoint === 'after_files_written') throw new \app\admin\development\exception\GenerationInterruptionException('interrupted');
                }
            );
            try {
                $service->execute(...[...$ids, $bundle, $tokens->issue($service::bundleDigest($bundle)), fn (): bool => $state->claimGeneration(...[...$ids, 'test'])]);
                self::fail('Generation must interrupt');
            } catch (\app\admin\development\exception\GenerationInterruptionException) { $resources->rollback(); }
            self::assertSame('new api', file_get_contents($this->frontend . '/src/admin/api/business/product.ts'));
            self::assertSame('new backend', file_get_contents($this->backend . '/example.php'));
            $journal = glob($this->backend . '/runtime/private/business-development/wal/*.json')[0];
            $recovery = \app\admin\development\service\GenerationTransactionService::production($this->backend);
            rename($this->frontend, $this->workspace . '/previous-frontend');
            mkdir($this->backend . '/web/src/admin/api/business', 0700, true);
            file_put_contents($this->backend . '/web/src/admin/api/business/product.ts', 'new api');
            try {
                $recovery->recover(basename($journal, '.json'));
                self::fail('A changed frontend root must block recovery');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('生成源码目录已变化', $error->getMessage());
            }
            self::assertSame('new backend', file_get_contents($this->backend . '/example.php'));
            self::assertSame('new api', file_get_contents($this->backend . '/web/src/admin/api/business/product.ts'));
            rename($this->workspace . '/previous-frontend', $this->frontend);
            self::assertSame('rolled_back', $recovery->recover(basename($journal, '.json'))['state']);
            self::assertSame('old api', file_get_contents($this->frontend . '/src/admin/api/business/product.ts'));
            self::assertSame('old backend', file_get_contents($this->backend . '/example.php'));
        } finally { BusinessGenerationFixture::drop(); }
    }
}
