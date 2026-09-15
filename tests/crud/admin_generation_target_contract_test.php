<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/vendor/topthink/framework/src/helper.php';

use app\admin\development\service\FormCrudDefinitionFactory;
use app\common\crud\ProductionTemplateContext;

$root = dirname(__DIR__, 2);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
foreach ([
    'app/admin/controller/generated/TestController.php', 'app/admin/model/Test.php', 'app/admin/service/TestService.php', 'app/admin/validate/TestValidate.php',
    'app/admin/controller/generated/OrderTestController.php', 'app/admin/model/generated/OrderTest.php', 'app/admin/service/generated/OrderTestService.php', 'app/admin/validate/generated/OrderTestValidate.php',
    'plugins/shop/app/console/controller/ProductController.php', 'plugins/shop/app/console/model/Product.php', 'plugins/shop/app/console/service/ProductService.php', 'plugins/shop/app/console/validate/ProductValidate.php',
    'plugins/shop/app/shop/controller/ProductController.php', 'plugins/shop/app/shop/model/Product.php', 'plugins/shop/app/shop/service/ProductService.php', 'plugins/shop/app/shop/validate/ProductValidate.php',
    'plugins/shop/admin-web/product/index.vue', 'plugins/shop/admin-web/product/api.ts', 'admin-web/src/api/generated/test.ts', 'admin-web/src/api/generated/order-test.ts',
    'admin-web/src/views/generated/test/index.vue', 'admin-web/src/views/generated/order-test/index.vue'
] as $legacyPath) {
    $expect(!is_file($root . '/' . $legacyPath), '旧生成业务文件必须删除：' . $legacyPath);
}
$form = [
    'form_key' => 'contract_order', 'table_name' => 'fun_contract_order', 'name' => '契约订单',
    'fields' => [['field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(80)']],
];
$definition = (new FormCrudDefinitionFactory())->create($form);
$targets = $definition->get('generationTargets');
foreach (['model', 'validate', 'service', 'controller'] as $artifact) {
    $expect(str_starts_with((string) $targets[$artifact], 'app/admin/'), "{$artifact} 必须生成到 app/admin");
    $expect(!str_starts_with((string) $targets[$artifact], 'app/console/'), "{$artifact} 不得生成到 app/console");
}
$context = ProductionTemplateContext::build($definition);
$expect(str_contains($context['modelContent'], 'namespace app\\admin\\model\\generated;'), '模型 namespace 必须与 admin 路径匹配');
$expect(str_contains($context['validateContent'], 'namespace app\\admin\\validate\\generated;'), '验证器 namespace 必须与 admin 路径匹配');
$expect(str_contains($context['serviceContent'], 'namespace app\\admin\\service\\generated;'), '服务 namespace 必须与 admin 路径匹配');
$expect(str_contains($context['controllerContent'], 'namespace app\\admin\\controller\\generated;'), '控制器 namespace 必须与 admin 路径匹配');
$expect(str_contains($context['controllerContent'], "use app\\admin\\validate\\generated\\ContractOrderValidate;"), '控制器验证器导入必须使用 admin namespace');
$expect(str_contains($context['controllerContent'], "use app\\admin\\service\\generated\\ContractOrderService;"), '控制器服务导入必须使用 admin namespace');
echo "PASS: admin CRUD generation target contract\n";
