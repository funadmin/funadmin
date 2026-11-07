<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\PermissionCode;
use app\admin\development\controller\Business;
use app\admin\development\service\BusinessDevelopmentService;
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

    private function actAs(int $adminId): void
    {
        $request = new Request("GET /admin/development/business/modules/1/formal-generation/archive HTTP/1.1\r\n\r\n");
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
