<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\PermissionCode;
use app\admin\development\controller\Business;
use app\admin\development\service\BusinessDevelopmentService;
use app\common\service\runtime\WorkerReloader;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;
use ZipArchive;

/** 下载 zip：内容为正式生成预览的 remoteContent，不含 delete 状态文件，临时文件在响应前删除。 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BusinessGenerationArchiveTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::destroy();
    }

    public function test_permission_code_is_derived_from_action(): void
    {
        self::assertSame('development:business:formal-generation-archive', PermissionCode::derive(Business::class, 'formalGenerationArchive'));
    }

    public function test_archive_contains_every_previewed_file_with_its_remote_content(): void
    {
        $this->createMetadataTables();
        $service = BusinessDevelopmentService::production(base_path(), ['sqlite']);
        $moduleId = $this->adoptPublished($service, 'archive_fixture');
        $before = $this->temporaryArchives();

        $archive = $service->formalGenerationArchive($moduleId, 'archive_nonce_01');
        $preview = $service->previewFormalGeneration($moduleId, true, 'archive_nonce_01');

        $expected = [];
        foreach ($preview['plan']['files'] as $file) {
            if ($file['status'] !== 'delete' && is_string($file['remoteContent'] ?? null)) $expected[$file['path']] = $file['remoteContent'];
        }
        self::assertNotEmpty($expected);
        self::assertMatchesRegularExpression('/^archive_fixture-\d{14}\.zip$/', $archive['filename']);
        self::assertSame([], $archive['skipped']);
        $entries = $this->entries($archive['content']);
        ksort($expected);
        ksort($entries);
        self::assertSame($expected, $entries);
        self::assertSame($before, $this->temporaryArchives());
    }

    public function test_builder_excludes_deleted_files_and_skips_files_without_text_content(): void
    {
        $before = $this->temporaryArchives();
        $archive = BusinessDevelopmentService::buildGenerationArchive([
            ['path' => 'app/admin/generated/a/A.php', 'status' => 'create', 'remoteContent' => "<?php\n// a\n"],
            ['path' => 'web/src/admin/views/generated/a/index.vue', 'status' => 'update', 'remoteContent' => '<template />'],
            ['path' => 'app/admin/generated/a/Old.php', 'status' => 'delete', 'remoteContent' => 'stale'],
            ['path' => 'web/public/generated/a/logo.png', 'status' => 'create', 'contentKind' => 'binary'],
            ['path' => '../outside.php', 'status' => 'create', 'remoteContent' => 'escape'],
            ['path' => '/etc/passwd', 'status' => 'create', 'remoteContent' => 'escape'],
        ]);

        self::assertSame([
            'app/admin/generated/a/A.php' => "<?php\n// a\n",
            'web/src/admin/views/generated/a/index.vue' => '<template />',
        ], $this->entries($archive['content']));
        self::assertSame(['web/public/generated/a/logo.png', '../outside.php', '/etc/passwd'], $archive['skipped']);
        self::assertSame($before, $this->temporaryArchives());
    }

    public function test_builder_rejects_plan_without_any_archivable_file(): void
    {
        $before = $this->temporaryArchives();
        try {
            BusinessDevelopmentService::buildGenerationArchive([['path' => 'a.php', 'status' => 'delete', 'remoteContent' => 'x']]);
            self::fail('空计划必须拒绝');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('没有可打包的生成文件', $exception->getMessage());
        }
        self::assertSame($before, $this->temporaryArchives());
    }

    public function test_controller_requires_generate_permission(): void
    {
        $this->actAs(0);
        $response = (new Business())->formalGenerationArchive(1);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringNotContainsString('application/zip', (string) $response->getHeader('Content-Type'));
    }

    public function test_controller_responds_with_zip_download(): void
    {
        $this->createMetadataTables();
        $moduleId = $this->adoptPublished(BusinessDevelopmentService::production(base_path(), ['sqlite']), 'archive_http');
        $before = $this->temporaryArchives();
        $this->actAs(1);

        $response = (new Business())->formalGenerationArchive($moduleId);

        self::assertSame(200, $response->getStatusCode(), $response->rawBody());
        self::assertSame('application/zip', $response->getHeader('Content-Type'));
        self::assertMatchesRegularExpression('/^attachment; filename="archive_http-\d{14}\.zip"$/', (string) $response->getHeader('Content-Disposition'));
        self::assertSame('0', $response->getHeader('X-Archive-Skipped'));
        self::assertArrayHasKey('app/admin/generated/archive_http/model/ArchiveHttp.php', $this->entries($response->rawBody()));
        self::assertSame($before, $this->temporaryArchives());
    }

    /** spec 4.7：下载会创建或复用生成记录，必须走带 CSRF 的 POST。 */
    public function test_archive_route_is_post(): void
    {
        $attributes = (new \ReflectionMethod(Business::class, 'formalGenerationArchive'))->getAttributes();
        $verbs = array_map(static fn (\ReflectionAttribute $attribute): string => $attribute->getName(), $attributes);
        self::assertContains(\support\annotation\route\Post::class, $verbs);
        self::assertNotContains(\support\annotation\route\Get::class, $verbs);
    }

    /** 预览弹窗打开时下载 zip：复用预览 nonce，已打开预览的生成记录仍为 planned，不被 superseded。 */
    public function test_controller_archive_with_preview_nonce_keeps_open_preview_planned(): void
    {
        $this->createMetadataTables();
        $service = BusinessDevelopmentService::production(base_path(), ['sqlite']);
        $moduleId = $this->adoptPublished($service, 'archive_nonce_http');
        $this->actAs(1);
        $preview = $service->previewFormalGeneration($moduleId, true, 'dialog_nonce_0001');
        $this->actAs(1, ['nonce' => 'dialog_nonce_0001']);

        $response = (new Business())->formalGenerationArchive($moduleId);

        self::assertSame(200, $response->getStatusCode(), $response->rawBody());
        self::assertSame('application/zip', $response->getHeader('Content-Type'), $response->rawBody());
        self::assertSame('planned', Db::table('crud_generation')->where('id', $preview['generationId'])->value('status'));
        self::assertSame(1, Db::table('crud_generation')->where('business_module_id', $moduleId)->count());
    }

    /** preview(nonce) → archive(nonce) → 生成记录仍为 planned → 正式生成成功（隔离项目根，不写真实项目）。 */
    public function test_preview_archive_then_formal_generation_succeeds(): void
    {
        $this->createMetadataTables();
        $root = sys_get_temp_dir() . '/funadmin-archive-flow-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        try {
            $service = $this->isolatedService($root);
            $moduleId = $this->adoptPublished($service, 'archive_flow');
            $this->actAs(1);
            $preview = $service->previewFormalGeneration($moduleId, true, 'dialog_nonce_0002');
            $archive = $service->formalGenerationArchive($moduleId, 'dialog_nonce_0002');

            self::assertNotEmpty($archive['entries']);
            self::assertSame('planned', Db::table('crud_generation')->where('id', $preview['generationId'])->value('status'));
            $result = $service->formalGeneration($moduleId, (int) $preview['generationId'], (string) $preview['sensitive']['confirmToken']);
            self::assertSame((int) $preview['generationId'], (int) $result['generationId']);
            self::assertSame('completed', Db::table('crud_generation')->where('id', $preview['generationId'])->value('status'));
            self::assertFileExists($root . '/app/admin/generated/archive_flow/model/ArchiveFlow.php');
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }

    /**
     * 批量生成：除最后一个模块外都带 deferReload=true，跳过重载并返回 reloadReason=deferred；
     * deferReload=false（或缺省）照常请求重载。重载器注入假信号发送器，不触碰真实 webman 主进程。
     */
    public function test_formal_generation_defer_reload_skips_worker_reload(): void
    {
        $this->createMetadataTables();
        $root = sys_get_temp_dir() . '/funadmin-defer-reload-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        $pidFile = $root . '/webman.pid';
        file_put_contents($pidFile, '4321');
        $signals = [];
        $reloader = new WorkerReloader($pidFile, static function (int $pid, int $signal) use (&$signals): bool {
            $signals[] = [$pid, $signal];
            return true;
        });
        try {
            $service = $this->isolatedService($root, $reloader);
            $this->actAs(1);

            $deferredId = $this->adoptPublished($service, 'defer_reload_a');
            $preview = $service->previewFormalGeneration($deferredId, true, 'defer_nonce_0001');
            $deferred = $service->formalGeneration($deferredId, (int) $preview['generationId'], (string) $preview['sensitive']['confirmToken'], ['deferReload' => true]);
            self::assertSame('completed', $deferred['state']);
            self::assertFalse($deferred['reloadRequested']);
            self::assertSame('deferred', $deferred['reloadReason']);
            self::assertSame([], $signals, 'deferReload=true 不得发送任何信号');

            $lastId = $this->adoptPublished($service, 'defer_reload_b');
            $preview = $service->previewFormalGeneration($lastId, true, 'defer_nonce_0002');
            $last = $service->formalGeneration($lastId, (int) $preview['generationId'], (string) $preview['sensitive']['confirmToken'], ['deferReload' => false]);
            self::assertSame('completed', $last['state']);
            self::assertTrue($last['reloadRequested']);
            self::assertNull($last['reloadReason']);
            self::assertContains([4321, WorkerReloader::reloadSignal()], $signals);
        } finally {
            $this->removeTree($root);
        }
    }

    public function test_formal_generation_rejects_non_boolean_defer_reload_before_executing(): void
    {
        $this->createMetadataTables();
        $root = sys_get_temp_dir() . '/funadmin-defer-invalid-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        try {
            $service = $this->isolatedService($root, new WorkerReloader($root . '/missing.pid', static fn (): bool => true));
            $this->actAs(1);
            $moduleId = $this->adoptPublished($service, 'defer_reload_bad');
            $preview = $service->previewFormalGeneration($moduleId, true, 'defer_nonce_0003');
            foreach (['1', 1, 'true', null] as $value) {
                try {
                    $service->formalGeneration($moduleId, (int) $preview['generationId'], (string) $preview['sensitive']['confirmToken'], ['deferReload' => $value]);
                    self::fail('deferReload 必须为布尔值');
                } catch (\InvalidArgumentException $exception) {
                    self::assertSame('deferReload 不合法', $exception->getMessage());
                }
            }
            self::assertSame('planned', Db::table('crud_generation')->where('id', $preview['generationId'])->value('status'));
        } finally {
            $this->removeTree($root);
        }
    }

    private function removeTree(string $root): void
    {
        if (!is_dir($root)) return;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($root);
    }

    /** 与 production 相同的组装，但项目根为临时目录、菜单/权限资源事务替换为空实现；重载器默认指向不存在的 pid 文件。 */
    private function isolatedService(string $root, ?WorkerReloader $reloader = null): BusinessDevelopmentService
    {
        $schemas = new \app\admin\form\repository\FormSchemaRepository();
        $forms = new \app\admin\form\service\FormDesignerService($root, $schemas);
        $targets = new \app\admin\development\service\BusinessTargetService($root, 'sqlite');
        $resources = new class {
            public function begin(): void {}
            public function apply(array $resources): array { return ['inserted' => 0, 'skipped' => 0]; }
            public function commit(): void {}
            public function rollback(): void {}
        };
        return new BusinessDevelopmentService(
            new \app\admin\development\service\BusinessModuleService($targets),
            $forms,
            $schemas,
            new \app\admin\form\service\FormPublishService($forms, $schemas),
            new \app\admin\development\service\ManagedGenerationService($root, resources: $resources, schemas: $schemas),
            new \app\admin\form\service\FormDataService(),
            new \app\admin\development\service\DevCrudService($root, ['sqlite']),
            new \app\common\form\registry\FieldCapabilityRegistry(),
            $targets,
            $reloader ?? new WorkerReloader($root . '/no-webman.pid', static fn (): bool => false)
        );
    }

    private function actAs(int $adminId, array $body = []): void
    {
        $json = json_encode((object) $body, JSON_THROW_ON_ERROR);
        $request = new Request("POST /admin/development/business/modules/1/formal-generation/archive HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n\r\n" . $json);
        $request->context['admin'] = ['id' => $adminId, 'username' => 'archive-test'];
        // 预览记录 actor 时读取 session；测试请求没有真实连接，补一个空会话
        $request->connection = (new \ReflectionClass(\Workerman\Connection\TcpConnection::class))->newInstanceWithoutConstructor();
        $request->context['session'] = new \Workerman\Protocols\Http\Session(bin2hex(random_bytes(16)));
        Context::set(\Webman\Http\Request::class, $request);
    }

    /** @return array<string, string> */
    private function entries(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'archive-test-');
        try {
            file_put_contents($path, $content);
            $zip = new ZipArchive();
            self::assertTrue($zip->open($path) === true);
            $entries = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                $entries[$name] = (string) $zip->getFromIndex($index);
            }
            $zip->close();
            return $entries;
        } finally {
            unlink($path);
        }
    }

    /** @return list<string> */
    private function temporaryArchives(): array
    {
        $files = glob(sys_get_temp_dir() . '/funadmin-codegen-*') ?: [];
        sort($files);
        return $files;
    }

    private function adoptPublished(BusinessDevelopmentService $service, string $table): int
    {
        Db::statement("CREATE TABLE $table (id integer primary key autoincrement not null, title varchar(100) not null, created_at datetime, updated_at datetime)");
        $inspection = $service->inspectDatabase('sqlite', $table);
        $detail = $service->createFromDatabase([
            'code' => $table, 'name' => '归档测试', 'table' => $table, 'connection' => 'sqlite',
            'target' => ['type' => 'core'], 'expectedInspectionHash' => $inspection['snapshotHash'],
        ], 'test:1');
        // 正式生成读取已发布版本；测试直接把当前 Schema 标为已发布
        Db::table('form')->where('id', (int) $detail['module']['form_id'])->update(['published_schema_hash' => Db::raw('schema_hash')]);
        return (int) $detail['module']['id'];
    }

    private function createMetadataTables(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->create('form', static function (Blueprint $table): void {
            $table->id(); $table->string('form_key')->unique(); $table->string('name');
            $table->string('table_name'); $table->string('connection'); $table->string('source_type');
            $table->integer('status')->default(1); $table->integer('schema_version')->default(2);
            foreach (['list_config', 'form_config', 'schema_document', 'publish_config'] as $field) $table->json($field)->nullable();
            foreach (['schema_hash', 'published_schema_hash', 'schema_origin', 'publish_status', 'remark'] as $field) $table->string($field)->nullable();
            $table->integer('sort_order')->default(0); $table->unsignedBigInteger('business_module_id')->nullable();
            // 正式生成完成后回写的发布状态列
            foreach (['publish_mode', 'published_definition_hash'] as $field) $table->string($field)->nullable();
            $table->dateTime('published_at')->nullable(); $table->unsignedBigInteger('crud_generation_id')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('form_field', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id'); $table->string('field_name'); $table->string('label');
            foreach (['type', 'column_type', 'index_type', 'relation_type', 'relation_table', 'relation_label_field', 'relation_value_field', 'relation_foreign_key', 'relation_on_delete', 'default_value', 'placeholder', 'help_text', 'remote_api', 'comment', 'list_filter', 'list_formatter', 'form_group'] as $field) $table->string($field)->nullable();
            foreach (['nullable', 'list_show', 'list_sort', 'list_search', 'form_show', 'form_required', 'form_readonly', 'form_disabled', 'sort_order', 'unsigned', 'relation_multiple', 'list_width', 'form_span'] as $field) $table->integer($field)->default(0);
            foreach (['options_source', 'control_props', 'validate_rules', 'link_rules'] as $field) $table->json($field)->nullable();
            $table->timestamps();
        });
        Db::schema()->create('form_schema_version', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id'); $table->integer('version'); $table->integer('schema_version');
            $table->string('schema_hash'); $table->json('schema_document');
            $table->string('origin'); $table->unsignedBigInteger('parent_version_id')->nullable();
            $table->string('change_summary'); $table->string('created_by'); $table->timestamps();
        });
        Db::schema()->create('business_module', static function (Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('name'); $table->unsignedBigInteger('form_id');
            foreach (['origin', 'connection_name', 'table_name', 'runtime_route', 'module_route', 'lifecycle_status', 'generation_status'] as $field) $table->string($field);
            $table->json('metadata'); $table->timestamps(); $table->softDeletes();
            // 正式生成完成后回写的发布状态列
            $table->string('published_schema_hash')->nullable(); $table->integer('published_schema_version')->nullable();
            $table->unsignedBigInteger('current_generation_id')->nullable(); $table->unsignedBigInteger('last_success_generation_id')->nullable();
        });
        Db::schema()->create('crud_generation', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('business_module_id'); $table->unsignedBigInteger('form_id')->default(0);
            foreach (['binding_status', 'generation_mode', 'operation', 'connection_name', 'table_name'] as $field) $table->string($field)->nullable();
            $table->string('operation_key')->unique(); $table->string('status'); $table->string('recovery_status')->default('none');
            $table->string('definition_hash', 64); $table->json('definition')->nullable(); $table->json('manifest')->nullable();
            $table->json('result')->nullable(); $table->json('error')->nullable(); $table->string('actor')->nullable();
            $table->string('transaction_id', 32)->nullable(); $table->string('plan_digest', 64)->nullable();
            $table->string('failure_code')->nullable(); $table->unsignedBigInteger('superseded_by_id')->nullable();
            foreach (['started_at', 'failed_at', 'completed_at', 'recovered_at'] as $field) $table->dateTime($field)->nullable();
            $table->timestamps();
        });
        Db::schema()->create('generated_file_baseline', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('business_module_id'); $table->unsignedBigInteger('generation_id');
            foreach (['relative_path', 'artifact_type', 'base_hash', 'base_storage_path', 'target_hash', 'template_version', 'definition_hash', 'content_kind', 'status'] as $field) $table->string($field)->nullable();
            $table->unique(['business_module_id', 'relative_path']); $table->timestamps(); $table->softDeletes();
        });
    }
}
