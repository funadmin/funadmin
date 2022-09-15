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
    'type'=>'', //simple 简单jwt 不需要数据库和redis 空 则可选redis和mysql appid appsecret必填
    'authentication'=>"Authorization",
    'jwt_key'=>'funadmin',//jwtkey，请一定记得修改
    'timeDif' => 100,//时间误差
    'refreshExpires' => 3600 * 24 * 30,   //刷新token过期时间
    'expires' => 3600 * 24,//token-有效期
    'responseType' => 'json',
    'driver'        =>'redis',//缓存或数据驱动;//redis//mysql
    'redisTokenKey'  =>'AccessToken:',//缓存键名
    'redisRefreshTokenKey'        =>'RefreshAccessToken:',//缓存键名
    'sign'        =>false,//是否需要签名 //加强安全性

];

//type simple
//获取token方式  username password timestamp

//type 為空
//获取token方式  username password timestamp appid appsecret
