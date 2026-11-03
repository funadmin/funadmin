<?php

return [
    \app\common\middleware\PluginApplicationGuard::class,
    // 全局请求缓存 验证码会报错不显示，千万不要释放注释
    // \think\middleware\CheckRequestCache::class,
    // Session 由 Webman 原生会话能力按需管理，不加载 ThinkPHP SessionInit。
    // 多语言加载（Cookie locale / locale 参数，缺省落默认中文） //
    \app\middleware\LoadLangPack::class,
    // 跨域由 webman/cors 插件提供，避免加载已移除的 ThinkPHP 中间件。
    \app\common\middleware\Install::class,
];
