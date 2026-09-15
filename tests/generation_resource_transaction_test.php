<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\admin\development\service\GenerationResourceTransaction;
use app\admin\service\ResourceRegistryService;

// 仅替换资源持久化边界，不启动数据库事务或连接。
$registry = new class extends ResourceRegistryService {
    public array $calls = [];
    public function __construct() {}
    public function removeSource(string $sourceType, string $sourceName): void { $this->calls[] = ['remove', $sourceType, $sourceName]; }
    public function registerPermissions(array $items, string $sourceType = 'system', string $sourceName = ''): void { $this->calls[] = ['permissions', $items, $sourceType, $sourceName]; }
};
try {
    $transaction = new GenerationResourceTransaction($registry);
    (new ReflectionProperty($transaction, 'active'))->setValue($transaction, true);
    $transaction->apply([['resourceKey' => 'permission|sample|generated:sample:list', 'code' => 'generated:sample:list']]);
    if (count($registry->calls) !== 2 || $registry->calls[0] !== ['remove', 'generated', 'sample']) throw new RuntimeException('资源注册未委托真实命名空间服务');

    $definition = \app\common\crud\CrudDefinition::fromArray([
        'entity' => 'order-test', 'title' => '测试可视化', 'routePath' => '/generated/order-test',
        'permissionPrefix' => 'generated:order-test', 'menu' => ['hidden' => true, 'affix' => true],
    ]);
    $service = (new ReflectionClass(\app\admin\development\service\ManagedGenerationService::class))->newInstanceWithoutConstructor();
    $resources = (new ReflectionMethod($service, 'resourcesFromDefinition'))->invoke($service, $definition);
    $menu = array_values(array_filter($resources, static fn (array $item): bool => $item['resourceType'] === 'menu'))[0];
    parse_str($menu['query'] ?? '', $meta);
    if (($meta['component'] ?? '') !== 'generated/order-test/index' || ($meta['formKey'] ?? '') !== 'order_test'
        || ($meta['name'] ?? '') !== 'OrderTest' || ($meta['type'] ?? '') !== 'C'
        || ($meta['hidden'] ?? '') !== '1' || ($meta['keepAlive'] ?? '') !== '1' || ($meta['affix'] ?? '') !== '1') {
        throw new RuntimeException('Definition 到资源计划丢失页面路由元数据');
    }
    $group = (new ReflectionMethod($transaction, 'groupBySource'))->invoke($transaction, $resources);
    if (($group['order-test']['menus'][0]['query'] ?? '') !== $menu['query']) throw new RuntimeException('资源事务丢失菜单 query');

    (new \think\App())->initialize();
    $auth = (new ReflectionClass(\app\admin\controller\authentication\AdminAuth::class))->newInstanceWithoutConstructor();
    $projection = new ReflectionMethod($auth, 'menuData');
    $row = new \app\admin\authorization\model\AdminMenu([
        'id' => 55, 'pid' => 0, 'permission_id' => 0, 'source_type' => 'generated',
        'source_name' => 'order-test', 'href' => '/generated/order-test', 'query' => '', 'name' => '测试可视化',
    ]);
    $dto = $projection->invoke($auth, $row);
    if ($dto['component'] !== 'generated/order-test/index' || $dto['formKey'] !== 'order_test') throw new RuntimeException('旧生成菜单空 query 必须只读恢复页面身份');
    if ($row->query !== '') throw new RuntimeException('兼容投影不能修改持久化模型');
    $row->query = 'component=custom/page&name=Custom&type=C&formKey=custom';
    if ($projection->invoke($auth, $row)['component'] !== 'custom/page') throw new RuntimeException('不得覆盖二开菜单元数据');
    $row->query = '';
    $row->source_type = 'admin_web';
    if ($projection->invoke($auth, $row)['component'] !== '') throw new RuntimeException('不得推断非生成菜单');
    $row->source_type = 'generated';
    $row->href = '/custom/path';
    if ($projection->invoke($auth, $row)['component'] !== '') throw new RuntimeException('不得推断不匹配的生成路由');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
echo "generation resource transaction tests: PASS\n";
