<?php

return [
    \app\common\middleware\PluginApplicationGuard::class,
    // 全局请求缓存 验证码会报错不显示，千万不要释放注释
    // \think\middleware\CheckRequestCache::class,
    // Session初始化 //
    \think\middleware\SessionInit::class,
    // 多语言加载（cookie think_lang / lang 参数，缺省落默认中文；app 子类放开 think_lang 的 HttpOnly 供前端同步） //
    \app\middleware\LoadLangPack::class,
    \think\middleware\AllowCrossDomain::class,
    \app\common\middleware\Install::class,
];
