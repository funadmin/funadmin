# generated → business 改名实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development.

**Goal:** 把代码生成产物的目录、命名空间、接口前缀、菜单路由、权限码、自定义动作注册键、默认发布配置从 `generated` 全部改为 `business`，不保留兼容。
**Spec:** `docs/superpowers/specs/2026-11-08-generated-to-business-rename-design.md`

## Global Constraints
- 新名称表见 spec「改名范围」；不改：`source_type='generated'`、`GeneratedFileBaseline`/`GeneratedLanguagePacks` 等类名与表名、CMS 等插件里与生成列相关的 generated。
- 不保留任何 `generated` 识别分支或重定向。
- 除 generated→business 替换外生成内容不得有其他差异；哈希基线（BusinessModuleLayoutTest、CodeGeneratorRelationsTest PLAIN_BASELINE、CodeGeneratorFormSizeTest 等）按替换后的实际内容更新，并在报告里说明每个变动的哈希只因替换而变。
- 冲突保护不放宽；不提交；不碰真实 MySQL；不改其他会话的 CMS 文件。

## Review Focus
1. 漏改：任何仍生成或识别 `generated` 路径/前缀/权限码/注册键的代码（grep 证明）。
2. 误改：`source_type='generated'`、类名表名、插件里的 generated 被改。
3. 权限推导：`PermissionCode::derive` 对 `app\admin\business\{dir}\controller\{Class}` 得到 `business:{kebab(Class)}:{method}`，且不与 `development:business:*` 混淆。
4. 前端动态路由识别 `business/` 组件前缀；预览文件分组、自定义动作「待生成」判断用新前缀。

### Task 1: 后端改名
**Files:** admin/app/common/crud/*（ArtifactPaths、CrudDefinition、CrudGenerator、DefinitionValidator、GenerationPlanner、ProductionTemplateContext、schema JSON、ConfirmationToken）、admin/app/admin/development/service/*（FormCrudDefinitionFactory 默认 module/apiPrefix/routePath、DevCrudService、ManagedGenerationService、BusinessTargetService 等）、admin/app/admin/authorization/*（PermissionCode、PermissionResource、PermissionTreeService、AdminAuthorizationService、AdminMenu、SystemMenu/SystemPermission）、admin/app/admin/form/service/{FormDataService,FormDesignerService}、admin/app/common/form/*（CustomActions、FormRegistryFactory 清单目录、FormSchemaValidator、FormSchemaDependencyChecker）、admin/app/common/service/translation/GeneratedLanguagePacks 的路径、lang messages、对应测试与快照。
- [ ] 先改测试期望（新路径/命名空间/权限码/注册键），看到失败；再改实现；全量 phpunit 只剩已知无关失败（报告列出）。

### Task 2: 前端改名
**Files:** web/src/admin/router/dynamic.ts、views/development/business/**（预览分组、默认 apiPrefix/routePath、constants、mock）、views/form/**（listButtonHost、customActions.ts、ListButtonBar 待生成前缀、types）、views/system/menu、shared/types、locales、mock/modules/developmentBusiness.ts；把 `views/generated/i18n-demo`、`api/generated/i18n-demo.ts` 移到 `views/business/`、`api/business/` 并改引用；删除 `views/generated/order`、`api/generated/order.ts`（由控制者重新生成）。
- [ ] 先改测试期望再实现；`npx vitest run` 与 `vue-tsc` 只剩已知无关失败。

### Task 3: 本仓库现有模块（控制者执行）
- [ ] 备份开发库；删除 `admin/app/admin/generated/order`、`admin/database/generated/order_*`、`admin/tests/generated/OrderGeneratedTest.php`、`web/tests/generated/order.spec.ts`；清理开发库里旧 order 的菜单、`generated:order:*` 权限与授权、生成记录与基线；用新代码对模块 2 预览并正式生成；浏览器打开 `/business/order` 并执行自定义动作；全量测试。
