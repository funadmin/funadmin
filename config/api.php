<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/3
 */
//配置api 接口
return [
    'authentication'=>"Authorization",
    'is_jwt'=>1,////是否开启jwt配置 1开启 //开启后 token请求需要使用jwttoken接口
    'jwt_key'=>'funadmin',//jwtkey，请一定记得修改
    'timeDif' => 10000,//时间误差
    'refreshExpires' => 3600 * 24 * 30,   //刷新token过期时间
    'expires' => 3600 * 24,//token-有效期
    'responseType' => 'json',
    'authapp' => false,//是否启用appid;
    'driver'        =>'mysql',//缓存或数据驱动;//redis//mysql
    'redisTokenKey'  =>'AccessToken:',//缓存键名
    'redisRefreshTokenKey'        =>'RefreshAccessToken:',//缓存键名

];