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
    private static array $cache = [];

    /**
     * 获取缓存数据，如果不存在则执行回调函数并缓存结果
     * @param string $key 缓存键名
     * @param Closure $callback 回调函数，用于获取数据
     * @param int $ttl 缓存时间（秒），0表示永久缓存到请求结束
     * @return mixed
     */
    public static function remember(string $key, Closure $callback, int $ttl = 0)
    {
        if (self::has($key)) {
            return self::$cache[$key]['data'];
        }

        $data = $callback();
        self::put($key, $data, $ttl);

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
        self::$cache[$key] = [
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
        if (self::has($key)) {
            return self::$cache[$key]['data'];
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
        if (!isset(self::$cache[$key])) {
            return false;
        }

        $cacheItem = self::$cache[$key];

        // 检查是否过期
        if ($cacheItem['expire_at'] > 0 && $cacheItem['expire_at'] < time()) {
            unset(self::$cache[$key]);
            return false;
        }

        return true;
    }
}
