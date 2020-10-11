<?php

return [
    // 全局请求缓存 验证码会报错不显示，千万不要释放注释
    // \think\middleware\CheckRequestCache::class,
    // Session初始化 //
     \think\middleware\SessionInit::class,


];
