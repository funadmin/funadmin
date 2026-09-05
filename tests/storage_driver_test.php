<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\storage\StorageDriverInterface;
use app\common\storage\StorageDriverRegistry;
use app\common\storage\StoredFile;
use think\file\UploadedFile;

function storageExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class TestStorageDriver implements StorageDriverInterface
{
    public function name(): string
    {
        return 'test';
    }

    public function label(): string
    {
        return '测试存储';
    }

    public function source(): string
    {
        return 'plugin:test';
    }

    public function available(): bool
    {
        return true;
    }

    public function store(UploadedFile $file, string $directory, string $rule = ''): StoredFile
    {
        return new StoredFile('test/example.txt', 'https://example.test/example.txt');
    }

    public function delete(string $storageKey): bool
    {
        return $storageKey !== '';
    }
}

$registry = new StorageDriverRegistry();
storageExpect($registry->resolve()->name() === 'local', '未配置存储驱动时必须默认使用 local');
storageExpect($registry->resolve('missing')->name() === 'local', '插件驱动不可用时必须安全回退 local');

$registry->register(new TestStorageDriver());
storageExpect($registry->has('test'), '插件必须能够注册自定义存储驱动');
storageExpect($registry->resolve('test')->label() === '测试存储', '必须能够解析插件注册的存储驱动');
$drivers = $registry->all();
storageExpect(count($drivers) === 2, '驱动列表必须同时包含内置 local 与插件驱动');
storageExpect($drivers[0]['name'] === 'local' && $drivers[0]['source'] === 'core', 'local 必须是核心内置默认驱动');
storageExpect($drivers[1]['name'] === 'test' && $drivers[1]['source'] === 'plugin:test', '驱动列表必须暴露插件来源');

try {
    $registry->register(new TestStorageDriver());
    storageExpect(false, '重复驱动名称必须被拒绝');
} catch (RuntimeException) {
}

echo "storage driver tests: PASS\n";
