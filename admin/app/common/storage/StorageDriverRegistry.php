<?php

declare(strict_types=1);

namespace app\common\storage;

use RuntimeException;

/**
 * 存储驱动注册表。核心注册 local，插件服务可注册其他驱动。
 */
final class StorageDriverRegistry
{
    /** @var array<string, StorageDriverInterface> */
    private array $drivers = [];

    public function __construct(?StorageDriverInterface $local = null, private readonly mixed $filter = null)
    {
        $this->register($local ?? new LocalStorageDriver());
    }

    public function register(StorageDriverInterface $driver): void
    {
        $name = strtolower(trim($driver->name()));
        if (!preg_match('/^[a-z][a-z0-9_]{0,29}$/', $name)) {
            throw new RuntimeException('存储驱动名称格式不正确');
        }
        if (isset($this->drivers[$name])) {
            throw new RuntimeException("存储驱动 {$name} 已注册");
        }
        $this->drivers[$name] = $driver;
    }

    public function has(string $name): bool
    {
        $name = strtolower(trim($name));
        return isset($this->drivers[$name]) && $this->drivers[$name]->available();
    }

    public function resolve(?string $name = null): StorageDriverInterface
    {
        $name = strtolower(trim((string) $name));
        if ($name !== '' && $this->has($name)) {
            return $this->drivers[$name];
        }
        return $this->drivers['local'];
    }

    /** Select exactly once per final upload. Runtime contributions are never cached. */
    public function forUpload(?string $name = null): StorageDriverInterface
    {
        $driver = $this->apply('storage.upload.resolve', $this->resolve($name), ['requested' => $name]);
        if (!$driver instanceof StorageDriverInterface || !$driver->available()) throw new RuntimeException('上传存储驱动不可用，请检查存储插件配置');
        return $driver;
    }

    /** Historical storage must never fall back to an unrelated location. */
    public function require(string $name): StorageDriverInterface
    {
        $driver = $this->drivers[$name] ?? $this->apply('storage.driver.resolve', null, ['name' => $name]);
        if (!$driver instanceof StorageDriverInterface || $driver->name() !== $name || !$driver->available()) throw new RuntimeException('原存储驱动不可用，请启用对应插件并检查原存储配置');
        return $driver;
    }

    private function apply(string $point, mixed $value, array $context = []): mixed
    {
        return $this->filter === null ? \app\common\plugin\hook\Hook::filter($point, $value, $context) : ($this->filter)($point, $value, $context);
    }

    public function all(): array
    {
        $result = [];
        foreach ($this->drivers as $driver) {
            $result[] = [
                'name' => $driver->name(),
                'label' => $driver->label(),
                'source' => $driver->source(),
                'available' => $driver->available(),
            ];
        }
        $result = $this->apply('storage.drivers', $result);
        if (!is_array($result)) throw new RuntimeException('存储插件返回无效驱动列表');
        return $result;
    }
}
