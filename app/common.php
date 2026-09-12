<?php

declare(strict_types=1);

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2021/8/2
 */

use think\facade\Cache;
use think\facade\Cookie;
use think\facade\Lang;
use think\facade\Route;
use think\facade\Session;

if (!function_exists('getKeyVal')) {
    /**
     * 将平行的 key/value 输入转换为键值对。
     */
    function getKeyVal(array $kv): array
    {
        $keys = $kv['key'] ?? null;
        $values = $kv['value'] ?? null;
        if (!is_array($keys) || !is_array($values)) {
            return [];
        }

        $data = [];
        foreach ($keys as $index => $key) {
            if (!array_key_exists($index, $values)
                || (!is_string($key) && !is_int($key))
                || $key === '') {
                continue;
            }
            $data[$index] = [$key => $values[$index]];
        }
        return $data;
    }
}

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

        $cacheKey = 'syscfg:' . hash('sha256', $group . "\0" . ($code ?? ''));
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['cached'] ?? false) === true && array_key_exists('value', $cached)) {
            return $cached['value'];
        }

        $query = \app\common\model\Config::where('group', $group);
        $value = $code === null
            ? $query->column('value', 'code')
            : $query->where('code', $code)->value('value');
        Cache::set($cacheKey, ['cached' => true, 'value' => $value], 3600);
        return $value;
    }
}

// 重写 URL 助手函数。
if (!function_exists('__u')) {
    function __u(string $url = '', array $vars = [], string|bool $suffix = true, string|bool $domain = false): string
    {
        return (string) Route::buildUrl($url, $vars)->suffix($suffix)->domain($domain);
    }
}

if (!function_exists('common_translate_value')) {
    /**
     * 多语言函数的共享实现。
     */
    function common_translate_value(mixed $str, array $vars = [], string $language = ''): mixed
    {
        if (is_numeric($str) || $str === '' || $str === null || $str === false) {
            return $str;
        }
        return Lang::get((string) $str, $vars, $language);
    }
}

if (!function_exists('__')) {
    function __(mixed $str, mixed ...$arguments): mixed
    {
        $vars = isset($arguments[0]) && is_array($arguments[0]) ? $arguments[0] : $arguments;
        $language = isset($arguments[0]) && is_array($arguments[0]) && isset($arguments[1])
            ? (string) $arguments[1]
            : '';
        return common_translate_value($str, $vars, $language);
    }
}

if (!function_exists('lang')) {
    function lang(mixed $str, mixed ...$arguments): mixed
    {
        return __($str, ...$arguments);
    }
}

if (!function_exists('isHttps')) {
    function isHttps(): bool
    {
        $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
        return ($https !== '' && $https !== 'off' && $https !== '0')
            || strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https'
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}

if (!function_exists('httpType')) {
    function httpType(): string
    {
        return isHttps() ? 'https://' : 'http://';
    }
}

if (!function_exists('timeAgo')) {
    /**
     * 将过去时间转换为中文相对时间。
     */
    function timeAgo(string|int $posttime): string
    {
        $original = (string) $posttime;
        $timestamp = is_int($posttime) ? $posttime : strtotime($posttime);
        if ($timestamp === false || $timestamp <= 0) {
            return $original;
        }

        $seconds = time() - $timestamp;
        return match (true) {
            $seconds < 0 => $original,
            $seconds <= 10 => '刚刚',
            $seconds <= 30 => '刚才',
            $seconds <= 60 => '刚一会',
            $seconds <= 120 => '1分钟前',
            $seconds <= 180 => '2分钟前',
            $seconds < 3600 => intdiv($seconds, 60) . '分钟前',
            $seconds < 86400 => intdiv($seconds, 3600) . '小时前',
            $seconds < 172800 => '昨天',
            $seconds < 259200 => '前天',
            $seconds <= 1728000 => intdiv($seconds, 86400) . '天前',
            default => $original,
        };
    }
}

if (!function_exists('node')) {
    function node(string $url): bool
    {
        static $service = null;
        static $requestIdentity = null;

        $request = request();
        $currentRequestIdentity = spl_object_id($request) . ':' . $request->pathinfo();
        if ($service === null || $requestIdentity !== $currentRequestIdentity) {
            $requestIdentity = $currentRequestIdentity;
            $service = new \app\console\authorization\service\AdminAuthorizationService($request);
        }
        return $service->nodeAccess($url);
    }
}

if (!function_exists('isLogin')) {
    function isLogin(): object|array|false
    {
        $member = Session::get('member');
        if ($member === null || (!is_array($member) && !is_object($member)) || $member === []) {
            return false;
        }

        $memberId = is_array($member) ? ($member['id'] ?? null) : ($member->id ?? null);
        if ($memberId !== null) {
            Cookie::set('mid', $memberId);
        }
        return $member;
    }
}

if (!function_exists('logout')) {
    function logout(): bool
    {
        Session::delete('member');
        Cookie::delete('mid');
        return true;
    }
}

if (!function_exists('format_bytes')) {
    /**
     * 格式化字节大小，最大单位固定为 PB。
     */
    function format_bytes(int|float $size, string $delimiter = ''): string
    {
        static $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        if (!is_finite((float) $size) || $size <= 0) {
            return '0' . $delimiter . $units[0];
        }

        $unitIndex = max(0, min((int) floor(log((float) $size, 1024)), 5));
        $value = round($size / (1024 ** $unitIndex), 2);
        return (string) $value . $delimiter . $units[$unitIndex];
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

if (!function_exists('getSystemTable')) {
    /**
     * 获取系统表清单。
     */
    function getSystemTable(array $table = [], array $shift = []): array
    {
        static $tableList = [
            'plugin',
            'admin',
            'admin_log',
            'attach',
            'attach_group',
            'auth_group',
            'auth_group_inherit',
            'auth_group_department',
            'department',
            'admin_menu',
            'permission',
            'blacklist',
            'casbin_rule',
            'system_migration',
            'config',
            'config_group',
            'dict_type',
            'dict_item',
            'field_type',
            'field_verify',
            'languages',
            'member',
            'member_group',
            'member_level',
            'provinces',
            'region',
        ];

        $isValidTable = static fn (mixed $name): bool => is_string($name) && trim($name) !== '';
        $additionalTables = array_filter($table, $isValidTable);
        $excludedTables = array_filter($shift, $isValidTable);
        return array_values(array_unique(array_diff(array_merge($tableList, $additionalTables), $excludedTables)));
    }
}
