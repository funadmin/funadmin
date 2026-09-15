<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/vendor/topthink/framework/src/helper.php';

use app\common\crud\ProductionTemplateContext;

// 不初始化现场应用；所有查询限定在 SQLite 内存连接，禁止访问真实数据库。
$app = new think\App(dirname(__DIR__, 2));
think\Container::setInstance($app);
$db = new think\DbManager();
$app->instance(think\DbManager::class, $db);
$db->setConfig(['default' => 'mysql', 'connections' => ['mysql' => [
    'type' => 'sqlite', 'database' => ':memory:', 'prefix' => 'wrong_', 'fields_strict' => true,
]]]);
$app->config->set(['default' => 'file', 'stores' => ['file' => ['type' => 'file']]], 'cache');
$checks = 0;
$failures = [];
$test = static function (string $name, callable $check) use (&$checks, &$failures): void {
    try {
        $check();
        ++$checks;
        echo "PASS: {$name}\n";
    } catch (Throwable $error) {
        $failures[] = $name . ': ' . $error->getMessage();
        echo 'FAIL: ' . end($failures) . "\n";
    }
};
$expect = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$definition = (new app\admin\development\service\FormCrudDefinitionFactory())->create([
    'form_key' => 'order_runtime_fixture', 'table_name' => 'fun_test', 'name' => '隔离列表',
    'fields' => [['field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(80)']],
]);
$test('默认模板生成的子命名空间模型无需补丁可加载', static function () use ($definition, $expect): void {
    $context = ProductionTemplateContext::build($definition);
    eval(substr($context['modelContent'], 5));
    $expect(class_exists('app\\admin\\model\\generated\\OrderRuntimeFixture'), '生成模型不可加载');
});
$test('模板不得将同名外键标量作为关联对象输出', static function () use ($definition, $expect): void {
    $data = $definition->toArray();
    $data['relations'] = [
        ['name' => 'title', 'type' => 'belongsTo', 'field' => 'title', 'target' => 'AdminLog', 'targetField' => 'id'],
        ['name' => 'owner', 'type' => 'belongsTo', 'field' => 'title', 'target' => 'AdminLog', 'targetField' => 'id'],
    ];
    $context = ProductionTemplateContext::build(app\common\crud\CrudDefinition::fromArray($data));
    $expect(!str_contains($context['controllerContent'], '$model->title?->toArray()'), '同名外键不能调用对象方法');
    $expect(str_contains($context['controllerContent'], '$model->owner?->toArray()'), '非冲突关联必须保留');
});
$test('隔离生成定义可验证模板行为', static function () use ($definition, $expect): void {
    $generated = ProductionTemplateContext::build($definition);
    $expect(str_contains($generated['controllerContent'], 'class OrderRuntimeFixtureController'), '生成控制器不可构建');
});
foreach (['password' => ['component' => 'password'], 'sensitive' => ['controlProps' => ['sensitive' => true]], 'write_only' => ['controlProps' => ['writeOnly' => true]]] as $kind => $metadata) {
    $data = $definition->toArray();
    $data['entity'] = 'required_' . $kind;
    $data['fields'][1] = array_replace($data['fields'][1], $metadata, ['required' => true, 'minLength' => 3]);
    $generated = ProductionTemplateContext::build(app\common\crud\CrudDefinition::fromArray($data));
    eval(substr($generated['validateContent'], 5));
    preg_match('/namespace ([^;]+);/', $generated['validateContent'], $namespace);
    preg_match('/final class (\w+)/', $generated['validateContent'], $class);
    $validator = $namespace[1] . '\\' . $class[1];
    $test($kind . ' 新增必填、更新仅跳过未提交敏感字段', static function () use ($validator, $expect): void {
        $expect(!(new $validator())->check([]), '新增不可跳过必填');
        $expect((new $validator())->forUpdate(42, [])->check([]), '更新未提交敏感字段应保留旧值');
        foreach (['', null, 'ab'] as $value) {
            $payload = ['title' => $value];
            $expect(!(new $validator())->forUpdate(42, $payload)->check($payload), '显式空值或短值必须校验');
        }
        $expect((new $validator())->forUpdate(42, ['title' => 'valid'])->check(['title' => 'valid']), '有效修改应通过');
    });
}
echo "结果：{$checks} 通过，" . count($failures) . " 失败\n";
exit($failures === [] ? 0 : 1);
