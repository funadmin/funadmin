<?php

declare(strict_types=1);

namespace Tests;

use app\admin\form\service\FormDesignerService;
use app\common\crud\CrudDefinition;
use app\common\crud\PluginCrudTarget;
use app\common\crud\ProductionTemplateContext;
use app\common\form\builder\Form as FormBuilder;
use app\common\model\concern\LaravelSoftDelete;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

// 插件源码位于 admin/plugins/<code>/app（安装副本不入库）：按插件源包加载 app\<code>\ 与 app\admin\plugins\<code>\ 下的类。
spl_autoload_register(static function (string $class): void {
    foreach (glob(dirname(__DIR__) . '/plugins/*', GLOB_ONLYDIR) ?: [] as $plugin) {
        $code = basename($plugin);
        foreach (['app\\' . $code . '\\' => '/app/' . $code . '/', 'app\\admin\\plugins\\' . $code . '\\' => '/app/admin/plugins/' . $code . '/'] as $prefix => $directory) {
            if (str_starts_with($class, $prefix)) {
                $path = $plugin . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require_once $path;
                    return;
                }
            }
        }
    }
}, true, true);

/**
 * 插件软删除规则的防回退守卫（docs/superpowers/specs/2026-11-11-plugin-soft-delete-design.md）：
 * - 每张非豁免插件表（按各插件 Schema 安装 + 软删除步骤建出）都有 deleted_at 且有以它开头的索引；
 *   有非主键唯一键时才有 deleted_token，且每个非主键唯一键都以 deleted_token 结尾；
 * - 插件 app 代码只在回收站“彻底删除”方法里调用 forceDelete( / Db::table(...)->delete(；
 * - 非豁免表的插件模型都使用 LaravelSoftDelete；
 * - 代码生成器、表单设计器、插件目标的建表 SQL：有唯一字段时带 deleted_token 与以它结尾的唯一键，没有唯一字段时不带。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PluginSoftDeleteGuardTest extends TestCase
{
    /** 只追加的历史表与单行设置表：不加软删除列。新增豁免必须在这里显式登记。 */
    private const EXEMPT = [
        'cms_form_event', 'cms_site_auth_audit', 'cms_model_version', 'cms_content_revision', 'cms_template_tag_version',
        'document_events', 'document_revisions',
        'oss_setting',
    ];

    /** 允许物理删除的回收站“彻底删除”方法（插件内相对路径 => 方法）。保持最小、具名。 */
    private const PERMANENT_DELETE_ALLOWLIST = [
        'cms/app/cms/service/MenuService.php::destroy',
        'cms/app/cms/service/SiteRoleService.php::destroyMembers',
        'cms/app/cms/service/FormRecycleBin.php::destroyForms',
        'cms/app/cms/service/FormRecycleBin.php::purge',
        'document/app/document/service/DocumentService.php::destroy',
        'document/app/document/service/DocumentCategoryService.php::destroy',
        'document/app/document/service/CategoryLinks.php::purge',
        'qa/app/qa/service/QaRecycleBin.php::destroyQuestions',
        'qa/app/qa/service/QaRecycleBin.php::destroyAnswers',
    ];

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
    }

    /** @return array<string, list<string>> 插件 => 表（未加前缀） */
    private static function installPluginTables(): array
    {
        \app\document\service\Schema::install();
        \app\qa\service\Schema::install();
        \app\admin\plugins\oss\service\Schema::install();
        \app\cms\service\Schema::install();
        \app\cms\service\SiteRoleSchema::install();
        \app\cms\service\TemplateTagRemarkSchema::install();
        \app\cms\service\ContributionSchema::install();
        \app\cms\service\FormSchema::install();
        \app\cms\service\FormFingerprintSchema::install();
        \app\cms\service\PageFormSchema::install();
        \app\cms\service\SoftDeleteSchema::install();
        return [
            'document' => \app\document\service\Schema::tables(),
            'qa' => \app\qa\service\Schema::tables(),
            'oss' => ['oss_profile', 'oss_setting'],
            'cms' => array_merge(\app\cms\service\Schema::tables(), \app\cms\service\SiteRoleSchema::tables(), \app\cms\service\ContributionSchema::tables(), \app\cms\service\FormSchema::tables()),
        ];
    }

    public function test_every_non_exempt_plugin_table_has_deleted_at_index_and_token_only_with_unique_keys(): void
    {
        $tables = array_merge(...array_values(self::installPluginTables()));
        self::assertSame([], array_values(array_diff(self::EXEMPT, $tables)), '豁免表必须是真实的插件表');
        // 插件自己的豁免常量与这里的显式清单一致。
        self::assertSame([], array_values(array_diff(\app\document\service\Schema::SOFT_DELETE_EXEMPT, self::EXEMPT)));
        self::assertSame([], array_values(array_diff(\app\cms\service\SoftDeleteSchema::EXEMPT, self::EXEMPT)));
        self::assertSame(array_values(array_diff(array_filter($tables, static fn (string $t): bool => str_starts_with($t, 'cms_')), self::EXEMPT)), \app\cms\service\SoftDeleteSchema::tables());

        $withToken = 0;
        foreach ($tables as $table) {
            self::assertTrue(Db::schema()->hasTable($table), $table . ' 未建出');
            $indexes = Db::schema()->getIndexes($table);
            $uniques = array_filter($indexes, static fn (array $index): bool => $index['unique'] && !$index['primary']);
            $hasToken = Db::schema()->hasColumn($table, 'deleted_token');
            if (in_array($table, self::EXEMPT, true)) {
                self::assertFalse(Db::schema()->hasColumn($table, 'deleted_at'), $table . ' 是豁免表，不应有 deleted_at');
                self::assertFalse($hasToken, $table . ' 是豁免表，不应有 deleted_token');
                continue;
            }
            self::assertTrue(Db::schema()->hasColumn($table, 'deleted_at'), $table . ' 缺 deleted_at');
            self::assertNotSame([], array_filter($indexes, static fn (array $index): bool => ($index['columns'][0] ?? null) === 'deleted_at'), $table . ' 缺以 deleted_at 开头的索引');
            self::assertSame($uniques !== [], $hasToken, $table . '：deleted_token 应当且仅当存在非主键唯一键');
            foreach ($uniques as $unique) {
                self::assertSame('deleted_token', $unique['columns'][array_key_last($unique['columns'])], $table . ' 的唯一键 ' . $unique['name'] . ' 必须以 deleted_token 结尾');
            }
            $withToken += $hasToken ? 1 : 0;
        }
        // 文档 5 张 + CMS 16 张有令牌；QA、OSS 没有非主键唯一键。
        self::assertSame(21, $withToken);
    }

    public function test_plugin_code_only_hard_deletes_inside_recycle_bin_permanent_delete_methods(): void
    {
        $root = dirname(__DIR__) . '/plugins/';
        $found = [];
        foreach (glob($root . '*', GLOB_ONLYDIR) ?: [] as $plugin) {
            if (!is_dir($plugin . '/app')) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($plugin . '/app', \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                if (!str_ends_with($path, '.php') || str_contains($path, '/vendor/')) {
                    continue;
                }
                $relative = substr($path, strlen($root));
                foreach (self::hardDeleteMethods((string) file_get_contents($path)) as $method) {
                    $found[] = $relative . '::' . $method;
                }
            }
        }
        $found = array_values(array_unique($found));
        sort($found);
        $allow = self::PERMANENT_DELETE_ALLOWLIST;
        sort($allow);
        self::assertSame([], array_values(array_diff($found, $allow)), '物理删除只允许出现在回收站彻底删除方法里（新增需登记到 PERMANENT_DELETE_ALLOWLIST）');
        self::assertSame([], array_values(array_diff($allow, $found)), '白名单中有已不存在的条目，请移除');
    }

    /**
     * 源码中调用 forceDelete( 或 Db::table(...)...->delete( 的语句所在的具名方法（闭包归入外层方法）。
     * @return list<string>
     */
    private static function hardDeleteMethods(string $source): array
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= str_repeat("\n", substr_count($token[1], "\n"));
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }
        $offsets = [];
        preg_match_all('/forceDelete\s*\(/', $code, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [, $offset]) {
            $offsets[] = $offset;
        }
        // Db::table(...) / Db::connection(...)->table(...) 开始、到分号结束的语句里出现 ->delete(。
        preg_match_all('/\bDb::(?:table|connection)\s*\([^;]*?->\s*delete\s*\(/is', $code, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [, $offset]) {
            $offsets[] = $offset;
        }
        $methods = [];
        foreach ($offsets as $offset) {
            preg_match_all('/\bfunction\s+&?\s*([A-Za-z_]\w*)\s*\(/', substr($code, 0, $offset), $names);
            $methods[] = $names[1] === [] ? '(file)' : $names[1][array_key_last($names[1])];
        }
        return $methods;
    }

    public function test_every_plugin_model_for_a_non_exempt_table_uses_laravel_soft_delete(): void
    {
        $root = dirname(__DIR__) . '/plugins/';
        $checked = [];
        foreach (glob($root . '*', GLOB_ONLYDIR) ?: [] as $plugin) {
            if (!is_dir($plugin . '/app')) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($plugin . '/app', \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                if (!str_ends_with($path, '.php') || str_contains($path, '/vendor/') || !str_contains($path, '/model/')) {
                    continue;
                }
                $class = 'app\\' . str_replace('/', '\\', substr($path, strlen($plugin . '/app/'), -4));
                self::assertTrue(class_exists($class) || trait_exists($class) || interface_exists($class), $class . ' 无法加载');
                $reflection = new \ReflectionClass($class);
                if (!$reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class) || $reflection->isAbstract()) {
                    continue;
                }
                $table = $reflection->newInstanceWithoutConstructor()->getTable();
                $checked[] = $table;
                if (in_array($table, self::EXEMPT, true)) {
                    self::assertNotContains(LaravelSoftDelete::class, class_uses_recursive($class), $class . ' 是豁免表模型，不应软删除');
                    continue;
                }
                self::assertContains(LaravelSoftDelete::class, class_uses_recursive($class), $class . '（' . $table . '）必须使用 LaravelSoftDelete');
            }
        }
        // 文档 7 + QA 2 + OSS 2 + CMS 26 个具体模型。
        self::assertCount(37, $checked);
    }

    // ---- 生成器：有唯一字段时带令牌，没有时不带 ----

    public function test_code_generator_create_sql_follows_token_rule(): void
    {
        self::assertTokenShape($this->generatorSql(true), true);
        self::assertTokenShape($this->generatorSql(false), false);
    }

    public function test_form_designer_create_sql_follows_token_rule(): void
    {
        self::assertTokenShape($this->formDesignerSql(true), true);
        self::assertTokenShape($this->formDesignerSql(false), false);
    }

    public function test_plugin_target_create_sql_follows_token_rule(): void
    {
        self::assertTokenShape($this->pluginTargetSql(true), true);
        self::assertTokenShape($this->pluginTargetSql(false), false);
    }

    private static function assertTokenShape(string $sql, bool $unique): void
    {
        $sql = explode('-- funadmin-crud-schema', $sql)[0];
        self::assertMatchesRegularExpression('/`deleted_at` datetime NULL/i', $sql);
        self::assertMatchesRegularExpression('/KEY `idx_\w+_deleted_at` \(`deleted_at`\)/', $sql);
        preg_match_all('/UNIQUE KEY `\w+` \(([^)]*)\)/', $sql, $keys);
        if (!$unique) {
            self::assertStringNotContainsString('deleted_token', $sql);
            self::assertSame([], $keys[1]);
            return;
        }
        self::assertMatchesRegularExpression('/`deleted_token` bigint unsigned NOT NULL DEFAULT 0/i', $sql);
        self::assertNotSame([], $keys[1], '应有唯一键');
        foreach ($keys[1] as $columns) {
            self::assertMatchesRegularExpression('/`deleted_token`\s*$/', $columns, '唯一键必须以 deleted_token 结尾：' . $columns);
        }
    }

    /** @return array<string, mixed> */
    private static function definitionData(string $entity, bool $unique): array
    {
        $data = \Tests\Console\CrudDefinitionFixture::make($entity, 'mysql')->toArray();
        if ($unique) {
            $data['fields'][] = ['name' => 'code', 'label' => '编码', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        }
        return $data;
    }

    private function generatorSql(bool $unique): string
    {
        return ProductionTemplateContext::build(CrudDefinition::fromArray(self::definitionData('guard-soft', $unique)))['migrationContent'];
    }

    private function formDesignerSql(bool $unique): string
    {
        $document = FormBuilder::make('guard_soft')->title('软删除守卫')->table('guard_soft', 'mysql', 'created');
        $document->input('title', '标题')->column('varchar(120)', true, false, 'index');
        if ($unique) {
            $document->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        }
        $forms = new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        return (new \ReflectionMethod($forms, 'createTableSql'))->invoke($forms, 'guard_soft', $document->compile()->fields());
    }

    private function pluginTargetSql(bool $unique): string
    {
        $data = self::definitionData('guard-soft', $unique);
        $data['target'] = ['type' => 'plugin', 'plugin' => 'guardkit', 'scope' => 'admin'];
        $data['table'] = 'guard_soft';
        unset($data['generationTargets']);
        $definition = CrudDefinition::fromArray($data);
        $root = sys_get_temp_dir() . '/funadmin-guard-soft-' . bin2hex(random_bytes(6));
        mkdir($root . '/plugins/guardkit/database/migrations', 0700, true);
        try {
            $create = ProductionTemplateContext::build($definition)['migrationContent'];
            $migration = (new \ReflectionMethod(PluginCrudTarget::class, 'migration'))->invoke(new PluginCrudTarget($root), $definition, 'guardkit', 'guard-soft', $create);
            self::assertSame('plugins/guardkit/database/migrations/001_create_guard_soft.sql', $migration['path']);
            return $migration['content'];
        } finally {
            foreach (['/plugins/guardkit/database/migrations', '/plugins/guardkit/database', '/plugins/guardkit', '/plugins', ''] as $dir) {
                @rmdir($root . $dir);
            }
        }
    }
}
