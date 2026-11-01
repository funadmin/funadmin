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

    public function __construct(?StorageDriverInterface $local = null)
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
        return $result;
    }
}
