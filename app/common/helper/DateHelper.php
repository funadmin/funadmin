<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/22
 */

namespace app\common\helper;

use DateTime;
use DateTimeZone;

/**
 * 日期时间处理类
 */
class DateHelper
{
    /**
     * @param $time
     * @return false|string
     * 获取当前日期时间
     */
    public static function intToDate($time)
    {
        return date('Y-m-d H:i:s', $time);
    }

    /**
     * 日期转时间戳
     *
     * @param $value
     * @return false|int
     */
    public static function dateToInt($value)
    {
        if (empty($value)) {
            return $value;
        }

        if (!is_numeric($value)) {
            return strtotime($value);
        }

        return $value;
    }

    /**
     * 格式化 UNIX 时间戳为人易读的字符串
     * @param int    Unix 时间戳
     * @param mixed $local 本地时间
     * @return    string    格式化的日期字符串
     */
    public static function humanDate($remote, $local = null)
    {
        $timediff = (is_null($local) || $local ? time() : $local) - $remote;
        $chunks = array(
            array(60 * 60 * 24 * 365, 'year'),
            array(60 * 60 * 24 * 30, 'month'),
            array(60 * 60 * 24 * 7, 'week'),
            array(60 * 60 * 24, 'day'),
            array(60 * 60, 'hour'),
            array(60, 'minute'),
            array(1, 'second')
        );

        for ($i = 0, $j = count($chunks); $i < $j; $i++) {
            $seconds = $chunks[$i][0];
            $name = $chunks[$i][1];
            if (($count = floor($timediff / $seconds)) != 0) {
                break;
            }
        }
        return lang("%d {$name}%s ago", $count, ($count > 1 ? 's' : ''));
    }
}

