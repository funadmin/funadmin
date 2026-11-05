# Plugin Hooks Implementation Plan

> **For agentic workers:** Use superpowers:dispatching-parallel-agents for independent backend runtime, manifest/API, and frontend runtime domains; root integrates and verifies the completed interfaces.

**Goal:** 全局可调用、跨插件的 PHP/JS 动作与过滤钩子，支持前端组件插槽和纯钩子插件。
**Architecture:** Manifest v2 optional backendHooks/frontendHooks, activation snapshot for PHP runtime, permission/audience projected descriptors for shared browser runtime. Existing publication and lifecycle remain authoritative.
**Tech Stack:** PHP 8.5, Webman, Vue 3, TypeScript, PHPUnit, Vitest; no new dependencies.
**Spec:** docs/superpowers/specs/2026-11-05-plugin-hooks-design.md

## Global Constraints
- 任意 PHP 调用位置与任意浏览器组件共享各自 runtime，支持跨插件任意扩展点。
- 保留现有统一目录、生命周期和源码发布，不安装依赖、不迁移业务数据库、不覆盖无关改动。
- 本次在当前 Rbac 工作区增量实施以保留已有未提交插件修复；不重置、不移动用户代码。
- 用户已明确“实施”，按已批准设计连续完成，不再次请求计划或执行方式批准。
- 后端 hooks 私有路径 hooks/*.php 返回 callable map；前端 file 为 adminWeb.source 相对 ts/js，component 引用 adminWeb.components。
- 排序 priority(default100), code, id；嵌套使用 context.hooks (PHP context['hooks'])，最大深度32。
- 默认前端 audience admin；public/member/admin 精确匹配；permission 项只允许 admin。
- 不自动提交混合工作区中的用户修改；验证后提供可审阅改动。

## Review Focus
- 禁用、缺失依赖、坏快照及在加载 PHP 源码前执行激活检查。
- 前端异步旧导入、同 generation 权限变化、scope 清理与迟到 cleanup。
- 纯函数/纯钩子插件无应用/菜单/routes/components 仍可运行。
- 匿名/会员/管理员认证隔离与相同 audience 会话更换。
- 上传复用/分块只触发一次完成事件，上传返回 filter 不改核心字段。

### Task 1: PHP dispatch and activation
Files: create app/common/plugin/hook/{Hook,HookRuntime}.php and tests/PluginHookRuntimeTest.php; modify sdk/{ActivationGate,PluginActivationCompiler,PluginActivationReader,Service}.php.
Interfaces:
- HookRuntime(reader: PluginActivationReader, pluginsPath: string, reporter?: callable), action(point,payload=null,context=[]):void and filter(point,value,context=[]):mixed.
- Hook facade resolves runtime from Container and exposes same static methods.
- Compiled plugin optional backendHooks field contains manifest records; handler file integrity metadata is compiled and validated by runtime.
- Snapshot remains backward compatible with old no-hooks shape.
Steps:
- [x] Write/run failing tests for no-app cross-plugin callable dispatch, deterministic order, false/null chain, errors, dependency, corrupt snapshot, disable across readers, gate-before-include, depth32.
- [x] Implement plugin-level activation independent of application; preserve application gates.
- [x] Run new tests and relevant existing activation tests.

### Task 2: Manifest, publication, and descriptor APIs
Files: modify sdk/{Manifest,schema/plugin.schema.json,PluginArchiveService}.php as needed; new sdk/HookManifest.php or focused validator; service FrontendHookService and HTTP descriptor controller; tests PluginHookManifestTest/PluginHookDescriptorTest; extend PluginCenterService.enabledModules.
Interfaces:
- optional backendHooks/frontendHooks arrays as spec.
- frontend HookModule DTO: {code:string,version:string,generation?:string,components:Record<string,string>,frontendHooks:HookDescriptor[],routes?:existing route DTO[]}.
- standalone GET /plugins/hooks/frontend?audience=public|member|admin returns normal JSON success envelope data:{generation:string,modules:HookModule[]}.
- Admin uses BrowserAuthService session; member uses existing MemberAuthService identity; public reveals only explicit public hooks. No hook execution endpoint.
- enabledModules existing array remains compatible; include pure frontend-hook plugins and fields frontendHooks,generation.
Steps:
- [x] Write/run failing manifest tests for no-app/no-route, duplicates, ownership, path symlink/extension, permission audience.
- [x] Implement validation and descriptors without invoking plugin handlers, with snapshot/dependency gating.
- [x] Test standalone audience authentication and enabledModules compatibility and publication of hooks-only sources.

### Task 3: Browser dispatch, slots, synchronization
Files: new src/shared/plugins/hooks/{types,runtime,startup,index}.ts and PluginSlot/error boundary; modify main/App/guard or existing startup as minimally needed, user/member logout, api/system/plugin DTO, dashboard; corresponding Vitest specs.
Interfaces:
- export hooks singleton, HookRuntime class, HookCancelledError, HookModule DTO matching Task2.
- runtime sync(modules,identity), clear(), action/filter, reactive revision for slot/host refresh.
- context.hooks bound dispatch, signal, onDispose; host context signal composes plugin revision lifecycle.
- sourceModules from Vite build whitelist src/admin/modules; lazy imports, no remote import.
- shared bootstrap route audience exact rules and 30s-visible/focus synchronization; latest request wins, session switch clears first.
Steps:
- [x] Write/run failing runtime tests for ordering/false/null, nested calls, depth, two concurrent chains, disable/late imports, permissions signature and cleanup.
- [x] Implement runtime, component slot isolation, descriptor sync.
- [x] Hook dashboard cards/loaded/quickActions and scope cleanup; test no app routes needed.
- [x] Run focused Vitest and npm run type-check.

### Task 4: Host integration and examples
Files: UploadService.php, AdminUpload.php, focused tests; docs/plugins.md; private example under docs/examples/plugin-hooks with no app/menu/routes.
Interfaces: Hook facade Task1; manifest fields Task2; frontend hooks API Task3.
Steps:
- [x] Write/run failing upload tests for completion on created/reused/chunk merge; no unfinished-chunk event.
- [x] Add upload.completed metadata created/reused and upload.result extension-only result validation, explicit error behavior.
- [x] Supply example PHP callable map, TS action/filter and Vue card plus cross-plugin invocation instructions.
- [x] Run related upload tests and example manifest validation.

### Task 5: Integrated verification and fresh review
- [x] Run PHP hook/manifest/descriptor/upload and existing activation/publication/scaffold tests.
- [x] Run browser hook/slot/bootstrap/dashboard tests, existing plugin route tests, type-check and Webman build.
- [x] Fresh independent code review of this feature, fix meaningful findings with regression tests.
- [x] Record exact verification evidence, limitations and any baseline failures; final delivery in current workspace.
