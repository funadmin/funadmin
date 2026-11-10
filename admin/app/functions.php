<?php

declare(strict_types=1);

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新webman实现
 * ============================================================================
 * Author: yuege
 * Date: 2021/8/2
 */

use support\Cache;

if (!function_exists('syscfg')) {
    /**
     * 获取系统配置，包含空值的缓存结果也视为命中。
     */
    function syscfg(string $group, ?string $code = null): mixed
    {
        $group = trim($group);
        if ($group === '') {
            throw new InvalidArgumentException('配置分组不能为空');
        }
        if ($code !== null) {
            $code = trim($code);
            if ($code === '') {
                throw new InvalidArgumentException('配置编码不能为空，读取整组配置请传入 null');
            }
        }

        $cacheKey = syscfg_cache_key($group, $code);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['cached'] ?? false) === true && array_key_exists('value', $cached)) {
            return $cached['value'];
        }

        $query = \app\common\model\Config::where('group', $group)->where('status', 1);
        $value = $code === null
            ? $query->pluck('value', 'code')->all()
            : $query->where('code', $code)->value('value');
        Cache::set($cacheKey, ['cached' => true, 'value' => $value], 3600);
        return $value;
    }
}

if (!function_exists('syscfg_cache_key')) {
    /** syscfg() 的缓存键：整组读取与单项读取各占一个键。 */
    function syscfg_cache_key(string $group, ?string $code = null): string
    {
        return 'syscfg_' . hash('sha256', trim($group) . "\0" . trim((string) $code));
    }
}

if (!function_exists('syscfg_forget')) {
    /** 失效一个配置分组的整组缓存，以及（若给出）该分组下单个配置项的缓存；不触碰其他缓存键。 */
    function syscfg_forget(string $group, ?string $code = null): void
    {
        if (trim($group) === '') {
            return;
        }
        Cache::delete(syscfg_cache_key($group));
        if ($code !== null && trim($code) !== '') {
            Cache::delete(syscfg_cache_key($group, $code));
        }
    }
}

if (!function_exists('password')) {
    function password(string $password, int|string $algorithm = PASSWORD_DEFAULT, array $options = []): string
    {
        $hash = password_hash($password, $algorithm, $options);
        if ($hash === false) {
            throw new RuntimeException('密码哈希生成失败');
        }
        return $hash;
    }
}

