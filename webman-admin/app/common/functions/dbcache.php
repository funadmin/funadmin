<?php
use app\common\service\DbCacheService;

if (!function_exists('db_cache')) {
    /**
     * 数据库查询缓存助手函数
     * 在单次请求生命周期内缓存数据库查询结果
     *
     * @param string $key 缓存键名
     * @param Closure|null $callback 回调函数，用于获取数据
     * @param int $ttl 缓存时间（秒），0表示永久缓存到请求结束
     * @return mixed
     */
    function db_cache(string $key, ?Closure $callback = null, int $ttl = 0)
    {
        if ($callback === null) {
            // 如果没有回调函数，直接获取缓存
            return DbCacheService::get($key);
        }

        return DbCacheService::remember($key, $callback, $ttl);
    }
}
