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
    public function registerLanguageLines(array $items, string $sourceType = 'system', string $sourceName = ''): array
    {
        $this->calls[] = ['languageLines', $items, $sourceType, $sourceName];
        return ['inserted' => 2, 'skipped' => 1];
    }
};
try {
    $transaction = new GenerationResourceTransaction($registry);
    (new ReflectionProperty($transaction, 'active'))->setValue($transaction, true);
    $applyResult = $transaction->apply([['resourceKey' => 'permission|sample|generated:sample:list', 'code' => 'generated:sample:list']]);
    if (count($registry->calls) !== 2 || $registry->calls[0] !== ['remove', 'generated', 'sample']) throw new RuntimeException('资源注册未委托真实命名空间服务');
    if ($applyResult !== ['inserted' => 0, 'skipped' => 0]) throw new RuntimeException('无语言行资源时 apply 必须返回零计数');

    $definition = \app\common\crud\CrudDefinition::fromArray([
        'entity' => 'order-test', 'title' => '测试可视化', 'routePath' => '/generated/order-test',
        'permissionPrefix' => 'generated:order-test', 'menu' => ['hidden' => true, 'affix' => true],
    ]);
    $service = (new ReflectionClass(\app\admin\development\service\ManagedGenerationService::class))->newInstanceWithoutConstructor();
    $resources = (new ReflectionMethod($service, 'resourcesFromDefinition'))->invoke($service, $definition);
    $menu = array_values(array_filter($resources, static fn (array $item): bool => $item['resourceType'] === 'menu'))[0];
    parse_str($menu['query'] ?? '', $meta);
    if (($meta['permission'] ?? '') !== 'generated:order-test:list') {
        throw new RuntimeException('Definition 到资源计划必须生成 canonical 页面权限');
    }
    if (($meta['component'] ?? '') !== 'generated/order-test/index' || ($meta['formKey'] ?? '') !== 'order_test'
        || ($meta['name'] ?? '') !== 'OrderTest' || ($meta['type'] ?? '') !== 'C'
        || ($meta['hidden'] ?? '') !== '1' || ($meta['keepAlive'] ?? '') !== '1' || ($meta['affix'] ?? '') !== '1') {
        throw new RuntimeException('Definition 到资源计划丢失页面路由元数据');
    }
    $group = (new ReflectionMethod($transaction, 'groupBySource'))->invoke($transaction, $resources);
    if (($group['order-test']['menus'][0]['query'] ?? '') !== $menu['query']) throw new RuntimeException('资源事务丢失菜单 query');

    // 语言行资源：由模板上下文收集的 key 确定性派生，插件目标不落库。
    $pack = [
        'zh-cn' => ['crud.order-test.field.name' => '名称', 'crud.order-test.page.title' => '测试可视化'],
        'en-us' => ['crud.order-test.field.name' => 'Name', 'crud.order-test.page.title' => 'Order Test'],
        'placeholders' => [],
    ];
    $langResources = (new ReflectionMethod($service, 'languageResources'))->invoke($service, $definition, $pack);
    if (count($langResources) !== 4) throw new RuntimeException('语言行资源必须覆盖 zh-cn 与 en-us 全量 key');
    $first = $langResources[0];
    if ($first['resourceType'] !== 'language' || $first['sourceName'] !== 'order-test'
        || $first['resourceKey'] !== 'language|order-test|zh-cn|crud.order-test.field.name'
        || $first['locale'] !== 'zh-cn' || $first['langKey'] !== 'crud.order-test.field.name' || $first['value'] !== '名称') {
        throw new RuntimeException('语言行资源必须携带 locale/langKey/value 与稳定键');
    }
    $pluginDefinition = \app\common\crud\CrudDefinition::fromArray([
        'entity' => 'order-test', 'title' => '测试可视化', 'routePath' => '/generated/order-test',
        'permissionPrefix' => 'generated:order-test', 'target' => ['type' => 'plugin', 'plugin' => 'demo', 'scope' => 'admin'],
    ]);
    if ((new ReflectionMethod($service, 'languageResources'))->invoke($service, $pluginDefinition, $pack) !== []) {
        throw new RuntimeException('插件目标不得派生语言行资源');
    }
    $langGroup = (new ReflectionMethod($transaction, 'groupBySource'))->invoke($transaction, $langResources);
    if (count($langGroup['order-test']['languageLines'] ?? []) !== 4
        || ($langGroup['order-test']['languageLines'][0]['key'] ?? '') !== 'crud.order-test.field.name') {
        throw new RuntimeException('groupBySource 必须按 sourceName 分组语言行');
    }
    $registry->calls = [];
    $applyResult = $transaction->apply($langResources);
    if ($applyResult !== ['inserted' => 2, 'skipped' => 1]) throw new RuntimeException('apply 必须汇总语言行 inserted/skipped 计数');
    $langCall = array_values(array_filter($registry->calls, static fn (array $call): bool => $call[0] === 'languageLines'))[0] ?? null;
    if ($langCall === null || $langCall[2] !== 'generated' || $langCall[3] !== 'order-test' || count($langCall[1]) !== 4) {
        throw new RuntimeException('语言行必须委托 registerLanguageLines 按 generated 来源注册');
    }

    (new \think\App())->initialize();
    $row = new \app\admin\authorization\model\AdminMenu([
        'id' => 55, 'pid' => 0, 'permission_id' => 0, 'source_type' => 'generated',
        'source_name' => 'order-test', 'href' => '/generated/order-test', 'query' => '', 'name' => '测试可视化',
    ]);
    $dto = $row->toWebMenuData();
    if ($dto['component'] !== 'generated/order-test/index' || $dto['formKey'] !== 'order_test') throw new RuntimeException('旧生成菜单空 query 必须只读恢复页面身份');
    if ($row->query !== '') throw new RuntimeException('兼容投影不能修改持久化模型');
    $row->query = 'component=custom/page&name=Custom&type=C&formKey=custom';
    if ($row->toWebMenuData()['component'] !== 'custom/page') throw new RuntimeException('不得覆盖二开菜单元数据');
    $row->query = '';
    $row->source_type = 'admin_web';
    if ($row->toWebMenuData()['component'] !== '') throw new RuntimeException('不得推断非生成菜单');
    $row->source_type = 'generated';
    $row->href = '/custom/path';
    if ($row->toWebMenuData()['component'] !== '') throw new RuntimeException('不得推断不匹配的生成路由');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
echo "generation resource transaction tests: PASS\n";
