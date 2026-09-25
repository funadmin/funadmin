<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\admin\development\service\BusinessDevelopmentService;
use app\admin\development\service\BusinessModuleService;
use app\admin\development\service\BusinessTargetService;
use app\admin\development\service\FormCrudDefinitionFactory;
use app\common\crud\ProductionTemplateContext;
use app\common\crud\PluginTemplateContext;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use app\common\service\InstallSupport;

// 只装配内存配置、调用纯转换方法，不初始化应用、不连接数据库。
$app = new think\App(dirname(__DIR__));
think\Container::setInstance($app);
$failures = [];
$expect = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) $failures[] = $message;
};
$service = (new ReflectionClass(BusinessDevelopmentService::class))->newInstanceWithoutConstructor();
$creation = new ReflectionMethod(BusinessDevelopmentService::class, 'creationPayload');
$compiler = new FormSchemaCompiler(new FormSchemaValidator());
$factory = new FormCrudDefinitionFactory();
$app->config->set(['mysqlPrefix' => ['__PREFIX__', '__prefix__', '{PREFIX}', '{prefix}', 'fun_', 'Fun_', 'THINK_', 'think_']], 'funadmin');
foreach (['', 'tenant_', 'fun_'] as $prefix) {
    $app->config->set(['default' => 'mysql', 'connections' => ['mysql' => ['prefix' => 'wrong_'], 'archive' => ['prefix' => $prefix]]], 'database');
    foreach (['core', 'plugin'] as $type) {
        $target = $type === 'plugin' ? ['type' => 'plugin', 'pluginCode' => 'sample'] : [];
        $logical = ($type === 'plugin' ? 'sample_' : '') . 'entry';
        foreach ([null, $logical, $prefix . $logical] as $inputTable) {
            $input = ['code' => 'entry', 'name' => '条目', 'connection' => 'archive', 'target' => $target];
            if ($inputTable !== null) $input['table'] = $inputTable;
            $payload = $creation->invoke($service, $input, 'created', []);
            $expect($payload['table_name'] === $prefix . $logical, "新建 {$type}/{$prefix}/" . ($inputTable ?? '默认') . ' 应只加目标连接前缀一次');
        }
        foreach (['legacy_entry', $prefix . $logical] as $physical) {
            $payload = $creation->invoke($service, ['code' => 'entry', 'name' => '条目', 'connection' => 'archive', 'target' => $target, 'table' => $physical], 'adopted', []);
            $expect($payload['table_name'] === $physical, '采纳必须保留物理表名');
        }
    }
    $designer = new \app\admin\form\service\FormDesignerService(dirname(__DIR__));
    $expect($designer->normalizeIdentifier($prefix . 'activity', true, 'archive') === 'activity', '表单标识推导必须使用指定连接前缀');
    $schemaPayload = new ReflectionMethod($designer, 'schemaPayload');
    foreach (['created', 'adopted'] as $source) {
        foreach ([0, 12] as $id) {
            foreach (['entry', $prefix . 'entry'] as $table) {
                $expected = $source === 'created' && $id === 0 ? $prefix . 'entry' : $table;
                $legacy = $schemaPayload->invoke($designer, ['id' => $id, 'connection' => 'archive', 'table_name' => $table, 'source_type' => $source]);
                $expect($legacy['table_name'] === $expected, '旧表单仅新建时补连接前缀，采纳和已有身份不变');
                $v2 = $schemaPayload->invoke($designer, ['id' => $id, 'schema_document' => ['schemaVersion' => 2, 'database' => ['connection' => 'archive', 'table' => $table, 'source' => $source]]]);
                $expect($v2['database']['table'] === $expected, 'V2 表单在编译哈希前统一新建物理表名');
            }
        }
    }
    $relationField = ['field_name' => 'category_id', 'label' => '分类', 'type' => 'relation', 'column_type' => 'bigint', 'relation_type' => 'belongs_to', 'relation_table' => $prefix . 'category', 'relation_value_field' => 'id', 'relation_label_field' => 'name'];
    $relationDefinition = $factory->create(['form_key' => 'entry', 'name' => '条目', 'connection' => 'archive', 'table_name' => $prefix . 'entry', 'source_type' => 'created', 'fields' => [$relationField]]);
    $expect($relationDefinition->get('relations')[0]['target'] === 'Category', '关联模型推导必须剥离目标连接前缀');
    foreach (['created', 'adopted'] as $source) {
        $table = $source === 'created' ? $prefix . 'sample_entry' : 'legacy_entry';
        $schema = $compiler->compile([
            'schemaVersion' => 2, 'key' => 'entry', 'title' => '条目',
            'database' => ['connection' => 'archive', 'table' => $table, 'source' => $source],
            'nodes' => [['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
                'database' => ['columnType' => 'varchar(255)', 'nullable' => false, 'index' => 'unique'], 'children' => []]],
        ]);
        $definition = $factory->createFromSchema($schema, [], [], ['primaryKey' => ['id'], 'columns' => [['name' => 'id', 'type' => 'bigint unsigned', 'nullable' => false]]]);
        foreach ([ProductionTemplateContext::build($definition), PluginTemplateContext::build($definition, 'sample', true), PluginTemplateContext::build($definition, 'sample', false)] as $context) {
            $expect(str_contains($context['modelContent'], "protected string \$table = '{$table}';"), "{$source}/{$prefix} 模型必须绑定准确物理表");
            $expect(str_contains($context['modelContent'], "protected \$connection = 'archive';"), '模型必须绑定目标连接');
            $expect(str_contains($context['migrationContent'], "`{$table}`"), '迁移和模型使用同一物理表');
            $expect(str_starts_with($context['migrationContent'], "-- funadmin-physical-table\n"), '生成迁移必须声明物理表语义');
            $modelCode = preg_replace('/namespace [^;]+;/', 'namespace PrefixTest\\Case' . bin2hex(random_bytes(5)) . ';', $context['modelContent'], 1);
            preg_match('/namespace ([^;]+);/', $modelCode, $namespace);
            $modelCode = str_replace('extends BackendModel', 'extends \\app\\admin\\model\\BackendModel', $modelCode);
            eval(substr($modelCode, 5));
            $modelClass = $namespace[1] . '\\Entry';
            $validateCode = preg_replace('/namespace [^;]+;/', 'namespace ' . $namespace[1] . ';', $context['validateContent'], 1);
            eval(substr($validateCode, 5));
            $validatorClass = $namespace[1] . '\\EntryValidate';
            $validator = new $validatorClass();
            $rulesProperty = new ReflectionProperty($validatorClass, 'rule');
            $uniqueRule = $rulesProperty->getValue($validator)['title'];
            $expect(str_contains($uniqueRule, 'unique:\\') && str_contains($uniqueRule, '\\Entry,title,{id},id'), 'unique 规则必须引用生成的绑定表模型');
            $validator->forUpdate(42);
            $expect(str_contains($rulesProperty->getValue($validator)['title'], ',title,42,id'), '更新 unique 必须排除当前主键');
            $model = (new ReflectionClass($modelClass))->newInstanceWithoutConstructor();
            $expect($model->getOption('table') === $table && $model->getOption('connection') === 'archive', '真实 ORM 模型配置必须保持物理表与连接');
        }
    }
    $ownersMethod = new ReflectionMethod(BusinessTargetService::class, 'tableOwners');
    $ownersPolicy = new BusinessTargetService(dirname(__DIR__), 'archive');
    $owners = $ownersMethod->invoke($ownersPolicy);
    $expect(($owners[$prefix . 'admin'] ?? '') === 'core', '采纳拒绝表必须使用连接实际前缀保护核心表');
    $policy = new BusinessTargetService(dirname(__DIR__), 'archive', static fn () => true, static fn () => [], static fn () => [['code' => 'sample', 'name' => '示例', 'scopes' => ['console'], 'businessWritable' => true]], static fn () => [], static fn () => false);
    try {
        $policy->assertSelection(BusinessModuleService::normalizeTarget(['type' => 'plugin', 'pluginCode' => 'sample'], 'created'), 'archive', $prefix . 'sample_entry');
    } catch (InvalidArgumentException $e) {
        $expect(false, "插件边界应接受 {$prefix}：" . $e->getMessage());
    }
}
$designerSource = file_get_contents(dirname(__DIR__) . '/app/admin/form/service/FormDesignerService.php');
$expect(str_contains($designerSource, "Db::connect((string) (\$payload['connection'] ?? 'mysql'))->getTables()"), 'DDL 表结构检查必须使用目标连接');
$expect(str_contains($designerSource, "Db::connect((string) (\$payload['connection'] ?? 'mysql'))->execute"), '动态 DDL 必须使用目标连接');
$designerUi = file_get_contents(dirname(__DIR__) . '/admin-web/src/views/form/designer/index.vue');
$expect(!str_contains($designerUi, 'fun_'), '设计器不得推导硬编码前缀表名');
$rewrite = new ReflectionMethod(\app\common\service\MigrationService::class, 'rewritePrefix');
foreach (['', 'tenant_', 'tenant_fun_'] as $prefix) {
    $legacySql = "CREATE TABLE `fun_member` (`id` int); SELECT 'fun_member';";
    $expect($rewrite->invoke(null, $legacySql, 'fun_', $prefix) === str_replace('fun_', $prefix, $legacySql), '安装历史 SQL 必须支持空和自定义前缀替换');
    $physicalSql = "-- funadmin-physical-table\nCREATE TABLE `{$prefix}entry` (`id` int);";
    $expect($rewrite->invoke(null, $physicalSql, 'fun_', $prefix) === $physicalSql, '生成物理表 SQL 不得再次替换前缀');
}
$aliases = ['__PREFIX__', '__prefix__', '{PREFIX}', '{prefix}', 'fun_', 'Fun_', 'THINK_', 'think_'];
$expect($rewrite->invoke(null, 'CREATE TABLE `__PREFIX__entry` (`id` int);', $aliases, 'tenant_fun_') === 'CREATE TABLE `tenant_fun_entry` (`id` int);', '模板别名必须单次替换，不能再次替换结果中包含的 fun_');
$resourceRoot = sys_get_temp_dir() . '/crud-prefix-' . bin2hex(random_bytes(6));
mkdir($resourceRoot, 0700);
try {
    $resourceSql = "INSERT INTO `fun_permission` (`name`) SELECT 'fun_permission' WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu`);";
    file_put_contents($resourceRoot . '/resources.sql', $resourceSql);
    $resourceDefinition = \app\common\crud\CrudDefinition::fromArray(['entity' => 'entry', 'connection' => 'archive', 'generationTargets' => ['permissionMigration' => 'resources.sql']]);
    $manifest = ['definitionHash' => $resourceDefinition->hash(), 'files' => [['path' => 'resources.sql', 'hash' => hash('sha256', $resourceSql)]]];
    foreach (['', 'tenant_', 'tenant_fun_'] as $prefix) {
        $app->config->set(['default' => 'mysql', 'connections' => ['mysql' => ['prefix' => $prefix], 'archive' => ['prefix' => 'wrong_']]], 'database');
        $executed = [];
        $installer = new \app\common\crud\CrudResourceInstaller($resourceRoot, static function (string $sql) use (&$executed): int { $executed[] = $sql; return 1; }, static fn (callable $operation) => $operation());
        $installer->apply($resourceDefinition->toArray(), $manifest);
        $expectedSql = strtr($resourceSql, ['`fun_permission`' => '`' . $prefix . 'permission`', '`fun_admin_menu`' => '`' . $prefix . 'admin_menu`']);
        $expect($executed === [$expectedSql], '资源 SQL 应使用系统默认连接前缀且不改写字符串内容');
    }
} finally {
    unlink($resourceRoot . '/resources.sql');
    rmdir($resourceRoot);
}
// 加载真实生成类，通过反射检查 ORM 配置，跳过会读取数据库结构的构造器。
foreach (['', 'tenant_', 'tenant_fun_'] as $prefix) {
    $app->config->set(['default' => 'mysql', 'connections' => ['mysql' => ['prefix' => 'wrong_'], 'archive' => ['prefix' => $prefix]]], 'database');
    foreach (['entry_tag', $prefix . 'entry_tag', 'legacy_link'] as $pivotTable) {
        $base = $factory->create(['form_key' => 'entry', 'name' => '条目', 'connection' => 'archive', 'table_name' => 'legacy_entry', 'source_type' => 'created', 'fields' => [['field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(80)', 'is_unique' => 1]]])->toArray();
        $base['fields'][1]['unique'] = true;
        $base['relations'] = [['name' => 'tags', 'type' => 'belongsToMany', 'field' => 'id', 'target' => 'Tag', 'targetField' => 'id', 'pivotTable' => $pivotTable, 'pivotLocalKey' => 'entry_id', 'pivotTargetKey' => 'tag_id']];
        $definition = \app\common\crud\CrudDefinition::fromArray($base);
        $hash = $definition->hash();
        foreach ([ProductionTemplateContext::build($definition), PluginTemplateContext::build($definition, 'sample', true), PluginTemplateContext::build($definition, 'sample', false)] as $context) {
            $expect(str_contains($context['validateContent'], 'unique:\\\\') && !str_contains($context['validateContent'], 'unique:legacy_entry'), 'unique 必须通过模型引用使用物理表与目标连接');
            $code = preg_replace('/namespace [^;]+;/', 'namespace PrefixPivotTest\\Case' . bin2hex(random_bytes(5)) . ';', $context['modelContent'], 1);
            $code = str_replace('extends BackendModel', 'extends \\app\\admin\\model\\BackendModel', $code);
            preg_match('/namespace ([^;]+);/', $code, $namespace);
            eval(substr($code, 5));
            $pivotClass = $namespace[1] . '\\EntryTagsPivot';
            $expect(class_exists($pivotClass), '多对多必须使用绑定物理表的 Pivot 模型，而非再次拼接前缀的字符串表名');
            if (class_exists($pivotClass)) {
                $pivot = (new ReflectionClass($pivotClass))->newInstanceWithoutConstructor();
                $expect($pivot->getOption('table') === $pivotTable && $pivot->getOption('connection') === 'archive', '中间模型必须保留原物理表与连接，包括无前缀采纳表');
                $expect(str_contains($code, 'EntryTagsPivot::class'), 'belongsToMany 必须引用物理中间模型');
            }
        }
        $expect($definition->hash() === $hash, '渲染不得改写历史 Definition hash');
    }
}
$db = ['host' => 'localhost', 'port' => '3306', 'database' => 'test', 'username' => 'root', 'password' => ''];
$admin = ['username' => 'admin', 'password' => 'admin123', 'repassword' => 'admin123', 'email' => 'admin@example.com'];
$normalized = InstallSupport::normalizeDatabaseInput($db);
$expect(($normalized['prefix'] ?? null) === '', '省略安装前缀必须默认空');
foreach (['', 'tenant_'] as $prefix) {
    $input = $db + ['prefix' => $prefix];
    $expect(InstallSupport::validate($input, $admin) === '', '安装接受空或合法自定义前缀');
    $env = InstallSupport::renderEnv(dirname(__DIR__) . '/.env.example', null, $input, false);
    $expect(parse_ini_string($env, false, INI_SCANNER_RAW)['DB_PREFIX'] === $prefix, '环境渲染保留空或自定义前缀');
}
$expect(str_contains(file_get_contents(dirname(__DIR__) . '/config/database.php'), "env('DB_PREFIX', '')"), '配置默认前缀必须为空');
$expect(parse_ini_file(dirname(__DIR__) . '/.env.example', false, INI_SCANNER_RAW)['DB_PREFIX'] === '', '示例前缀必须为空');
$expect(str_contains(file_get_contents(dirname(__DIR__) . '/admin-web/src/views/install/index.vue'), "prefix: ''"), '安装页面默认前缀必须为空');
if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "CRUD prefix tests: PASS\n";
