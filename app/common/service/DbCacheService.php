<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace app\common\service;

use Closure;

/**
 * 数据库缓存服务类
 * 用于在单次请求生命周期内缓存数据库查询结果，减少重复查询
 * Class DbCacheService
 * @package app\common\service
 */
class DbCacheService extends AbstractService
{
    /**
     * 缓存存储数组
     * @var array
     */
    private static $cache = [];

    /**
     * 缓存统计信息
     * @var array
     */
    private static $stats = [
        'hits' => 0,      // 缓存命中次数
        'misses' => 0,    // 缓存未命中次数
        'queries' => 0,   // 总查询次数
    ];

    /**
     * 获取缓存数据，如果不存在则执行回调函数并缓存结果
     * @param string $key 缓存键名
     * @param Closure $callback 回调函数，用于获取数据
     * @param int $ttl 缓存时间（秒），0表示永久缓存到请求结束
     * @return mixed
     */
    public static function remember(string $key, Closure $callback, int $ttl = 0)
    {
        self::$stats['queries']++;
        
        // 生成完整的缓存键名
        $fullKey = self::generateKey($key);
        
        // 检查缓存是否存在且未过期
        if (self::has($fullKey)) {
            self::$stats['hits']++;
            return self::$cache[$fullKey]['data'];
        }
        
        // 缓存未命中，执行回调函数获取数据
        self::$stats['misses']++;
        $data = $callback();
        
        // 缓存数据
        self::put($fullKey, $data, $ttl);
        
        return $data;
    }

    /**
     * 直接设置缓存数据
     * @param string $key 缓存键名
     * @param mixed $data 缓存数据
     * @param int $ttl 缓存时间（秒），0表示永久缓存
     * @return void
     */
    public static function put(string $key, $data, int $ttl = 0)
    {
        $fullKey = self::generateKey($key);
        
        self::$cache[$fullKey] = [
            'data' => $data,
            'expire_at' => $ttl > 0 ? time() + $ttl : 0,
            'created_at' => time()
        ];
    }

    /**
     * 获取缓存数据
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $fullKey = self::generateKey($key);
        
        if (self::has($fullKey)) {
            return self::$cache[$fullKey]['data'];
        }
        
        return $default;
    }

    /**
     * 检查缓存是否存在且未过期
     * @param string $key 缓存键名
     * @return bool
     */
    public static function has(string $key): bool
    {
        $fullKey = self::generateKey($key);
        
        if (!isset(self::$cache[$fullKey])) {
            return false;
        }
        
        $cacheItem = self::$cache[$fullKey];
        
        // 检查是否过期
        if ($cacheItem['expire_at'] > 0 && $cacheItem['expire_at'] < time()) {
            unset(self::$cache[$fullKey]);
            return false;
        }
        
        return true;
    }

    /**
     * 删除指定缓存
     * @param string $key 缓存键名
     * @return bool
     */
    public static function forget(string $key): bool
    {
        $fullKey = self::generateKey($key);
        
        if (isset(self::$cache[$fullKey])) {
            unset(self::$cache[$fullKey]);
            return true;
        }
        
        return false;
    }

    /**
     * 清空所有缓存
     * @return void
     */
    public static function flush()
    {
        self::$cache = [];
        self::$stats = [
            'hits' => 0,
            'misses' => 0,
            'queries' => 0,
        ];
    }

    /**
     * 按标签清除缓存
     * @param string $tag 标签名
     * @return int 清除的缓存数量
     */
    public static function flushByTag(string $tag): int
    {
        $count = 0;
        $tagPrefix = "tag:{$tag}:";
        
        foreach (self::$cache as $key => $value) {
            if (strpos($key, $tagPrefix) === 0) {
                unset(self::$cache[$key]);
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * 获取缓存统计信息
     * @return array
     */
    public static function getStats(): array
    {
        $hitRate = self::$stats['queries'] > 0 
            ? round((self::$stats['hits'] / self::$stats['queries']) * 100, 2) 
            : 0;
            
        return array_merge(self::$stats, [
            'hit_rate' => $hitRate . '%',
            'cache_count' => count(self::$cache),
            'memory_usage' => self::getMemoryUsage()
        ]);
    }

    /**
     * 批量缓存数据
     * @param array $items 键值对数组
     * @param int $ttl 缓存时间
     * @return void
     */
    public static function putMany(array $items, int $ttl = 0)
    {
        foreach ($items as $key => $value) {
            self::put($key, $value, $ttl);
        }
    }

    /**
     * 批量获取缓存数据
     * @param array $keys 键名数组
     * @return array
     */
    public static function getMany(array $keys): array
    {
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = self::get($key);
        }
        
        return $result;
    }

    /**
     * 生成完整的缓存键名
     * @param string $key 原始键名
     * @return string
     */
    private static function generateKey(string $key): string
    {
        // 如果键名已经包含标签前缀，直接返回
        if (strpos($key, 'tag:') === 0) {
            return $key;
        }
        
        return $key;
    }

    /**
     * 计算缓存占用内存大小
     * @return string
     */
    private static function getMemoryUsage(): string
    {
        $size = strlen(serialize(self::$cache));
        
        if ($size < 1024) {
            return $size . ' B';
        } elseif ($size < 1024 * 1024) {
            return round($size / 1024, 2) . ' KB';
        } else {
            return round($size / (1024 * 1024), 2) . ' MB';
        }
    }

    /**
     * 使用标签缓存数据
     * @param string $tag 标签名
     * @param string $key 缓存键名
     * @param Closure $callback 回调函数
     * @param int $ttl 缓存时间
     * @return mixed
     */
    public static function rememberWithTag(string $tag, string $key, Closure $callback, int $ttl = 0)
    {
        $taggedKey = "tag:{$tag}:{$key}";
        return self::remember($taggedKey, $callback, $ttl);
    }

    /**
     * 使用标签设置缓存
     * @param string $tag 标签名
     * @param string $key 缓存键名
     * @param mixed $data 缓存数据
     * @param int $ttl 缓存时间
     * @return void
     */
    public static function putWithTag(string $tag, string $key, $data, int $ttl = 0)
    {
        $taggedKey = "tag:{$tag}:{$key}";
        self::put($taggedKey, $data, $ttl);
    }

    /**
     * 缓存模型查询结果
     * @param string $model 模型类名
     * @param string $method 方法名
     * @param array $params 参数
     * @param Closure $callback 查询回调
     * @param int $ttl 缓存时间
     * @return mixed
     */
    public static function rememberModel(string $model, string $method, array $params, Closure $callback, int $ttl = 0)
    {
        $key = "model:{$model}:{$method}:" . md5(serialize($params));
        return self::remember($key, $callback, $ttl);
    }

    /**
     * 清除模型相关的所有缓存
     * @param string $model 模型类名
     * @return int 清除的缓存数量
     */
    public static function flushModel(string $model): int
    {
        $count = 0;
        $modelPrefix = "model:{$model}:";
        
        foreach (self::$cache as $key => $value) {
            if (strpos($key, $modelPrefix) === 0) {
                unset(self::$cache[$key]);
                $count++;
            }
        }
        
        return $count;
    }
} 