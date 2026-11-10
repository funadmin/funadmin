<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugin\command\PluginMigrate;
use app\common\plugin\sdk\PluginScaffolder;
use app\common\service\database\MigrationService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * plugin:migrate --adopt：迁移效果已由运行时 DDL 落库时只登记不执行；任一效果缺失、不一致或语句不可验证即拒绝。
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class PluginMigrateAdoptTest extends TestCase
{
    private const CODE = 'adoptfix';
    private const BASE = "-- adoptfix 1.0.0\n"
        . "create table `fun_adoptfix_item` (`id` bigint unsigned not null auto_increment primary key, `code` varchar(64) not null, `title` varchar(255) not null default '', `meta` json null, `created_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;\n"
        . "alter table `fun_adoptfix_item` add unique `adoptfix_item_code_unique`(`code`);\n";
    private const EXTEND = "-- adoptfix 1.1.0; generated from Schema.php.\n"
        . "alter table `fun_adoptfix_item` add `fingerprint` char(64) not null default '' after `title`;\n"
        . "alter table `fun_adoptfix_item` add index `adoptfix_item_fingerprint_idx`(`code`, `fingerprint`);\n";
    private string $root;
    private bool $ready = false;

    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $this->root = sys_get_temp_dir() . '/funadmin-adopt-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        $this->ready = true;
        (new MigrationService())->run();
        foreach (['plugin_operation', 'plugin', 'system_migration'] as $table) Db::schema()->dropIfExists($table);
        Db::schema()->create('system_migration', static function (Blueprint $table): void { $table->id(); $table->string('scope'); $table->string('version'); $table->string('checksum'); $table->unsignedBigInteger('executed_at'); });
        Db::schema()->create('plugin', static function (Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('name')->default(''); $table->string('version')->default('');
            $table->string('lifecycle_state')->default('discovered'); $table->boolean('status')->default(false);
            $table->string('db_version')->nullable(); $table->boolean('migration_pending')->default(false);
            $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('plugin_operation', static function (Blueprint $table): void {
            $table->id(); foreach (['plugin_code', 'operation', 'operation_token', 'stage', 'from_version', 'to_version', 'source', 'package_hash', 'result'] as $name) $table->string($name);
            $table->integer('progress'); $table->integer('status')->default(1); $table->text('error_message')->nullable(); $table->text('recovery_path')->nullable();
            $table->json('metadata')->nullable(); $table->timestamps();
        });
        (new PluginScaffolder($this->root . '/plugins'))->scaffold(self::CODE);
        $this->migration('001_base', self::BASE);
        $this->migration('002_extend', self::EXTEND);
        Db::table('plugin')->insert(['code' => self::CODE, 'version' => '1.1.0', 'lifecycle_state' => 'failed', 'db_version' => '001_base', 'migration_pending' => 1]);
    }

    protected function tearDown(): void
    {
        if (!$this->ready) return;
        foreach (['plugin_operation', 'plugin', 'system_migration', 'adoptfix_item'] as $table) Db::schema()->dropIfExists($table);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_adopt_records_runner_checksum_only_when_every_effect_exists(): void
    {
        $this->liveSchema();
        $this->record('001_base', self::BASE);
        $command = new CommandTester(new PluginMigrate($this->root));

        self::assertSame(0, $command->execute(['code' => self::CODE, '--adopt' => '002_extend', '--dry-run' => true]), $command->getDisplay());
        $plan = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($plan['dryRun']);
        self::assertTrue($plan['adoptable']);
        self::assertFalse($plan['recorded']);
        self::assertSame(hash('sha256', self::EXTEND), $plan['checksum']);
        self::assertSame(['present', 'present'], array_column($plan['effects'], 'status'));
        self::assertSame(['column', 'index'], array_column($plan['effects'], 'kind'));
        self::assertSame(1, Db::table('system_migration')->count(), 'dry-run 不写库');
        self::assertSame(0, Db::table('plugin_operation')->count());

        self::assertSame(0, $command->execute(['code' => self::CODE, '--adopt' => '002_extend.sql']), $command->getDisplay());
        $row = Db::table('system_migration')->where('version', '002_extend')->first();
        self::assertNotNull($row);
        self::assertSame('plugin:' . self::CODE, $row->scope);
        // 与 MigrationService 执行时登记的 checksum 完全相同：文件原文（前缀改写之前）的 sha256。
        self::assertSame(hash_file('sha256', $this->file('002_extend')), $row->checksum);
        self::assertGreaterThan(0, (int) $row->executed_at);
        $audit = Db::table('plugin_operation')->where('operation', 'migration_adopt')->first();
        self::assertNotNull($audit);
        self::assertSame('002_extend', json_decode((string) $audit->metadata, true)['version']);
        // 接管只登记 system_migration，不改插件生命周期状态；随后的更新/迁移会跳过已登记版本。
        self::assertSame('failed', Db::table('plugin')->value('lifecycle_state'));
        self::assertSame('002_extend', (new MigrationService())->latestAppliedVersion('plugin:' . self::CODE));

        // 重复接管是无操作；登记 checksum 与文件不一致时拒绝。
        self::assertSame(0, $command->execute(['code' => self::CODE, '--adopt' => '002_extend']), $command->getDisplay());
        self::assertTrue(json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR)['alreadyRecorded']);
        self::assertSame(2, Db::table('system_migration')->count());
        Db::table('system_migration')->where('version', '002_extend')->update(['checksum' => str_repeat('0', 64)]);
        self::assertSame(1, $command->execute(['code' => self::CODE, '--adopt' => '002_extend']));
        self::assertStringContainsString('内容发生变化', $command->getDisplay());
    }

    public function test_create_table_adoption_checks_table_columns_and_indexes(): void
    {
        $this->liveSchema();
        $command = new CommandTester(new PluginMigrate($this->root));
        self::assertSame(0, $command->execute(['code' => self::CODE, '--adopt' => '001_base', '--dry-run' => true]), $command->getDisplay());
        $plan = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($plan['adoptable'], $command->getDisplay());
        $kinds = array_count_values(array_column($plan['effects'], 'kind'));
        self::assertSame(['table' => 1, 'column' => 5, 'index' => 2], $kinds);
        self::assertSame(0, $command->execute(['code' => self::CODE, '--adopt' => '001_base']), $command->getDisplay());
        self::assertSame(hash('sha256', self::BASE), Db::table('system_migration')->where('version', '001_base')->value('checksum'));
    }

    public static function brokenSchemas(): array
    {
        return [
            'column missing' => ['no-column', 'fingerprint'],
            'column type incompatible' => ['int-column', 'fingerprint'],
            'column nullability differs' => ['nullable-column', 'fingerprint'],
            'index missing' => ['no-index', 'code, fingerprint'],
            'index has other columns' => ['other-index', 'code, fingerprint'],
            'index uniqueness differs' => ['unique-index', 'code, fingerprint'],
            'table missing' => ['no-table', 'adoptfix_item'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('brokenSchemas')]
    public function test_adopt_refuses_and_records_nothing_when_any_effect_is_missing(string $variant, string $mentioned): void
    {
        $this->liveSchema($variant);
        $command = new CommandTester(new PluginMigrate($this->root));
        self::assertSame(0, $command->execute(['code' => self::CODE, '--adopt' => '002_extend', '--dry-run' => true]), $command->getDisplay());
        $plan = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($plan['adoptable']);
        self::assertContains('missing', array_column($plan['effects'], 'status'));

        self::assertSame(1, $command->execute(['code' => self::CODE, '--adopt' => '002_extend']));
        self::assertStringContainsString('拒绝接管', $command->getDisplay());
        self::assertStringContainsString($mentioned, $command->getDisplay());
        self::assertSame(0, Db::table('system_migration')->count());
        self::assertSame(0, Db::table('plugin_operation')->count());
    }

    public static function unsupportedMigrations(): array
    {
        return [
            'data change' => ["update `fun_adoptfix_item` set `title` = 'x';\n"],
            'column change' => ["alter table `fun_adoptfix_item` modify `title` varchar(100) not null;\n"],
            'mixed with supported' => ["alter table `fun_adoptfix_item` add `fingerprint` char(64) not null default '', add constraint `fk_x` foreign key (`code`) references `fun_other` (`code`);\n"],
            'foreign key in create' => ["create table `fun_adoptfix_item` (`id` bigint unsigned not null, `code` varchar(64) not null, foreign key (`code`) references `fun_other` (`code`));\n"],
            'create table as select' => ["create table `fun_adoptfix_item` select * from `fun_other`;\n"],
            'generated column' => ["alter table `fun_adoptfix_item` add `upper_code` varchar(64) as (upper(`code`)) stored;\n"],
            'dynamic sql' => ["SET @s = 'alter table `fun_adoptfix_item` add `x` int';\nPREPARE stmt FROM @s;\nEXECUTE stmt;\n"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsupportedMigrations')]
    public function test_adopt_refuses_statements_it_cannot_verify(string $sql): void
    {
        $this->liveSchema();
        $this->migration('003_change', $sql);
        $command = new CommandTester(new PluginMigrate($this->root));
        self::assertSame(0, $command->execute(['code' => self::CODE, '--adopt' => '003_change', '--dry-run' => true]), $command->getDisplay());
        $plan = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($plan['adoptable']);
        self::assertContains('unsupported', array_column($plan['effects'], 'status'));
        self::assertSame(1, $command->execute(['code' => self::CODE, '--adopt' => '003_change']));
        self::assertStringContainsString('拒绝接管', $command->getDisplay());
        self::assertSame(0, Db::table('system_migration')->count());
    }

    public function test_adopt_keeps_runner_preflight_and_requires_explicit_installed_target(): void
    {
        $this->liveSchema();
        $command = new CommandTester(new PluginMigrate($this->root));
        // 只向前守卫与执行路径相同：破坏性文件连 dry-run 都拒绝。
        $this->migration('003_drop', "alter table `fun_adoptfix_item` drop `title`;\n");
        self::assertSame(1, $command->execute(['code' => self::CODE, '--adopt' => '003_drop', '--dry-run' => true]));
        self::assertStringContainsString('破坏性', $command->getDisplay());
        self::assertSame(1, $command->execute(['code' => self::CODE, '--adopt' => '../001_base']));
        self::assertSame(1, $command->execute(['code' => self::CODE, '--adopt' => '009_missing']));
        self::assertStringContainsString('不存在', $command->getDisplay());
        // 必须显式给出 --adopt；普通迁移经插件更新/迁移流程执行。
        self::assertSame(1, $command->execute(['code' => self::CODE]));
        self::assertStringContainsString('--adopt', $command->getDisplay());
        Db::table('plugin')->delete();
        self::assertSame(1, $command->execute(['code' => self::CODE, '--adopt' => '002_extend']));
        self::assertStringContainsString('尚未安装', $command->getDisplay());
        self::assertSame(0, Db::table('system_migration')->count());
    }

    /** 今天开发库上的真实情形：CMS 旧版 enabled() 先建好了 005/006 的列与索引。 */
    public function test_cms_migrations_are_adoptable_against_schema_created_by_former_enable_hooks(): void
    {
        foreach (array_merge(\app\cms\service\Schema::tables(), \app\cms\service\SiteRoleSchema::tables(), \app\cms\service\FormSchema::tables(), ['cms_member_submission']) as $table) Db::schema()->dropIfExists($table);
        \app\cms\service\Schema::install();
        \app\cms\service\SiteRoleSchema::install();
        \app\cms\service\ContributionSchema::install();
        \app\cms\service\TemplateTagRemarkSchema::install();
        \app\cms\service\FormSchema::install();
        \app\cms\service\FormFingerprintSchema::install();
        \app\cms\service\PageFormSchema::install();
        Db::table('plugin')->insert(['code' => 'cms', 'version' => '1.5.1', 'lifecycle_state' => 'failed']);
        $command = new CommandTester(new PluginMigrate());
        foreach (['001_cms', '002_template_tag_remark', '004_forms', '005_form_fingerprint', '006_page_form'] as $version) {
            self::assertSame(0, $command->execute(['code' => 'cms', '--adopt' => $version, '--dry-run' => true]), $command->getDisplay());
            $plan = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($plan['adoptable'], $version . ': ' . $command->getDisplay());
            self::assertSame(hash_file('sha256', base_path('plugins/cms/database/migrations/' . $version . '.sql')), $plan['checksum']);
        }
        // 003 是数据清理，无法从结构证明“已生效”，必须真正执行。
        self::assertSame(0, $command->execute(['code' => 'cms', '--adopt' => '003_remove_legacy_site_role', '--dry-run' => true]));
        self::assertFalse(json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR)['adoptable']);
        // 列存在但 006 的另一半缺失：拒绝。
        Db::schema()->table('cms_category_locale', static fn (Blueprint $t) => $t->dropColumn('form_code'));
        self::assertSame(0, $command->execute(['code' => 'cms', '--adopt' => '006_page_form', '--dry-run' => true]));
        $plan = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($plan['adoptable']);
        self::assertSame(['present', 'missing'], array_column($plan['effects'], 'status'));
        self::assertSame(0, Db::table('system_migration')->count());
    }

    private function liveSchema(string $variant = ''): void
    {
        if ($variant === 'no-table') return;
        Db::schema()->create('adoptfix_item', static function (Blueprint $t) use ($variant): void {
            $t->id();
            $t->string('code', 64);
            $t->string('title', 255)->default('');
            match ($variant) {
                'no-column' => null,
                'int-column' => $t->integer('fingerprint')->default(0),
                'nullable-column' => $t->char('fingerprint', 64)->nullable(),
                default => $t->char('fingerprint', 64)->default(''),
            };
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['code'], 'adoptfix_item_code_unique');
            if ($variant === 'no-column') return;
            match ($variant) {
                'no-index' => null,
                'other-index' => $t->index(['fingerprint', 'code'], 'adoptfix_item_fingerprint_idx'),
                'unique-index' => $t->unique(['code', 'fingerprint'], 'adoptfix_item_fingerprint_idx'),
                default => $t->index(['code', 'fingerprint'], 'adoptfix_item_fingerprint_idx'),
            };
        });
    }

    private function migration(string $version, string $sql): void
    {
        file_put_contents($this->file($version), $sql);
    }

    private function file(string $version): string
    {
        return $this->root . '/plugins/' . self::CODE . '/database/migrations/' . $version . '.sql';
    }

    private function record(string $version, string $sql): void
    {
        Db::table('system_migration')->insert(['scope' => 'plugin:' . self::CODE, 'version' => $version, 'checksum' => hash('sha256', $sql), 'executed_at' => time()]);
    }
}
