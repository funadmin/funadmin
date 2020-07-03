<?php
/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/26
 */
namespace speed\helper;
class SignHelper{
    /**
     * 数据后台签名加密认证
     * @param  array  $data 被认证的数据
     * @return string       签名
     */
    public static function authSign($data) {
        //数据类型检测
        if(!is_array($data)){
            $data = (array)$data;
        }
        ksort($data); //排序
        $code = http_build_query($data); //url编码并生成query字符串
        $sign = sha1($code); //生成签名
        return $sign;
    }

    public static function passwordSalt($cost=12){

        return ['cost'=>$cost];
    }

}