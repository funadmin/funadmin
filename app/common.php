<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2021/8/2
 */

use think\App;
use think\facade\Cache;
use think\facade\Route;
use think\facade\Cookie;
use think\facade\Session;
use think\facade\Db;

if (!function_exists('syscfg')) {
    /**
     * @param $group
     * @param null $code
     * @return array|mixed|object|App
     */
    function syscfg($group, $code = null)
    {
        $where = ['group' => $group];
        $value = empty($code) ? cache("syscfg_{$group}") : cache("syscfg_{$group}_{$code}");
        if (!empty($value)) {
            return $value;
        }
        if (!empty($code)) {
            $where['code'] = $code;
            $value = \app\common\model\Config::where($where)->value('value');
            cache("syscfg_{$group}_{$code}", $value, 3600);
        } else {
            $value = \app\common\model\Config::where($where)->column('value', 'code');
            cache("syscfg_{$group}", $value, 3600);
        }
        return $value;

    }
}

//重写url 助手函数
if (!function_exists('__u')) {

    function __u($url = '', array $vars = [], $suffix = true, $domain = false)
    {
        $url =(string) Route::buildUrl($url, $vars)->suffix($suffix)->domain($domain);
        return $url;
    }
}

/**多语言函数*/
if (!function_exists('__')) {
    function __($str, $vars = [], $lang = '')
    {
        if (is_numeric($str) || empty($str)) {
            return $str;
        }
        if (!is_array($vars)) {
            $vars = func_get_args();
            array_shift($vars);
            $lang = '';
        }
        return \think\facade\Lang::get($str, $vars, $lang);
    }
}
if (!function_exists('lang')) {
    function lang($str, $vars = [], $lang = '')
    {
        if (is_numeric($str) || empty($str)) {
            return $str;
        }
        if (!is_array($vars)) {
            $vars = func_get_args();
            array_shift($vars);
            $lang = '';
        }
        return \think\facade\Lang::get($str, $vars, $lang);
    }
}

if (!function_exists("_getProvicesByPid")) {
    function _getProvicesByPid($pid = 0)
    {
        return  \app\common\model\Provinces::cache(true)->find($pid);
    }
}

if (!function_exists("_getMember")) {
    function _getMember($id)
    {
        $member = \app\common\model\Member::cache(true)->find($id);
        if ($member) {
            return $member;
        }
        return [];
    }
}
/**
 * 打印
 */
if (!function_exists('p')) {
    function p($var, $die = 0)
    {
        print_r($var);
        $die && die();
    }
}
/**
 * 手机
 */
if (!function_exists('isMobile')) {
    function isMobile()
    {
        if (isset ($_SERVER['HTTP_X_WAP_PROFILE'])) {
            return true;
        }
        if (isset ($_SERVER['HTTP_VIA'])) {
            return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
        }
        if (isset ($_SERVER['HTTP_USER_AGENT'])) {
            $clientkeywords = array('nokia',
                'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel',
                'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce',
                'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile'
            );
            if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
                return true;
            }
        }
        if (isset ($_SERVER['HTTP_ACCEPT'])) {
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
                return true;
            }
        }
        return false;
    }
}

//是否https;

if (!function_exists('isHttps')) {
    function isHttps()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }
        return false;
    }
}

/**
 * 获取http类型
 */
if (!function_exists('httpType')) {
    /**
     * http 类型
     * @return string
     */
    function httpType()
    {
        return $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

    }
}

if (!function_exists('timeAgo')) {
    /**
     * 从前
     * @param $posttime
     * @return string
     */
    function timeAgo($posttime)
    {
        //当前时间的时间戳
        $nowtimes = strtotime(date('Y-m-d H:i:s'), time());
        //之前时间参数的时间戳
        $posttimes = strtotime($posttime);
        //相差时间戳
        $counttime = $nowtimes - $posttimes;
        //进行时间转换
        if ($counttime <= 10) {
            return '刚刚';
        } else if ($counttime > 10 && $counttime <= 30) {
            return '刚才';
        } else if ($counttime > 30 && $counttime <= 60) {
            return '刚一会';
        } else if ($counttime > 60 && $counttime <= 120) {
            return '1分钟前';
        } else if ($counttime > 120 && $counttime <= 180) {
            return '2分钟前';
        } else if ($counttime > 180 && $counttime < 3600) {
            return intval(($counttime / 60)) . '分钟前';
        } else if ($counttime >= 3600 && $counttime < 3600 * 24) {
            return intval(($counttime / 3600)) . '小时前';
        } else if ($counttime >= 3600 * 24 && $counttime < 3600 * 24 * 2) {
            return '昨天';
        } else if ($counttime >= 3600 * 24 * 2 && $counttime < 3600 * 24 * 3) {
            return '前天';
        } else if ($counttime >= 3600 * 24 * 3 && $counttime <= 3600 * 24 * 20) {
            return intval(($counttime / (3600 * 24))) . '天前';
        } else {
            return $posttime;
        }
    }
    /**
     * 导入数据库
     */
    if (!function_exists('importSqlData')) {
        /**
         * http 类型
         * @return string
         */
        function importSqlData($sqlFile)
        {
            $lines = file($sqlFile);
            $sqlLine = '';
            foreach ($lines as $line) {
                if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 2) == '/*')
                    continue;
                $sqlLine .= $line;
                if (substr(trim($line), -1, 1) == ';' and $line != 'COMMIT;') {
                    $sqlLine = str_ireplace('fun_', config('database.connections.mysql.prefix'), $sqlLine);
                    $sqlLine = str_ireplace('__PREFIX__', config('database.connections.mysql.prefix'), $sqlLine);
                    $sqlLine = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $sqlLine);
                    try {
                        Db::execute($sqlLine);
                    } catch (\PDOException $e) {
                        throw new PDOException($e->getMessage());
                    }
                    $sqlLine = '';
                }
            }
        }
    }

    /**
     * 动态永久修改 config 文件内容
     * @param $key
     * @param $value
     * @return bool|int
     */
    if (!function_exists('setConfig')) {
        function setConfig($configFile,$key, $value)
        {
            $config = file_get_contents($configFile); //加载配置文件
            $config = preg_replace("/'{$key}'.*?=>.*?'.*?'/", "'{$key}' => '{$value}'", $config);
            return file_put_contents($configFile, $config); // 写入配置文件
        }
    }

}

/**
 * 权限 文件内容
 * @param $key
 * @param $value
 * @return bool|int
 */
if (!function_exists('auth')) {
    function auth($url)
    {
        $auth = new \app\backend\service\AuthService();
        return $auth->authNode($url);
    }
}


/**
 * 是否登录
 * @param $key
 * @param $value
 * @return bool|int
 */
if (!function_exists('isLogin')) {
    function isLogin()
    {
        if (session('member')) {
            \think\facade\Cookie::set('mid', session('member.id'));//跨域
            return session('member');
        } else if(!empty(\think\facade\Cookie::get('mid'))) {
            $member = \app\common\model\Member::withoutField('password')->find(Cookie::get('mid'));
            session('member',$member);
            return $member;
        }else{
            return false;
        }
    }
}

if (!function_exists('logout')) {
    function logout()
    {
        Session::delete('member');
        Cookie::delete('mid');
        if(!empty($_COOKIE['mid'])) $_COOKIE['mid'] = '';
        return true;

    }
}
/**
 * 获取版本号
 * @param $key
 * @param $value
 * @return bool|int
 */
if (!function_exists('getTpVersion')) {
    function getTpVersion()
    {
        return App::VERSION;
    }
}