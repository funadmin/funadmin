# 代码生成追加实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** 新建表弹窗替代可视化创建页、属性面板改版、自定义动作（设计器新建 + 正式生成处理方法与权限）。
**Spec:** `docs/superpowers/specs/2026-11-08-codegen-followups-design.md`

## Global Constraints
- 现有模块未改配置时：生成文件、schemaHash、definitionHash 不变（`BusinessModuleLayoutTest`、`CodeGeneratorRelationsTest::PLAIN_BASELINE` 不改）；`list.customActions` 空/缺省不进 canonical。
- 自定义动作执行只走现有注册动作链（FormActionRegistry / ListButtonExecutor），不新增可调用任意接口的入口。
- 注册键 `generated.{entity}.{key}`；权限码 `generated:{entity}:action-{kebab(key)}`。
- 冲突保护不放宽；文案 `t('key','中文')` + zh-CN/en-US；主题 token + `html.dark`；不提交、不暂存。
- 测试：后端默认 sqlite（`cd admin && vendor/bin/phpunit`）；前端 `cd web && npx vitest run`；`npx vue-tsc --noEmit` 只允许 MenuItemsEditor.vue / ProviderSettingsDrawer.vue 的既有错误。

## Review Focus
1. 自定义动作 key 与已有注册键或另一模块冲突 → 加载时明确报错，不静默覆盖。
2. 用户在生成的 `{Class}Actions.php` 写了逻辑后再次生成 → 三方合并保留，不覆盖。
3. 未生成就动态发布 → 运行时按钮禁用并提示，不报错。
4. 属性面板栅格值不是 6/8/12/24 → 显示「自定义 N 列」，不被改写。
5. 无代码生成页面权限的角色访问 `/development/business/visual` → 仍显示旧页面。

### Task 1: 后端自定义动作
**Files:** `admin/app/common/form/schema/FormSchemaValidator.php`（list.customActions）、`FormSchemaCompiler.php`（空数组剔除）、`admin/app/admin/development/service/FormCrudDefinitionFactory.php`、`admin/app/common/crud/{DefinitionValidator,ProductionTemplateContext,CrudGenerator}.php` + `schema/crud-definition-v1.schema.json` + 新模板、`admin/app/common/form/registry/FormRegistryFactory.php`；Test `admin/tests/Console/CodeGeneratorCustomActionsTest.php`、`admin/tests/FormSchemaCustomActionsTest.php`
- [ ] 测试先行：校验规则（合法/非法 key、重复、batch 仅 toolbar、参数上限与类型、空数组不改 hash）；生成文件列表含 `action/{Class}Actions.php` 与 `config/actions.php`，内容含正确 handler/permission/targets/parameterTypes；权限 SQL 含动作权限行；无自定义动作的模块文件哈希不变；FormRegistryFactory 合并生成清单、键冲突抛错；端到端（sqlite，参照 CrudRelationsExecutionTest）：生成后 listAction 执行该动作调用到方法桩、无权限 403/禁止；手改 Actions.php 后再生成计划为 auto-merged/keep-local 不覆盖。
- [ ] 实现 → 全部通过 → 全量 phpunit 无新增失败。

### Task 2: 设计器自定义动作
**Files:** `web/src/admin/views/form/schema/types.ts`、`designer/components/{ButtonInspector.vue,useButtonDraft.ts,ButtonsWorkspace.vue,ListConfigurationPanel.vue}`、新 `designer/components/CustomActionForm.vue`、运行时 `web/src/admin/views/form/runtime/*`/`components/ListButtonBar.vue`（禁用提示）；Test `CustomActionForm.spec.ts`、`ButtonsWorkspace.spec.ts`
- [ ] 测试先行：「＋ 新建自定义动作」表单校验与写入 `list.customActions`；下拉「自定义动作」分组含待生成标记；选中后按钮 action 为 `{type:'registered', key:'generated.{entity}.{key}'}` 且参数绑定出现；删除自定义动作移除绑定按钮（有确认）；插件目标不显示入口并说明；运行时注册目录缺少该键时按钮禁用并提示「正式生成后可用」。
- [ ] 实现 → 通过。

### Task 3: 新建表弹窗
**Files:** 新 `web/src/admin/views/development/business/codegen/CreateTableDialog.vue`、`mine.vue`、`visual.vue`（旧路由跳转，复用 `useLegacyCodegenRedirect`）、`constants.ts`；Test `codegen/CreateTableDialog.spec.ts`、`legacyRoutes.spec.ts`
- [ ] 测试先行：字段与默认值（标识建议、表名自动补前缀且可改、目标默认核心）、提交 payload 与现有 createVisual 一致、成功后进入设计器、`?create=1` 自动打开、旧路由按权限跳转或渲染旧页。
- [ ] 实现 → 通过。

### Task 4: 属性面板改版
**Files:** `web/src/admin/views/form/designer/components/{PropsPanel.vue,PropsFieldFolds.vue}`（必要时拆分子组件）；Test `PropsPanel.spec.ts`
- [ ] 测试先行：分组与上下布局；宽度分段 ↔ span 6/8/12/24 双向，其他值显示「自定义 N 列」不改写；三个状态切换块写回原字段；校验/联动摘要文本；「只读」对应原「编辑禁改」字段；所有原 patch 键仍存在。
- [ ] 实现 → 通过。

### Task 5: 验证（控制者执行）
- [ ] 全量 phpunit / vitest / vue-tsc；浏览器截图（新建表弹窗、属性面板、自定义动作检查器，亮/暗）；用 `fun_order` 走「新建自定义动作 → 保存 → 动态发布 → 预览 → 生成 → 打开生成页面并点自定义按钮得到未实现提示 → 手改生成文件 → 再生成被保护」。
