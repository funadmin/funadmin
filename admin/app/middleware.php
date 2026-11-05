<?php

return [
    \app\common\middleware\PluginApplicationGuard::class,
    // Session 由 Webman 原生会话能力按需管理。
    \app\middleware\LoadLangPack::class,
    // 跨域由 webman/cors 插件提供。
    \app\common\middleware\Install::class,
];
