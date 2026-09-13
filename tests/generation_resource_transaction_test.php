<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\development\service\GenerationResourceTransaction;
use app\console\service\ResourceRegistryService;

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
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
echo "generation resource transaction tests: PASS\n";
