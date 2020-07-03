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
 * Date: 2019/9/30
 */
use think\facade\Route;
////一般路由规则，访问的url为：v1/user/1,对应的文件为Address类下的read方法
Route::get(':version/address/:id','api/:version.user/address');
//
////资源路由，详情查看tp手册资源路由
Route::resource(':version/user','api/:version.user')->app('api');
//
////生成access_token，post访问Token类下的token方法
//Route::post(':version/token','api/:version.token/accessToken')->app('api');;
//Route::post(':version/token/refresh','api/:version.token/refresh')->app('api');
//Route::rule('blog/:id','api/:v1.order/changeOrder');

