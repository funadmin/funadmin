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
 * Date: 2017/8/2
 */


return [
     // app 子类放开 think_lang 的 HttpOnly 供前端同步语言
     \app\middleware\LoadLangPack::class,

     \think\middleware\SessionInit::class,
        //全局请求缓存
//     \think\middleware\CheckRequestCache::class,

];

