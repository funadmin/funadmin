<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\storage\StorageDriverInterface;
use app\common\storage\StorageDriverRegistry;
use app\common\storage\StoredFile;
use app\common\service\MigrationService;
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

$migrationFiles = glob(dirname(__DIR__) . '/database/migrations/*_attachment_storage_drivers.sql') ?: [];
storageExpect(count($migrationFiles) === 1, '必须存在唯一的附件存储驱动迁移');
$migration = (string) file_get_contents($migrationFiles[0]);
storageExpect(str_contains($migration, '`storage_key`'), '附件迁移必须保存与公开 URL 分离的存储对象键');
storageExpect(str_contains($migration, "`value` = 'local'"), '附件迁移必须把默认驱动设置为 local');
storageExpect(str_contains($migration, 'backend/systemstorage:update'), '附件迁移必须注册存储配置权限');

$migrationService = new MigrationService();
$reflection = new ReflectionMethod($migrationService, 'assertForwardOnly');
$reflection->setAccessible(true);
$reflection->invoke($migrationService, $migration, basename($migrationFiles[0]));

echo "storage driver tests: PASS\n";
