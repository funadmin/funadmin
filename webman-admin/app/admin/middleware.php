<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新webman实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */


return [
     // app 子类通过 locale Cookie 同步当前语言
     \app\middleware\LoadLangPack::class,
     // Session 由 Webman 原生会话能力按需管理，不加载 ThinkPHP SessionInit。
        //全局请求缓存
//     \think\middleware\CheckRequestCache::class,

];

