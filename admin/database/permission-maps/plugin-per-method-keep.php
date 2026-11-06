<?php

declare(strict_types=1);

// 冻结：迁移后仍保留的旧插件权限码。CMS 站点操作既是站点角色可分配的能力权限，也被菜单与权限发现引用，
// 因此展开授权后不停用、不撤销。迁移已固化，不要手改。
return [
    'cms:site:detail',
    'cms:site:edit',
    'cms:site:domain',
    'cms:site:locale',
    'cms:category:list',
    'cms:category:create',
    'cms:category:edit',
    'cms:category:status',
    'cms:content:list',
    'cms:content:create',
    'cms:content:edit',
    'cms:content:preview',
    'cms:content:publish',
    'cms:content:offline',
    'cms:menu:list',
    'cms:menu:create',
    'cms:menu:edit',
    'cms:menu:status',
    'cms:menu:delete',
    'cms:tag:list',
    'cms:tag:create',
    'cms:tag:edit',
    'cms:tag:preview',
    'cms:tag:publish',
    'cms:model:list',
    'cms:model:status',
    'cms:model:template',
    'cms:submission:list',
    'cms:submission:review',
    'cms:access:list',
    'cms:access:members',
    'cms:access:roles',
];
