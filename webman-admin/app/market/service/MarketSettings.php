<?php

declare(strict_types=1);

namespace app\market\service;

use RuntimeException;

/** 插件市场配置与私有存储路径。 */
final class MarketSettings
{
    public const CODE = 'market';

    public static function value(string $key, mixed $default = null): mixed
    {
        $file = root_path() . 'plugins' . DIRECTORY_SEPARATOR . self::CODE . DIRECTORY_SEPARATOR . 'config.php';
        $config = is_file($file) ? include $file : [];
        $definition = is_array($config) ? ($config[$key] ?? null) : null;
        return is_array($definition) && array_key_exists('value', $definition) ? $definition['value'] : $default;
    }

    public static function intValue(string $key, int $default, int $min, int $max): int
    {
        $value = (int) self::value($key, $default);
        return $value < $min || $value > $max ? $default : $value;
    }

    /**
     * 插件 storage 由管理端安装流程在 runtime/admin/plugins-data 下创建，
     * 而 runtime_path() 随当前应用变化，因此按项目根目录定位。
     */
    public static function storagePath(string $relative = ''): string
    {
        $runtime = root_path() . 'runtime' . DIRECTORY_SEPARATOR;
        $candidates = [
            $runtime . 'admin' . DIRECTORY_SEPARATOR . 'plugins-data' . DIRECTORY_SEPARATOR . self::CODE . DIRECTORY_SEPARATOR . 'storage',
            $runtime . 'plugins-data' . DIRECTORY_SEPARATOR . self::CODE . DIRECTORY_SEPARATOR . 'storage',
        ];
        $root = $candidates[0];
        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                $root = $candidate;
                break;
            }
        }
        $path = $relative === '' ? $root : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        return $path;
    }

    public static function ensureDirectory(string $directory, int $mode = 0750): string
    {
        if (!is_dir($directory) && !mkdir($directory, $mode, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建插件市场存储目录');
        }
        return $directory;
    }

    /** 客户端 PLUGIN_MARKETPLACE_URL 对应的根地址，不含 /api/v3。 */
    public static function publicUrl(): string
    {
        $configured = rtrim(trim((string) self::value('public_url', '')), '/');
        if ($configured !== '') {
            return $configured;
        }
        return rtrim(request()->domain(), '/') . '/' . self::CODE;
    }
}
