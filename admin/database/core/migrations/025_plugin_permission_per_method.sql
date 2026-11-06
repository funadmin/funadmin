-- 插件后台权限按方法细分（与 PHP 迁移 034_plugin_permission_per_method.php 等价）。
-- 1) 登记每个插件后台方法推导出的权限码；2) 把角色持有的旧粗粒度权限展开为它覆盖的新权限；
-- 3) 撤销不再使用的旧授权并停用旧权限行（CMS 站点操作作为能力权限保留）；4) 插件菜单改绑到列表接口权限。
-- 升级前后每个角色能访问的插件接口集合不变，可重复执行。

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`sort_order`)
SELECT 0,'admin',d.code,d.obj,d.act,d.name,'route',1,0,'plugin',d.plugin,999 FROM (
  SELECT 'cms:access:assign','cms/access','assign','cms:access:assign','cms'
  UNION ALL SELECT 'cms:access:candidates','cms/access','candidates','cms:access:candidates','cms'
  UNION ALL SELECT 'cms:access:index','cms/access','index','cms:access:index','cms'
  UNION ALL SELECT 'cms:access:remove','cms/access','remove','cms:access:remove','cms'
  UNION ALL SELECT 'cms:access:save-role','cms/access','save-role','cms:access:save-role','cms'
  UNION ALL SELECT 'cms:categories:index','cms/categories','index','cms:categories:index','cms'
  UNION ALL SELECT 'cms:categories:save','cms/categories','save','cms:categories:save','cms'
  UNION ALL SELECT 'cms:categories:status','cms/categories','status','cms:categories:status','cms'
  UNION ALL SELECT 'cms:content:attachments','cms/content','attachments','cms:content:attachments','cms'
  UNION ALL SELECT 'cms:content:detail','cms/content','detail','cms:content:detail','cms'
  UNION ALL SELECT 'cms:content:index','cms/content','index','cms:content:index','cms'
  UNION ALL SELECT 'cms:content:lookups','cms/content','lookups','cms:content:lookups','cms'
  UNION ALL SELECT 'cms:content:offline','cms/content','offline','cms:content:offline','cms'
  UNION ALL SELECT 'cms:content:preview','cms/content','preview','cms:content:preview','cms'
  UNION ALL SELECT 'cms:content:publish','cms/content','publish','cms:content:publish','cms'
  UNION ALL SELECT 'cms:content:revisions','cms/content','revisions','cms:content:revisions','cms'
  UNION ALL SELECT 'cms:content:save','cms/content','save','cms:content:save','cms'
  UNION ALL SELECT 'cms:content:translation','cms/content','translation','cms:content:translation','cms'
  UNION ALL SELECT 'cms:content:upload','cms/content','upload','cms:content:upload','cms'
  UNION ALL SELECT 'cms:contributions:index','cms/contributions','index','cms:contributions:index','cms'
  UNION ALL SELECT 'cms:contributions:review','cms/contributions','review','cms:contributions:review','cms'
  UNION ALL SELECT 'cms:contributions:save-settings','cms/contributions','save-settings','cms:contributions:save-settings','cms'
  UNION ALL SELECT 'cms:contributions:settings','cms/contributions','settings','cms:contributions:settings','cms'
  UNION ALL SELECT 'cms:menus:index','cms/menus','index','cms:menus:index','cms'
  UNION ALL SELECT 'cms:menus:remove','cms/menus','remove','cms:menus:remove','cms'
  UNION ALL SELECT 'cms:menus:save','cms/menus','save','cms:menus:save','cms'
  UNION ALL SELECT 'cms:menus:status','cms/menus','status','cms:menus:status','cms'
  UNION ALL SELECT 'cms:menus:targets','cms/menus','targets','cms:menus:targets','cms'
  UNION ALL SELECT 'cms:models:bindings','cms/models','bindings','cms:models:bindings','cms'
  UNION ALL SELECT 'cms:models:index','cms/models','index','cms:models:index','cms'
  UNION ALL SELECT 'cms:models:save','cms/models','save','cms:models:save','cms'
  UNION ALL SELECT 'cms:sites:configuration','cms/sites','configuration','cms:sites:configuration','cms'
  UNION ALL SELECT 'cms:sites:configuration-save','cms/sites','configuration-save','cms:sites:configuration-save','cms'
  UNION ALL SELECT 'cms:sites:context','cms/sites','context','cms:sites:context','cms'
  UNION ALL SELECT 'cms:sites:domain-save','cms/sites','domain-save','cms:sites:domain-save','cms'
  UNION ALL SELECT 'cms:sites:grant','cms/sites','grant','cms:sites:grant','cms'
  UNION ALL SELECT 'cms:sites:index','cms/sites','index','cms:sites:index','cms'
  UNION ALL SELECT 'cms:sites:locale-save','cms/sites','locale-save','cms:sites:locale-save','cms'
  UNION ALL SELECT 'cms:sites:model-save','cms/sites','model-save','cms:sites:model-save','cms'
  UNION ALL SELECT 'cms:sites:options','cms/sites','options','cms:sites:options','cms'
  UNION ALL SELECT 'cms:sites:save','cms/sites','save','cms:sites:save','cms'
  UNION ALL SELECT 'cms:sites:settings','cms/sites','settings','cms:sites:settings','cms'
  UNION ALL SELECT 'cms:tags:index','cms/tags','index','cms:tags:index','cms'
  UNION ALL SELECT 'cms:tags:options','cms/tags','options','cms:tags:options','cms'
  UNION ALL SELECT 'cms:tags:preview','cms/tags','preview','cms:tags:preview','cms'
  UNION ALL SELECT 'cms:tags:providers','cms/tags','providers','cms:tags:providers','cms'
  UNION ALL SELECT 'cms:tags:publish','cms/tags','publish','cms:tags:publish','cms'
  UNION ALL SELECT 'cms:tags:save','cms/tags','save','cms:tags:save','cms'
  UNION ALL SELECT 'example:index:index','example/index','index','返回示例插件后台入口响应','example'
  UNION ALL SELECT 'example:test:create','example/test','create','创建示例插件测试记录，校验提交字段后保存','example'
  UNION ALL SELECT 'example:test:destroy','example/test','destroy','永久删除示例插件测试记录，删除后不可通过回收站恢复','example'
  UNION ALL SELECT 'example:test:destroy-many','example/test','destroy-many','批量永久删除示例插件测试记录，删除后不可通过回收站恢复','example'
  UNION ALL SELECT 'example:test:detail','example/test','detail','获取指定示例插件测试记录的详情','example'
  UNION ALL SELECT 'example:test:export','example/test','export','按筛选条件导出示例插件测试记录数据','example'
  UNION ALL SELECT 'example:test:import','example/test','import','批量导入示例插件测试记录，返回导入结果','example'
  UNION ALL SELECT 'example:test:index','example/test','index','获取示例插件测试记录列表','example'
  UNION ALL SELECT 'example:test:recycle','example/test','recycle','将选中的示例插件测试记录移入回收站','example'
  UNION ALL SELECT 'example:test:remove','example/test','remove','删除指定示例插件测试记录','example'
  UNION ALL SELECT 'example:test:restore','example/test','restore','恢复回收站中的示例插件测试记录','example'
  UNION ALL SELECT 'example:test:restore-many','example/test','restore-many','批量恢复回收站中的示例插件测试记录','example'
  UNION ALL SELECT 'example:test:update','example/test','update','更新指定示例插件测试记录','example'
  UNION ALL SELECT 'market:category:create','market/category','create','创建插件市场分类，校验提交字段后保存','market'
  UNION ALL SELECT 'market:category:delete','market/category','delete','删除指定插件市场分类','market'
  UNION ALL SELECT 'market:category:index','market/category','index','获取插件市场分类列表','market'
  UNION ALL SELECT 'market:category:update','market/category','update','更新指定插件市场分类','market'
  UNION ALL SELECT 'market:grant:create','market/grant','create','创建插件市场授权，校验提交字段后保存','market'
  UNION ALL SELECT 'market:grant:delete','market/grant','delete','删除指定插件市场授权','market'
  UNION ALL SELECT 'market:grant:index','market/grant','index','获取插件市场授权列表','market'
  UNION ALL SELECT 'market:grant:update','market/grant','update','更新指定插件市场授权','market'
  UNION ALL SELECT 'market:order:close','market/order','close','关闭指定市场订单','market'
  UNION ALL SELECT 'market:order:confirm','market/order','confirm','确认订单收款并开通相应插件授权','market'
  UNION ALL SELECT 'market:order:detail','market/order','detail','按订单号获取市场订单详情','market'
  UNION ALL SELECT 'market:order:index','market/order','index','按订单状态、关键字和插件分页查询市场订单','market'
  UNION ALL SELECT 'market:plugin:delete','market/plugin','delete','删除指定市场插件','market'
  UNION ALL SELECT 'market:plugin:delete-version','market/plugin','delete-version','删除指定市场插件版本','market'
  UNION ALL SELECT 'market:plugin:index','market/plugin','index','按关键字分页查询市场插件','market'
  UNION ALL SELECT 'market:plugin:overview','market/plugin','overview','获取插件市场运营概览','market'
  UNION ALL SELECT 'market:plugin:package','market/plugin','package','下载指定市场插件版本的安装包','market'
  UNION ALL SELECT 'market:plugin:publish','market/plugin','publish','发布指定市场插件版本','market'
  UNION ALL SELECT 'market:plugin:republish','market/plugin','republish','重新发布已撤回的市场插件版本','market'
  UNION ALL SELECT 'market:plugin:update','market/plugin','update','更新指定市场插件','market'
  UNION ALL SELECT 'market:plugin:update-version','market/plugin','update-version','更新指定市场插件版本的信息','market'
  UNION ALL SELECT 'market:plugin:upload','market/plugin','upload','校验并签名上传的插件 ZIP 包，创建草稿版本','market'
  UNION ALL SELECT 'market:plugin:versions','market/plugin','versions','获取市场插件的版本列表','market'
  UNION ALL SELECT 'market:plugin:withdraw','market/plugin','withdraw','撤回指定市场插件版本','market'
  UNION ALL SELECT 'market:setting:generate-key','market/setting','generate-key','生成插件市场签名密钥','market'
  UNION ALL SELECT 'market:setting:index','market/setting','index','获取插件市场签名配置状态','market'
  UNION ALL SELECT 'market:setting:payment','market/setting','payment','读取插件市场支付配置','market'
  UNION ALL SELECT 'market:setting:save-payment','market/setting','save-payment','保存插件市场支付配置','market'
  UNION ALL SELECT 'market:stat:index','market/stat','index','按 days 指定的时间范围获取插件市场统计','market'
  UNION ALL SELECT 'qa:moderation:index','qa/moderation','index','qa:moderation:index','qa'
  UNION ALL SELECT 'qa:moderation:review','qa/moderation','review','qa:moderation:review','qa'
) AS d (`code`,`obj`,`act`,`name`,`plugin`)
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` e WHERE e.`code` = d.`code`);

UPDATE `fun_permission` SET `status` = 1, `resource_type` = 'route' WHERE `source_type` = 'plugin' AND `code` IN ('cms:access:assign','cms:access:candidates','cms:access:index','cms:access:remove','cms:access:save-role','cms:categories:index','cms:categories:save','cms:categories:status','cms:content:attachments','cms:content:detail','cms:content:index','cms:content:lookups','cms:content:offline','cms:content:preview','cms:content:publish','cms:content:revisions','cms:content:save','cms:content:translation','cms:content:upload','cms:contributions:index','cms:contributions:review','cms:contributions:save-settings','cms:contributions:settings','cms:menus:index','cms:menus:remove','cms:menus:save','cms:menus:status','cms:menus:targets','cms:models:bindings','cms:models:index','cms:models:save','cms:sites:configuration','cms:sites:configuration-save','cms:sites:context','cms:sites:domain-save','cms:sites:grant','cms:sites:index','cms:sites:locale-save','cms:sites:model-save','cms:sites:options','cms:sites:save','cms:sites:settings','cms:tags:index','cms:tags:options','cms:tags:preview','cms:tags:providers','cms:tags:publish','cms:tags:save','example:index:index','example:test:create','example:test:destroy','example:test:destroy-many','example:test:detail','example:test:export','example:test:import','example:test:index','example:test:recycle','example:test:remove','example:test:restore','example:test:restore-many','example:test:update','market:category:create','market:category:delete','market:category:index','market:category:update','market:grant:create','market:grant:delete','market:grant:index','market:grant:update','market:order:close','market:order:confirm','market:order:detail','market:order:index','market:plugin:delete','market:plugin:delete-version','market:plugin:index','market:plugin:overview','market:plugin:package','market:plugin:publish','market:plugin:republish','market:plugin:update','market:plugin:update-version','market:plugin:upload','market:plugin:versions','market:plugin:withdraw','market:setting:generate-key','market:setting:index','market:setting:payment','market:setting:save-payment','market:stat:index','qa:moderation:index','qa:moderation:review');

-- 未命名（名称仍是权限码）的推导码补上默认名称（方法 OpenAPI summary）。
UPDATE `fun_permission` SET `name` = '返回示例插件后台入口响应' WHERE `code` = 'example:index:index' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '创建示例插件测试记录，校验提交字段后保存' WHERE `code` = 'example:test:create' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '永久删除示例插件测试记录，删除后不可通过回收站恢复' WHERE `code` = 'example:test:destroy' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '批量永久删除示例插件测试记录，删除后不可通过回收站恢复' WHERE `code` = 'example:test:destroy-many' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '获取指定示例插件测试记录的详情' WHERE `code` = 'example:test:detail' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '按筛选条件导出示例插件测试记录数据' WHERE `code` = 'example:test:export' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '批量导入示例插件测试记录，返回导入结果' WHERE `code` = 'example:test:import' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '获取示例插件测试记录列表' WHERE `code` = 'example:test:index' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '将选中的示例插件测试记录移入回收站' WHERE `code` = 'example:test:recycle' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '删除指定示例插件测试记录' WHERE `code` = 'example:test:remove' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '恢复回收站中的示例插件测试记录' WHERE `code` = 'example:test:restore' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '批量恢复回收站中的示例插件测试记录' WHERE `code` = 'example:test:restore-many' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '更新指定示例插件测试记录' WHERE `code` = 'example:test:update' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '创建插件市场分类，校验提交字段后保存' WHERE `code` = 'market:category:create' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '删除指定插件市场分类' WHERE `code` = 'market:category:delete' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '获取插件市场分类列表' WHERE `code` = 'market:category:index' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '更新指定插件市场分类' WHERE `code` = 'market:category:update' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '创建插件市场授权，校验提交字段后保存' WHERE `code` = 'market:grant:create' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '删除指定插件市场授权' WHERE `code` = 'market:grant:delete' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '获取插件市场授权列表' WHERE `code` = 'market:grant:index' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '更新指定插件市场授权' WHERE `code` = 'market:grant:update' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '关闭指定市场订单' WHERE `code` = 'market:order:close' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '确认订单收款并开通相应插件授权' WHERE `code` = 'market:order:confirm' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '按订单号获取市场订单详情' WHERE `code` = 'market:order:detail' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '按订单状态、关键字和插件分页查询市场订单' WHERE `code` = 'market:order:index' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '删除指定市场插件' WHERE `code` = 'market:plugin:delete' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '删除指定市场插件版本' WHERE `code` = 'market:plugin:delete-version' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '按关键字分页查询市场插件' WHERE `code` = 'market:plugin:index' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '获取插件市场运营概览' WHERE `code` = 'market:plugin:overview' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '下载指定市场插件版本的安装包' WHERE `code` = 'market:plugin:package' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '发布指定市场插件版本' WHERE `code` = 'market:plugin:publish' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '重新发布已撤回的市场插件版本' WHERE `code` = 'market:plugin:republish' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '更新指定市场插件' WHERE `code` = 'market:plugin:update' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '更新指定市场插件版本的信息' WHERE `code` = 'market:plugin:update-version' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '校验并签名上传的插件 ZIP 包，创建草稿版本' WHERE `code` = 'market:plugin:upload' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '获取市场插件的版本列表' WHERE `code` = 'market:plugin:versions' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '撤回指定市场插件版本' WHERE `code` = 'market:plugin:withdraw' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '生成插件市场签名密钥' WHERE `code` = 'market:setting:generate-key' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '获取插件市场签名配置状态' WHERE `code` = 'market:setting:index' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '读取插件市场支付配置' WHERE `code` = 'market:setting:payment' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '保存插件市场支付配置' WHERE `code` = 'market:setting:save-payment' AND `name` = `code`;
UPDATE `fun_permission` SET `name` = '按 days 指定的时间范围获取插件市场统计' WHERE `code` = 'market:stat:index' AND `name` = `code`;

-- 展开授权：持有旧权限的每个 (角色, 域) 获得映射到的全部新权限。
INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT 'p', r.`v0`, r.`v1`, m.`obj`, m.`act`, '', '', SHA2(CONCAT_WS(CHAR(31), 'p', r.`v0`, r.`v1`, m.`obj`, m.`act`), 256)
FROM (
  SELECT 'cms:category:list','cms/categories','index'
  UNION ALL SELECT 'cms:category:list','cms/sites','context'
  UNION ALL SELECT 'cms:category:save','cms/categories','save'
  UNION ALL SELECT 'cms:category:save','cms/categories','status'
  UNION ALL SELECT 'cms:content:list','cms/content','detail'
  UNION ALL SELECT 'cms:content:list','cms/content','index'
  UNION ALL SELECT 'cms:content:list','cms/content','lookups'
  UNION ALL SELECT 'cms:content:list','cms/content','revisions'
  UNION ALL SELECT 'cms:content:list','cms/content','translation'
  UNION ALL SELECT 'cms:content:list','cms/sites','context'
  UNION ALL SELECT 'cms:content:offline','cms/content','offline'
  UNION ALL SELECT 'cms:content:preview','cms/content','preview'
  UNION ALL SELECT 'cms:content:publish','cms/content','publish'
  UNION ALL SELECT 'cms:content:save','cms/content','attachments'
  UNION ALL SELECT 'cms:content:save','cms/content','save'
  UNION ALL SELECT 'cms:content:save','cms/content','upload'
  UNION ALL SELECT 'cms:menu:delete','cms/menus','remove'
  UNION ALL SELECT 'cms:menu:list','cms/menus','index'
  UNION ALL SELECT 'cms:menu:list','cms/menus','targets'
  UNION ALL SELECT 'cms:menu:list','cms/sites','context'
  UNION ALL SELECT 'cms:menu:save','cms/menus','save'
  UNION ALL SELECT 'cms:menu:save','cms/menus','status'
  UNION ALL SELECT 'cms:model:list','cms/models','bindings'
  UNION ALL SELECT 'cms:model:list','cms/models','index'
  UNION ALL SELECT 'cms:model:list','cms/sites','context'
  UNION ALL SELECT 'cms:model:save','cms/models','save'
  UNION ALL SELECT 'cms:site:detail','cms/contributions','settings'
  UNION ALL SELECT 'cms:site:edit','cms/contributions','save-settings'
  UNION ALL SELECT 'cms:site:grant','cms/access','assign'
  UNION ALL SELECT 'cms:site:grant','cms/access','candidates'
  UNION ALL SELECT 'cms:site:grant','cms/access','index'
  UNION ALL SELECT 'cms:site:grant','cms/access','remove'
  UNION ALL SELECT 'cms:site:grant','cms/access','save-role'
  UNION ALL SELECT 'cms:site:grant','cms/sites','context'
  UNION ALL SELECT 'cms:site:grant','cms/sites','grant'
  UNION ALL SELECT 'cms:site:list','cms/sites','configuration'
  UNION ALL SELECT 'cms:site:list','cms/sites','context'
  UNION ALL SELECT 'cms:site:list','cms/sites','index'
  UNION ALL SELECT 'cms:site:list','cms/sites','settings'
  UNION ALL SELECT 'cms:site:save','cms/sites','configuration-save'
  UNION ALL SELECT 'cms:site:save','cms/sites','domain-save'
  UNION ALL SELECT 'cms:site:save','cms/sites','locale-save'
  UNION ALL SELECT 'cms:site:save','cms/sites','model-save'
  UNION ALL SELECT 'cms:site:save','cms/sites','options'
  UNION ALL SELECT 'cms:site:save','cms/sites','save'
  UNION ALL SELECT 'cms:submission:list','cms/contributions','index'
  UNION ALL SELECT 'cms:submission:list','cms/sites','context'
  UNION ALL SELECT 'cms:submission:review','cms/contributions','review'
  UNION ALL SELECT 'cms:tag:list','cms/sites','context'
  UNION ALL SELECT 'cms:tag:list','cms/tags','index'
  UNION ALL SELECT 'cms:tag:list','cms/tags','options'
  UNION ALL SELECT 'cms:tag:list','cms/tags','providers'
  UNION ALL SELECT 'cms:tag:preview','cms/tags','preview'
  UNION ALL SELECT 'cms:tag:publish','cms/tags','publish'
  UNION ALL SELECT 'cms:tag:save','cms/tags','save'
  UNION ALL SELECT 'example:entry:index','example/index','index'
  UNION ALL SELECT 'example:test:batch-delete','example/test','recycle'
  UNION ALL SELECT 'example:test:batch-destroy','example/test','destroy-many'
  UNION ALL SELECT 'example:test:batch-restore','example/test','restore-many'
  UNION ALL SELECT 'example:test:create','example/test','create'
  UNION ALL SELECT 'example:test:delete','example/test','remove'
  UNION ALL SELECT 'example:test:destroy','example/test','destroy'
  UNION ALL SELECT 'example:test:detail','example/test','detail'
  UNION ALL SELECT 'example:test:export','example/test','export'
  UNION ALL SELECT 'example:test:import','example/test','import'
  UNION ALL SELECT 'example:test:list','example/test','index'
  UNION ALL SELECT 'example:test:restore','example/test','restore'
  UNION ALL SELECT 'example:test:update','example/test','update'
  UNION ALL SELECT 'market:category:manage','market/category','create'
  UNION ALL SELECT 'market:category:manage','market/category','delete'
  UNION ALL SELECT 'market:category:manage','market/category','update'
  UNION ALL SELECT 'market:grant:manage','market/grant','create'
  UNION ALL SELECT 'market:grant:manage','market/grant','delete'
  UNION ALL SELECT 'market:grant:manage','market/grant','index'
  UNION ALL SELECT 'market:grant:manage','market/grant','update'
  UNION ALL SELECT 'market:order:manage','market/order','close'
  UNION ALL SELECT 'market:order:manage','market/order','confirm'
  UNION ALL SELECT 'market:order:view','market/order','detail'
  UNION ALL SELECT 'market:order:view','market/order','index'
  UNION ALL SELECT 'market:plugin:manage','market/plugin','delete'
  UNION ALL SELECT 'market:plugin:manage','market/plugin','delete-version'
  UNION ALL SELECT 'market:plugin:manage','market/plugin','package'
  UNION ALL SELECT 'market:plugin:manage','market/plugin','publish'
  UNION ALL SELECT 'market:plugin:manage','market/plugin','republish'
  UNION ALL SELECT 'market:plugin:manage','market/plugin','update'
  UNION ALL SELECT 'market:plugin:manage','market/plugin','update-version'
  UNION ALL SELECT 'market:plugin:manage','market/plugin','upload'
  UNION ALL SELECT 'market:plugin:manage','market/plugin','withdraw'
  UNION ALL SELECT 'market:plugin:view','market/category','index'
  UNION ALL SELECT 'market:plugin:view','market/plugin','index'
  UNION ALL SELECT 'market:plugin:view','market/plugin','overview'
  UNION ALL SELECT 'market:plugin:view','market/plugin','versions'
  UNION ALL SELECT 'market:setting:manage','market/setting','generate-key'
  UNION ALL SELECT 'market:setting:manage','market/setting','index'
  UNION ALL SELECT 'market:setting:manage','market/setting','payment'
  UNION ALL SELECT 'market:setting:manage','market/setting','save-payment'
  UNION ALL SELECT 'market:stat:view','market/stat','index'
  UNION ALL SELECT 'qa:moderation:list','qa/moderation','index'
  UNION ALL SELECT 'qa:moderation:review','qa/moderation','review'
) AS m (`old_code`,`obj`,`act`)
JOIN `fun_permission` o ON o.`code` = m.`old_code`
JOIN `fun_casbin_rule` r ON r.`ptype` = 'p' AND r.`v2` = o.`obj` AND r.`v3` = o.`act`;

-- 撤销不再使用的旧授权（仍被新权限复用的 obj/act 保留），并停用旧权限行。
DELETE r FROM `fun_casbin_rule` r JOIN `fun_permission` o ON r.`ptype` = 'p' AND r.`v2` = o.`obj` AND r.`v3` = o.`act`
WHERE o.`code` IN ('cms:category:save','cms:menu:save','cms:model:save','cms:site:grant','cms:site:list','cms:site:save','cms:tag:save','example:entry:index','example:test:batch-delete','example:test:batch-destroy','example:test:batch-restore','example:test:delete','example:test:list','market:category:manage','market:grant:manage','market:order:manage','market:order:view','market:plugin:manage','market:plugin:view','market:setting:manage','market:stat:view','qa:moderation:list')
  AND (o.`obj`, o.`act`) NOT IN (('cms/categories','index'),('cms/sites','context'),('cms/categories','save'),('cms/categories','status'),('cms/content','detail'),('cms/content','index'),('cms/content','lookups'),('cms/content','revisions'),('cms/content','translation'),('cms/content','offline'),('cms/content','preview'),('cms/content','publish'),('cms/content','attachments'),('cms/content','save'),('cms/content','upload'),('cms/menus','remove'),('cms/menus','index'),('cms/menus','targets'),('cms/menus','save'),('cms/menus','status'),('cms/models','bindings'),('cms/models','index'),('cms/models','save'),('cms/contributions','settings'),('cms/contributions','save-settings'),('cms/access','assign'),('cms/access','candidates'),('cms/access','index'),('cms/access','remove'),('cms/access','save-role'),('cms/sites','grant'),('cms/sites','configuration'),('cms/sites','index'),('cms/sites','settings'),('cms/sites','configuration-save'),('cms/sites','domain-save'),('cms/sites','locale-save'),('cms/sites','model-save'),('cms/sites','options'),('cms/sites','save'),('cms/contributions','index'),('cms/contributions','review'),('cms/tags','index'),('cms/tags','options'),('cms/tags','providers'),('cms/tags','preview'),('cms/tags','publish'),('cms/tags','save'),('example/index','index'),('example/test','recycle'),('example/test','destroy-many'),('example/test','restore-many'),('example/test','create'),('example/test','remove'),('example/test','destroy'),('example/test','detail'),('example/test','export'),('example/test','import'),('example/test','index'),('example/test','restore'),('example/test','update'),('market/category','create'),('market/category','delete'),('market/category','update'),('market/grant','create'),('market/grant','delete'),('market/grant','index'),('market/grant','update'),('market/order','close'),('market/order','confirm'),('market/order','detail'),('market/order','index'),('market/plugin','delete'),('market/plugin','delete-version'),('market/plugin','package'),('market/plugin','publish'),('market/plugin','republish'),('market/plugin','update'),('market/plugin','update-version'),('market/plugin','upload'),('market/plugin','withdraw'),('market/category','index'),('market/plugin','index'),('market/plugin','overview'),('market/plugin','versions'),('market/setting','generate-key'),('market/setting','index'),('market/setting','payment'),('market/setting','save-payment'),('market/stat','index'),('qa/moderation','index'),('qa/moderation','review'));
UPDATE `fun_permission` SET `status` = 0 WHERE `code` IN ('cms:category:save','cms:menu:save','cms:model:save','cms:site:grant','cms:site:list','cms:site:save','cms:tag:save','example:entry:index','example:test:batch-delete','example:test:batch-destroy','example:test:batch-restore','example:test:delete','example:test:list','market:category:manage','market:grant:manage','market:order:manage','market:order:view','market:plugin:manage','market:plugin:view','market:setting:manage','market:stat:view','qa:moderation:list');

-- 插件菜单改绑：绑定旧权限码的菜单改为绑定对应列表接口的推导码，并同步 query 中的 permission 参数。
UPDATE `fun_admin_menu` m JOIN `fun_permission` o ON o.`id` = m.`permission_id` AND o.`code` = 'cms:site:list' JOIN `fun_permission` n ON n.`code` = 'cms:sites:index' SET m.`permission_id` = n.`id`, m.`query` = REPLACE(m.`query`, 'permission=cms%3Asite%3Alist', 'permission=cms%3Asites%3Aindex');
UPDATE `fun_admin_menu` m JOIN `fun_permission` o ON o.`id` = m.`permission_id` AND o.`code` = 'cms:category:list' JOIN `fun_permission` n ON n.`code` = 'cms:categories:index' SET m.`permission_id` = n.`id`, m.`query` = REPLACE(m.`query`, 'permission=cms%3Acategory%3Alist', 'permission=cms%3Acategories%3Aindex');
UPDATE `fun_admin_menu` m JOIN `fun_permission` o ON o.`id` = m.`permission_id` AND o.`code` = 'cms:content:list' JOIN `fun_permission` n ON n.`code` = 'cms:content:index' SET m.`permission_id` = n.`id`, m.`query` = REPLACE(m.`query`, 'permission=cms%3Acontent%3Alist', 'permission=cms%3Acontent%3Aindex');
UPDATE `fun_admin_menu` m JOIN `fun_permission` o ON o.`id` = m.`permission_id` AND o.`code` = 'cms:submission:list' JOIN `fun_permission` n ON n.`code` = 'cms:contributions:index' SET m.`permission_id` = n.`id`, m.`query` = REPLACE(m.`query`, 'permission=cms%3Asubmission%3Alist', 'permission=cms%3Acontributions%3Aindex');
UPDATE `fun_admin_menu` m JOIN `fun_permission` o ON o.`id` = m.`permission_id` AND o.`code` = 'cms:menu:list' JOIN `fun_permission` n ON n.`code` = 'cms:menus:index' SET m.`permission_id` = n.`id`, m.`query` = REPLACE(m.`query`, 'permission=cms%3Amenu%3Alist', 'permission=cms%3Amenus%3Aindex');
UPDATE `fun_admin_menu` m JOIN `fun_permission` o ON o.`id` = m.`permission_id` AND o.`code` = 'cms:tag:list' JOIN `fun_permission` n ON n.`code` = 'cms:tags:index' SET m.`permission_id` = n.`id`, m.`query` = REPLACE(m.`query`, 'permission=cms%3Atag%3Alist', 'permission=cms%3Atags%3Aindex');
UPDATE `fun_admin_menu` m JOIN `fun_permission` o ON o.`id` = m.`permission_id` AND o.`code` = 'cms:model:list' JOIN `fun_permission` n ON n.`code` = 'cms:models:index' SET m.`permission_id` = n.`id`, m.`query` = REPLACE(m.`query`, 'permission=cms%3Amodel%3Alist', 'permission=cms%3Amodels%3Aindex');
UPDATE `fun_admin_menu` m JOIN `fun_permission` o ON o.`id` = m.`permission_id` AND o.`code` = 'cms:site:grant' JOIN `fun_permission` n ON n.`code` = 'cms:access:index' SET m.`permission_id` = n.`id`, m.`query` = REPLACE(m.`query`, 'permission=cms%3Asite%3Agrant', 'permission=cms%3Aaccess%3Aindex');
UPDATE `fun_admin_menu` m JOIN `fun_permission` o ON o.`id` = m.`permission_id` AND o.`code` = 'qa:moderation:list' JOIN `fun_permission` n ON n.`code` = 'qa:moderation:index' SET m.`permission_id` = n.`id`, m.`query` = REPLACE(m.`query`, 'permission=qa%3Amoderation%3Alist', 'permission=qa%3Amoderation%3Aindex');
