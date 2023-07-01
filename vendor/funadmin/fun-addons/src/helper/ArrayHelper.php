<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/22
 */
namespace fun\helper;
class ArrayHelper
{
    /**
     * 移除空值的key
     * @param $para
     * @return array
     * @author helei
     */
    public static function arrayFilter($para)
    {
        $paraFilter = [];
        foreach($para as $key=>$val) {
            if ($val === '' || $val === null) {
                continue;
            } else {
                if (!is_array($para[$key])) {
                    $para[$key] = is_bool($para[$key]) ? $para[$key] : trim($para[$key]);
                }
                $paraFilter[$key] = $para[$key];
            }
        }
        return $paraFilter;
    }
    /**
     * 删除一位数组中，指定的key与对应的值
     * @param array $array 要操作的数组
     * @param array|string $keys 需要删除的key的数组，或者用（,）链接的字符串
     * @return array
     */
    public static function removeKeys(array $array, $keys)
    {
        if (!is_array($keys)) {// 如果不是数组，需要进行转换
            $keys = explode(',', $keys);
        }
        if (empty($keys) || !is_array($keys)) {
            return $array;
        }
        $flag = true;
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                if (is_int($key)) {
                    $flag = false;
                }
                unset($array[$key]);
            }
        }
        if (!$flag) {
            $array = array_values($array);
        }
        return $array;
    }

    /**
     * 对输入的数组进行字典排序
     * @param array $array 需要排序的数组
     * @return array
     * @author helei
     */
    public static function arraySort(array $array)
    {
        ksort($array);
        reset($array);
        return $array;
    }

    /**
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param array $array 需要拼接的数组
     * @return string
     * @throws \Exception
     */
    public static function createLinkstring($array)
    {
        if (!is_array($array)) {
            throw new \Exception('必须传入数组参数');
        }
        reset($array);
        $arg = '';
        foreach ($array as $key=>$val){
            if (is_array($val)) {
                continue;
            }
            $arg .= $key . '=' . urldecode($val) . '&';
        }
        //去掉最后一个&字符
        $arg && $arg = rtrim($arg, '&');
        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }
        return $arg;
    }

    /**
     * 解析配置
     * @param string $value 配置值
     * @return array|string
     */
    public static function parseToarr($value = '')
    {
        $array = preg_split('/[\r\n]+/', trim($value, ",;\r\n"));
        if (strpos($value, ':')) {
            $value = array();
            foreach ($array as $val) {
                list($k, $v) = explode(':', $val);
                $value[$k] = $v;
            }
        } else {
            $value = $array;
        }
        return $value;
    }
}