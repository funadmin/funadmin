<?php

declare(strict_types=1);

// 由迁移前的插件路由权限要求冻结生成：旧插件权限码 → 按方法推导的新权限码。迁移已固化，不要手改。
return array (
  'cms:category:list' => 
  array (
    0 => 'cms:categories:index',
    1 => 'cms:sites:context',
  ),
  'cms:category:save' => 
  array (
    0 => 'cms:categories:save',
    1 => 'cms:categories:status',
  ),
  'cms:content:list' => 
  array (
    0 => 'cms:content:detail',
    1 => 'cms:content:index',
    2 => 'cms:content:lookups',
    3 => 'cms:content:revisions',
    4 => 'cms:content:translation',
    5 => 'cms:sites:context',
  ),
  'cms:content:offline' => 
  array (
    0 => 'cms:content:offline',
  ),
  'cms:content:preview' => 
  array (
    0 => 'cms:content:preview',
  ),
  'cms:content:publish' => 
  array (
    0 => 'cms:content:publish',
  ),
  'cms:content:save' => 
  array (
    0 => 'cms:content:attachments',
    1 => 'cms:content:save',
    2 => 'cms:content:upload',
  ),
  'cms:menu:delete' => 
  array (
    0 => 'cms:menus:remove',
  ),
  'cms:menu:list' => 
  array (
    0 => 'cms:menus:index',
    1 => 'cms:menus:targets',
    2 => 'cms:sites:context',
  ),
  'cms:menu:save' => 
  array (
    0 => 'cms:menus:save',
    1 => 'cms:menus:status',
  ),
  'cms:model:list' => 
  array (
    0 => 'cms:models:bindings',
    1 => 'cms:models:index',
    2 => 'cms:sites:context',
  ),
  'cms:model:save' => 
  array (
    0 => 'cms:models:save',
  ),
  'cms:site:detail' => 
  array (
    0 => 'cms:contributions:settings',
  ),
  'cms:site:edit' => 
  array (
    0 => 'cms:contributions:save-settings',
  ),
  'cms:site:grant' => 
  array (
    0 => 'cms:access:assign',
    1 => 'cms:access:candidates',
    2 => 'cms:access:index',
    3 => 'cms:access:remove',
    4 => 'cms:access:save-role',
    5 => 'cms:sites:context',
    6 => 'cms:sites:grant',
  ),
  'cms:site:list' => 
  array (
    0 => 'cms:sites:configuration',
    1 => 'cms:sites:context',
    2 => 'cms:sites:index',
    3 => 'cms:sites:settings',
  ),
  'cms:site:save' => 
  array (
    0 => 'cms:sites:configuration-save',
    1 => 'cms:sites:domain-save',
    2 => 'cms:sites:locale-save',
    3 => 'cms:sites:model-save',
    4 => 'cms:sites:options',
    5 => 'cms:sites:save',
  ),
  'cms:submission:list' => 
  array (
    0 => 'cms:contributions:index',
    1 => 'cms:sites:context',
  ),
  'cms:submission:review' => 
  array (
    0 => 'cms:contributions:review',
  ),
  'cms:tag:list' => 
  array (
    0 => 'cms:sites:context',
    1 => 'cms:tags:index',
    2 => 'cms:tags:options',
    3 => 'cms:tags:providers',
  ),
  'cms:tag:preview' => 
  array (
    0 => 'cms:tags:preview',
  ),
  'cms:tag:publish' => 
  array (
    0 => 'cms:tags:publish',
  ),
  'cms:tag:save' => 
  array (
    0 => 'cms:tags:save',
  ),
  'example:entry:index' => 
  array (
    0 => 'example:index:index',
  ),
  'example:test:batch-delete' => 
  array (
    0 => 'example:test:recycle',
  ),
  'example:test:batch-destroy' => 
  array (
    0 => 'example:test:destroy-many',
  ),
  'example:test:batch-restore' => 
  array (
    0 => 'example:test:restore-many',
  ),
  'example:test:create' => 
  array (
    0 => 'example:test:create',
  ),
  'example:test:delete' => 
  array (
    0 => 'example:test:remove',
  ),
  'example:test:destroy' => 
  array (
    0 => 'example:test:destroy',
  ),
  'example:test:detail' => 
  array (
    0 => 'example:test:detail',
  ),
  'example:test:export' => 
  array (
    0 => 'example:test:export',
  ),
  'example:test:import' => 
  array (
    0 => 'example:test:import',
  ),
  'example:test:list' => 
  array (
    0 => 'example:test:index',
  ),
  'example:test:restore' => 
  array (
    0 => 'example:test:restore',
  ),
  'example:test:update' => 
  array (
    0 => 'example:test:update',
  ),
  'market:category:manage' => 
  array (
    0 => 'market:category:create',
    1 => 'market:category:delete',
    2 => 'market:category:update',
  ),
  'market:grant:manage' => 
  array (
    0 => 'market:grant:create',
    1 => 'market:grant:delete',
    2 => 'market:grant:index',
    3 => 'market:grant:update',
  ),
  'market:order:manage' => 
  array (
    0 => 'market:order:close',
    1 => 'market:order:confirm',
  ),
  'market:order:view' => 
  array (
    0 => 'market:order:detail',
    1 => 'market:order:index',
  ),
  'market:plugin:manage' => 
  array (
    0 => 'market:plugin:delete',
    1 => 'market:plugin:delete-version',
    2 => 'market:plugin:package',
    3 => 'market:plugin:publish',
    4 => 'market:plugin:republish',
    5 => 'market:plugin:update',
    6 => 'market:plugin:update-version',
    7 => 'market:plugin:upload',
    8 => 'market:plugin:withdraw',
  ),
  'market:plugin:view' => 
  array (
    0 => 'market:category:index',
    1 => 'market:plugin:index',
    2 => 'market:plugin:overview',
    3 => 'market:plugin:versions',
  ),
  'market:setting:manage' => 
  array (
    0 => 'market:setting:generate-key',
    1 => 'market:setting:index',
    2 => 'market:setting:payment',
    3 => 'market:setting:save-payment',
  ),
  'market:stat:view' => 
  array (
    0 => 'market:stat:index',
  ),
  'qa:moderation:list' => 
  array (
    0 => 'qa:moderation:index',
  ),
  'qa:moderation:review' => 
  array (
    0 => 'qa:moderation:review',
  ),
);
