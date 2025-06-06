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

if (!function_exists('db_cache_put')) {
    /**
     * 设置数据库缓存
     * 
     * @param string $key 缓存键名
     * @param mixed $data 缓存数据
     * @param int $ttl 缓存时间（秒）
     * @return void
     */
    function db_cache_put(string $key, $data, int $ttl = 0)
    {
        DbCacheService::put($key, $data, $ttl);
    }
}

if (!function_exists('db_cache_get')) {
    /**
     * 获取数据库缓存
     * 
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    function db_cache_get(string $key, $default = null)
    {
        return DbCacheService::get($key, $default);
    }
}

if (!function_exists('db_cache_forget')) {
    /**
     * 删除指定缓存
     * 
     * @param string $key 缓存键名
     * @return bool
     */
    function db_cache_forget(string $key): bool
    {
        return DbCacheService::forget($key);
    }
}

if (!function_exists('db_cache_flush')) {
    /**
     * 清空所有数据库缓存
     * 
     * @return void
     */
    function db_cache_flush()
    {
        DbCacheService::flush();
    }
}

if (!function_exists('db_cache_has')) {
    /**
     * 检查缓存是否存在
     * 
     * @param string $key 缓存键名
     * @return bool
     */
    function db_cache_has(string $key): bool
    {
        return DbCacheService::has($key);
    }
}

if (!function_exists('db_cache_stats')) {
    /**
     * 获取缓存统计信息
     * 
     * @return array
     */
    function db_cache_stats(): array
    {
        return DbCacheService::getStats();
    }
}

if (!function_exists('db_cache_model')) {
    /**
     * 缓存模型查询结果
     * 
     * @param string $model 模型类名
     * @param string $method 方法名
     * @param array $params 参数
     * @param Closure $callback 查询回调
     * @param int $ttl 缓存时间
     * @return mixed
     */
    function db_cache_model(string $model, string $method, array $params, Closure $callback, int $ttl = 0)
    {
        return DbCacheService::rememberModel($model, $method, $params, $callback, $ttl);
    }
}

if (!function_exists('db_cache_flush_model')) {
    /**
     * 清除模型相关的所有缓存
     * 
     * @param string $model 模型类名
     * @return int 清除的缓存数量
     */
    function db_cache_flush_model(string $model): int
    {
        return DbCacheService::flushModel($model);
    }
}

if (!function_exists('db_cache_tag')) {
    /**
     * 使用标签缓存数据
     * 
     * @param string $tag 标签名
     * @param string $key 缓存键名
     * @param Closure $callback 回调函数
     * @param int $ttl 缓存时间
     * @return mixed
     */
    function db_cache_tag(string $tag, string $key, Closure $callback, int $ttl = 0)
    {
        return DbCacheService::rememberWithTag($tag, $key, $callback, $ttl);
    }
}

if (!function_exists('db_cache_flush_tag')) {
    /**
     * 按标签清除缓存
     * 
     * @param string $tag 标签名
     * @return int 清除的缓存数量
     */
    function db_cache_flush_tag(string $tag): int
    {
        return DbCacheService::flushByTag($tag);
    }
}

if (!function_exists('cache_user_info')) {
    /**
     * 缓存用户信息的便捷方法
     * 
     * @param int $userId 用户ID
     * @param Closure|null $callback 获取用户信息的回调函数
     * @return mixed
     */
    function cache_user_info(int $userId, ?Closure $callback = null)
    {
        $key = "user_info_{$userId}";
        
        if ($callback === null) {
            return db_cache_get($key);
        }
        
        return db_cache($key, $callback, 300); // 缓存5分钟
    }
}

if (!function_exists('cache_member_info')) {
    /**
     * 缓存会员信息的便捷方法
     * 
     * @param int $memberId 会员ID
     * @param Closure|null $callback 获取会员信息的回调函数
     * @return mixed
     */
    function cache_member_info(int $memberId, ?Closure $callback = null)
    {
        $key = "member_info_{$memberId}";
        
        if ($callback === null) {
            return db_cache_get($key);
        }
        
        return db_cache($key, $callback, 300); // 缓存5分钟
    }
}

if (!function_exists('cache_config')) {
    /**
     * 缓存系统配置的便捷方法
     * 
     * @param string $configKey 配置键名
     * @param Closure|null $callback 获取配置的回调函数
     * @return mixed
     */
    function cache_config(string $configKey, ?Closure $callback = null)
    {
        $key = "sys_config_{$configKey}";
        
        if ($callback === null) {
            return db_cache_get($key);
        }
        
        return db_cache($key, $callback, 600); // 缓存10分钟
    }
}

if (!function_exists('cache_permissions')) {
    /**
     * 缓存用户权限的便捷方法
     * 
     * @param int $userId 用户ID
     * @param Closure|null $callback 获取权限的回调函数
     * @return mixed
     */
    function cache_permissions(int $userId, ?Closure $callback = null)
    {
        $key = "user_permissions_{$userId}";
        
        if ($callback === null) {
            return db_cache_get($key);
        }
        
        return db_cache($key, $callback, 600); // 缓存10分钟
    }
}