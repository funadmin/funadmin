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
 * Date: 2019/9/30
 */
use think\facade\Route;
////一般路由规则，访问的url为：v1/member/1,对应的文件为member类下的index方法
Route::get(':version/member/index','api/:version.member/index');
//
////资源路由，详情查看tp手册资源路由
//Route::resource(':version/member','api/:version.member');
//
////生成access_token，post访问Token类下的token方法
Route::post(':version/token','api/:version.token/build');
Route::post(':version/token/refresh','api/:version.token/refresh');

