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

        $cacheKey = 'syscfg_' . hash('sha256', $group . "\0" . ($code ?? ''));
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

