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
 * Date: 2021/8/2
 */

use think\App;
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
            if ((!is_string($key) && !is_int($key)) || $key === '' || !array_key_exists($index, $values)) {
                continue;
            }
            $data[$index][$key] = $values[$index];
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
        $hasCode = $code !== null && $code !== '';
        $cacheKey = 'syscfg:' . hash('sha256', serialize([$group, $hasCode ? $code : null]));
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['cached'] ?? false) === true && array_key_exists('value', $cached)) {
            return $cached['value'];
        }

        $query = \app\common\model\Config::where(['group' => $group]);
        $value = $hasCode
            ? $query->where('code', $code)->value('value')
            : $query->column('value', 'code');
        Cache::set($cacheKey, ['cached' => true, 'value' => $value], 3600);
        return $value;
    }
}

if (!function_exists('Mycfg')) {
    function Mycfg(string $group, ?string $code = null): mixed
    {
        return syscfg($group, $code);
    }
}

// 重写 URL 助手函数。
if (!function_exists('__u')) {
    function __u($url = '', array $vars = [], $suffix = true, $domain = false): string
    {
        return (string) Route::buildUrl($url, $vars)->suffix($suffix)->domain($domain);
    }
}

if (!function_exists('funadmin_common_translate_value')) {
    /**
     * 多语言函数的共享实现，并保留旧式可变参数调用。
     */
    function funadmin_common_translate_value(mixed $str, mixed $vars, mixed $lang, array $arguments): mixed
    {
        if (is_numeric($str) || empty($str)) {
            return $str;
        }
        if (!is_array($vars)) {
            array_shift($arguments);
            $vars = $arguments;
            $lang = '';
        }
        return Lang::get((string) $str, $vars, (string) $lang);
    }
}

if (!function_exists('__')) {
    function __(mixed $str, mixed $vars = [], mixed $lang = ''): mixed
    {
        return funadmin_common_translate_value($str, $vars, $lang, func_get_args());
    }
}

if (!function_exists('lang')) {
    function lang(mixed $str, mixed $vars = [], mixed $lang = ''): mixed
    {
        return funadmin_common_translate_value($str, $vars, $lang, func_get_args());
    }
}

if (!function_exists('getProvicesByPid')) {
    function getProvicesByPid(int|string $pid = 0): mixed
    {
        return \app\common\model\Region::cache(true)->find($pid);
    }
}

if (!function_exists('getMember')) {
    function getMember(int|string $id): mixed
    {
        return \app\common\model\Member::cache(true)->find($id) ?: [];
    }
}

if (!function_exists('p')) {
    /**
     * 打印变量，按需终止执行。
     */
    function p(mixed $var, bool|int $die = false): void
    {
        if (!(bool) config('app.app_debug', false)) {
            throw new LogicException('p 仅允许在调试模式下使用');
        }
        print_r($var);
        if ($die) {
            die();
        }
    }
}

if (!function_exists('isMobile')) {
    function isMobile(): bool
    {
        if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
            return true;
        }
        if (isset($_SERVER['HTTP_VIA']) && stripos((string) $_SERVER['HTTP_VIA'], 'wap') !== false) {
            return true;
        }

        $clientKeywords = [
            'nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-',
            'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu',
            'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini',
            'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile',
        ];
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($userAgent !== '' && preg_match('/(' . implode('|', $clientKeywords) . ')/i', $userAgent) === 1) {
            return true;
        }

        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $wapPosition = strpos($accept, 'vnd.wap.wml');
        $htmlPosition = strpos($accept, 'text/html');
        return $wapPosition !== false && ($htmlPosition === false || $wapPosition < $htmlPosition);
    }
}

if (!function_exists('isHttps')) {
    function isHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }
        if (strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https') {
            return true;
        }
        return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
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
        if ($timestamp === false) {
            return $original;
        }

        $seconds = time() - $timestamp;
        if ($seconds < 0) {
            return $original;
        }
        if ($seconds <= 10) {
            return '刚刚';
        }
        if ($seconds <= 30) {
            return '刚才';
        }
        if ($seconds <= 60) {
            return '刚一会';
        }
        if ($seconds <= 120) {
            return '1分钟前';
        }
        if ($seconds <= 180) {
            return '2分钟前';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . '分钟前';
        }
        if ($seconds < 86400) {
            return intdiv($seconds, 3600) . '小时前';
        }
        if ($seconds < 172800) {
            return '昨天';
        }
        if ($seconds < 259200) {
            return '前天';
        }
        if ($seconds <= 1728000) {
            return intdiv($seconds, 86400) . '天前';
        }
        return $original;
    }
}

if (!function_exists('setConfig')) {
    /**
     * 任意配置文件写入已停用。
     */
    function setConfig(string $configFile, string $key, mixed $value): never
    {
        throw new LogicException('setConfig 已停用：禁止通过公共助手写入任意配置文件');
    }
}

if (!function_exists('auth')) {
    function auth(string $url): bool
    {
        return node($url);
    }
}

if (!function_exists('node')) {
    function node(string $url): bool
    {
        return (new \app\console\service\AdminAuthorizationService())->nodeAccess($url);
    }
}

if (!function_exists('isLogin')) {
    function isLogin(): mixed
    {
        $member = Session::get('member');
        if (!$member) {
            return false;
        }

        $memberId = Session::get('member.id');
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
        if (array_key_exists('mid', $_COOKIE)) {
            $_COOKIE['mid'] = '';
        }
        return true;
    }
}

if (!function_exists('getTpVersion')) {
    function getTpVersion(): string
    {
        return (string) App::VERSION;
    }
}

if (!function_exists('format_bytes')) {
    /**
     * 格式化字节大小，最大单位固定为 PB。
     */
    function format_bytes(int|float $size, string $delimiter = ''): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $size = max(0, $size);
        $unitIndex = 0;
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        return round($size, 2) . $delimiter . $units[$unitIndex];
    }
}

if (!function_exists('password')) {
    function password(string $password, int|string $type = PASSWORD_DEFAULT): string
    {
        return password_hash($password, $type);
    }
}

if (!function_exists('getSystemTable')) {
    /**
     * 获取系统表清单。
     */
    function getSystemTable(array $table = [], array $shift = []): array
    {
        $tableList = [
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

        return array_values(array_unique(array_diff(array_merge($tableList, $table), $shift)));
    }
}
