<?php

declare(strict_types=1);

// 冻结：插件菜单与前端路由原先绑定的旧权限码 → 改绑的按方法推导码（该页面列表接口的权限）。
// 每个新码只由对应旧码展开得到，因此菜单可见性与升级前一致。迁移已固化，不要手改。
return [
    'cms:site:list' => 'cms:sites:index',
    'cms:category:list' => 'cms:categories:index',
    'cms:content:list' => 'cms:content:index',
    'cms:submission:list' => 'cms:contributions:index',
    'cms:menu:list' => 'cms:menus:index',
    'cms:tag:list' => 'cms:tags:index',
    'cms:model:list' => 'cms:models:index',
    'cms:site:grant' => 'cms:access:index',
    'qa:moderation:list' => 'qa:moderation:index',
];
