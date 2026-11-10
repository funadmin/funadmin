<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugin\command\PluginValidate;
use app\common\plugin\sdk\LifecycleDdlGuard;
use app\common\plugin\sdk\LifecycleHookDdlDetector;
use app\common\plugin\sdk\PluginScaffolder;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

/** SDK 规则：install / enabled / beforeUpdate / afterUpdate 钩子不得改结构，结构变更只能写在 migration 里。 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class PluginHookDdlValidationTest extends TestCase
{
    private const CODE = 'hookddl';
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/funadmin-hook-ddl-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        (new PluginScaffolder($this->root . '/plugins'))->scaffold(self::CODE);
        $this->service('ItemSchema', <<<'PHP'
            use Illuminate\Database\Schema\{Blueprint, Builder};
            use support\Db;
            final class ItemSchema
            {
                public static function ready(): bool { return Db::schema()->hasTable('hookddl_item'); }
                public static function install(): void { $schema = Db::schema(); if (!$schema->hasTable('hookddl_item')) self::create($schema); }
                private static function create(Builder $schema): void { $schema->create('hookddl_item', static function (Blueprint $t): void { $t->id(); }); }
            }
            PHP);
        $this->service('Seeder', <<<'PHP'
            use support\Db;
            final class Seeder
            {
                public function run(): void { if (ItemSchema::ready()) Db::table('hookddl_item')->insertOrIgnore(['id' => 1]); $this->touch(); }
                private function touch(): void { Db::statement('UPDATE fun_hookddl_item SET id = id'); }
            }
            PHP);
        $this->service('Upgrader', <<<'PHP'
            use support\Db;
            final class Upgrader
            {
                public function run(): void { Db::schema()->table('hookddl_item', static fn ($t) => $t->string('name')); }
            }
            PHP);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_validate_rejects_schema_changes_reachable_from_lifecycle_hooks(): void
    {
        Db::schema()->create('plugin', static function (Blueprint $table): void {
            $table->id(); $table->string('code'); $table->string('version'); $table->string('lifecycle_state');
            $table->timestamps(); $table->softDeletes();
        });
        try {
            $command = new CommandTester(new PluginValidate($this->root));
            // 只读的结构探测（hasTable）、数据写入与 DML 语句都允许。
            $this->entry(['enabled' => '(new \app\hookddl\service\Seeder())->run();']);
            self::assertSame(0, $command->execute(['name' => self::CODE]), $command->getDisplay());

            $this->entry(['enabled' => '\app\hookddl\service\Seeder::class; \app\hookddl\service\ItemSchema::install();']);
            self::assertSame(1, $command->execute(['name' => self::CODE]));
            $display = preg_replace('/\s+/', ' ', $command->getDisplay());
            self::assertStringContainsString('生命周期钩子不得修改数据库结构', $display);
            self::assertStringContainsString('enabled()', $display);
            self::assertStringContainsString('ItemSchema::install()', $display);
        } finally {
            Db::schema()->dropIfExists('plugin');
        }
    }

    public static function forbidden(): array
    {
        return [
            'schema helper via builder parameter' => ['enabled', '\app\hookddl\service\ItemSchema::install();', 'create()'],
            'imported alias' => ['install', 'Tables::install();', 'create()'],
            'schema() chain in instance method' => ['beforeUpdate', '(new \app\hookddl\service\Upgrader())->run();', 'table()'],
            'raw ddl literal' => ['afterUpdate', "Db::statement('ALTER TABLE fun_hookddl_item ADD name VARCHAR(10)');", 'ALTER'],
            'raw ddl via unprepared' => ['enabled', 'Db::unprepared("create index i on fun_hookddl_item (id)");', 'CREATE'],
            'drop through facade-style builder' => ['install', "Db::schema()->dropIfExists('hookddl_item');", 'dropIfExists()'],
            'sql not statically known' => ['enabled', '$sql = "x"; Db::statement($sql);', 'statement()'],
            'blueprint in hook body' => ['afterUpdate', "Db::connection()->getSchemaBuilder()->create('t', static function (\\Illuminate\\Database\\Schema\\Blueprint \$t): void {});", 'create()'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forbidden')]
    public function test_detector_flags_each_schema_change_form(string $hook, string $body, string $mentioned): void
    {
        $this->entry([$hook => $body]);
        $violations = (new LifecycleHookDdlDetector())->violations($this->root . '/plugins/' . self::CODE, 'Plugin.php');
        self::assertNotSame([], $violations);
        self::assertStringStartsWith($hook . '()', $violations[0]);
        self::assertStringContainsString($mentioned, implode("\n", $violations));
    }

    public function test_hooks_outside_the_rule_and_unreachable_methods_are_not_flagged(): void
    {
        // disabled / uninstall / purgeData 不在规则内；未从钩子调用的结构方法（如 ItemSchema::install）不算。
        $this->entry(['disabled' => "Db::schema()->dropIfExists('hookddl_item');", 'enabled' => '\app\hookddl\service\ItemSchema::ready();']);
        self::assertSame([], (new LifecycleHookDdlDetector())->violations($this->root . '/plugins/' . self::CODE, 'Plugin.php'));
    }

    public function test_cms_enable_hook_no_longer_changes_schema_and_former_hook_is_flagged_precisely(): void
    {
        self::assertSame([], (new LifecycleHookDdlDetector())->violations(base_path('plugins/cms'), 'Plugin.php'));
        foreach (['document', 'oss', 'qa'] as $plugin) {
            self::assertSame([], (new LifecycleHookDdlDetector())->violations(base_path('plugins/' . $plugin), 'Plugin.php'), $plugin);
        }
        // 改动前的 enabled()：只有四个 *Schema::install() 改结构；种子、站点角色引导与权限补齐都不算。
        $copy = $this->root . '/plugins/cms';
        $this->copyTree(base_path('plugins/cms/app/cms'), $copy . '/app/cms');
        file_put_contents($copy . '/Plugin.php', '<?php declare(strict_types=1); namespace plugins\cms; use app\common\plugin\sdk\Plugin as BasePlugin; final class Plugin extends BasePlugin { '
            . 'public function install(): bool { return true; } public function uninstall(): bool { return true; } '
            . 'public function enabled(): bool { \app\cms\service\ModelService::seed(); \app\cms\service\SiteRoleSchema::bootstrap(); \app\cms\service\FormPermissionUpgrade::backfill(); \app\cms\service\TemplateTagRemarkSchema::install(); \app\cms\service\FormSchema::install(); \app\cms\service\FormFingerprintSchema::install(); \app\cms\service\PageFormSchema::install(); return true; } '
            . 'public function disabled(): bool { return true; } public function purgeData(): bool { return false; } }');
        $violations = (new LifecycleHookDdlDetector())->violations($copy, 'Plugin.php');
        $roots = array_values(array_unique(array_map(static fn (string $line): string => explode(' → ', $line)[1], $violations)));
        self::assertSame([
            'app\cms\service\TemplateTagRemarkSchema::install()',
            'app\cms\service\FormSchema::install()',
            'app\cms\service\FormFingerprintSchema::install()',
            'app\cms\service\PageFormSchema::install()',
        ], $roots, implode("\n", $violations));
    }

    public function test_runtime_guard_blocks_ddl_during_hooks_only_and_resets(): void
    {
        $probe = static fn () => Db::schema()->create('hookddl_probe', static function (Blueprint $t): void { $t->id(); });
        try {
            LifecycleDdlGuard::run(self::CODE, 'enabled', $probe);
            self::fail('钩子内的 DDL 必须被拒绝');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('hookddl 的 enabled() 钩子禁止修改数据库结构', $exception->getMessage());
        }
        self::assertFalse(Db::schema()->hasTable('hookddl_probe'), 'DDL 在执行前被拒绝');
        // 嵌套与异常之后状态复原；钩子内的只读探测与数据写入照常。
        self::assertTrue(LifecycleDdlGuard::run(self::CODE, 'install', static fn (): bool => LifecycleDdlGuard::run(self::CODE, 'afterUpdate', static fn (): bool => !Db::schema()->hasTable('hookddl_probe'))));
        try {
            $probe();
            self::assertTrue(Db::schema()->hasTable('hookddl_probe'), '钩子之外的 DDL（migration 执行路径）不受影响');
            Db::table('hookddl_probe')->insert(['id' => 1]);
            self::assertSame(1, LifecycleDdlGuard::run(self::CODE, 'enabled', static fn (): int => Db::table('hookddl_probe')->where('id', 1)->update(['id' => 1])));
            $this->expectExceptionMessage('afterUpdate() 钩子禁止修改数据库结构');
            LifecycleDdlGuard::run(self::CODE, 'afterUpdate', static fn () => Db::statement('ALTER TABLE fun_hookddl_probe ADD name VARCHAR(10)'));
        } finally {
            Db::schema()->dropIfExists('hookddl_probe');
        }
    }

    public function test_plugin_service_runs_every_guarded_hook_through_the_runtime_guard(): void
    {
        $source = (string) file_get_contents(app_path('admin/plugin/service/PluginService.php'));
        foreach (LifecycleHookDdlDetector::HOOKS as $hook) {
            self::assertSame(1, preg_match_all('/LifecycleDdlGuard::run\(\$code, \'' . $hook . '\', static fn \(\) => \$plugin->' . $hook . '\(/', $source), $hook);
            self::assertSame(1, preg_match_all('/\$plugin->' . $hook . '\(/', $source), $hook . ' 只能经守卫调用');
        }
    }

    /** @param array<string, string> $bodies */
    private function entry(array $bodies): void
    {
        $hooks = ['install' => '', 'uninstall' => '', 'enabled' => '', 'disabled' => '', 'beforeUpdate' => '', 'afterUpdate' => ''];
        $source = "<?php\ndeclare(strict_types=1);\nnamespace plugins\\hookddl;\nuse app\\common\\plugin\\sdk\\Plugin as BasePlugin;\nuse app\\hookddl\\service\\ItemSchema as Tables;\nuse support\\Db;\nfinal class Plugin extends BasePlugin\n{\n";
        foreach (array_replace($hooks, $bodies) as $hook => $body) {
            $signature = in_array($hook, ['beforeUpdate', 'afterUpdate'], true) ? 'string $fromVersion, string $toVersion, bool $migrate' : '';
            $source .= "    public function {$hook}({$signature}): bool { {$body} return true; }\n";
        }
        file_put_contents($this->root . '/plugins/' . self::CODE . '/Plugin.php', $source . "}\n");
    }

    private function service(string $class, string $body): void
    {
        $directory = $this->root . '/plugins/' . self::CODE . '/app/' . self::CODE . '/service';
        if (!is_dir($directory)) mkdir($directory, 0755, true);
        file_put_contents($directory . '/' . $class . '.php', "<?php\ndeclare(strict_types=1);\nnamespace app\\hookddl\\service;\n" . $body . "\n");
    }

    private function copyTree(string $from, string $to): void
    {
        mkdir($to, 0755, true);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            $target = $to . substr($file->getPathname(), strlen($from));
            $file->isDir() ? mkdir($target, 0755, true) : copy($file->getPathname(), $target);
        }
    }
}
