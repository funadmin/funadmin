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
 * Date: 2021/6/2
 * Time: 16:01
 */

/**
 * 动态永久修改 config 文件内容
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