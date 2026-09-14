<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/vendor/topthink/framework/src/helper.php';

use app\common\crud\ProductionTemplateContext;
use app\console\controller\generated\OrderTestController;
use app\console\model\generated\OrderTest;

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
$test('真实 OrderTest 模型可自动加载', static function () use ($expect): void {
    $expect(class_exists(OrderTest::class), '模型加载失败');
});
$definition = (new app\console\development\service\FormCrudDefinitionFactory())->create([
    'form_key' => 'order_runtime_fixture', 'table_name' => 'fun_test', 'name' => '隔离列表',
    'fields' => [['field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(80)']],
]);
$test('默认模板生成的子命名空间模型无需补丁可加载', static function () use ($definition, $expect): void {
    $context = ProductionTemplateContext::build($definition);
    eval(substr($context['modelContent'], 5));
    $expect(class_exists('app\\console\\model\\generated\\OrderRuntimeFixture'), '生成模型不可加载');
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
$test('真实控制器列表：非空外键、分页、软删除及物理表名', static function () use ($db, $app, $expect): void {
    $db->execute('CREATE TABLE fun_test (id INTEGER PRIMARY KEY, field_1 TEXT, field_2 TEXT, field_3 TEXT, field_4 TEXT, field_5 INTEGER, field_6 TEXT, field_7 TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
    $db->execute("INSERT INTO fun_test VALUES (1,'7','a','','',1,'[]','[]',NULL,NULL,NULL),(2,'8','b','','',2,'[]','[]',NULL,NULL,'2026-09-13 00:00:00')");
    $controller = (new ReflectionClass(OrderTestController::class))->newInstanceWithoutConstructor();
    $request = new app\Request();
    $app->instance('request', $request);
    (new ReflectionProperty(app\BaseController::class, 'request'))->setValue($controller, $request);
    foreach ([0 => 1, 1 => 2] as $recycled => $id) {
        $request->withGet(['page' => 1, 'pageSize' => 20, 'recycled' => $recycled]);
        $payload = $controller->index()->getData();
        if (is_string($payload)) $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $expect($payload['code'] === 200 && $payload['data']['total'] === 1, '列表响应或软删除过滤错误');
        $expect($payload['data']['list'][0]['id'] === $id, '列表记录错误');
        $expect($payload['data']['list'][0]['field1'] === (string) ($id + 6), '必须保留外键标量');
    }
    $request->withGet(['page' => 2, 'pageSize' => 20, 'recycled' => 0]);
    $payload = $controller->index()->getData();
    $expect($payload['data']['list'] === [] && $payload['data']['total'] === 1, '空页必须保持总数');
    $db->execute('UPDATE fun_test SET field_1 = NULL WHERE id = 1');
    $request->withGet(['page' => 1, 'pageSize' => 20, 'recycled' => 0]);
    $payload = $controller->index()->getData();
    $expect($payload['data']['list'][0]['field1'] === '', '空外键必须正常输出');
});
echo "结果：{$checks} 通过，" . count($failures) . " 失败\n";
exit($failures === [] ? 0 : 1);
