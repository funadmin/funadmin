# 代码生成（含表单设计器改版）实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 用一个 SaiAdmin 风格的「代码生成」页面取代业务开发的四个入口，并重做表单设计器页面；数据仍是同一份业务 Schema，生成链路沿用现有正式生成。

**Architecture:** 后端只做增量：Schema 顶层新增 `relations`、关联生成两处缺陷修复、`formWidth/formFullscreen`、两个新接口（同步表结构、下载 zip）和一条菜单/权限迁移。前端新增 `views/development/business/codegen/` 下的纯 TS 模型（字段表格、关联、同步合并、文件分组）与弹窗组件，`mine.vue` 改为代码生成页；表单设计器只改展示层。

**Tech Stack:** PHP 8.5 / webman 注解路由 / Eloquent / PHPUnit；Vue 3 `<script setup>` / Element Plus / Pinia / vitest / vue-tsc；新增 `shiki`。

**Spec:** `docs/superpowers/specs/2026-11-07-code-generator-design.md`；样机 `.superpowers/design/codegen-design.html`、`.superpowers/design/designer-redesign.html`。

## Global Constraints

- 用户没有修改配置时，现有模块再次预览的生成文件、schemaHash、definitionHash 不变（`admin/tests/BusinessModuleLayoutTest.php` 现有哈希断言一个都不改）。唯一例外：配置了 hasMany / belongsTo 的模块（spec 4.4 缺陷修复）。
- Schema 中 `relations` 缺省或为空数组时不进入 canonical 文档。
- `formWidth` 整数 360–1600（px），`formFullscreen` 布尔；未设置时生成结果逐字节不变。
- 新路由权限码：`development:business:database-sync`、`development:business:formal-generation-archive`（由 `PermissionCode::derive` 推导，测试里断言）。
- 冲突保护不放宽：不新增任何覆盖本地文件的捷径；预览文件内容仍只在拥有 `development:business:generate` 时返回。
- 菜单 id 42 名称「业务开发」→「代码生成」；页面组件路径 `development/business/mine`、菜单名 `BusinessDevelopment`、权限 `development:business:modules` 不变。
- 表单设计器：`useDesigner` store、保存/冲突/离线草稿/版本逻辑、`ListConfigurationPanel` 的数据语义不变；改写已有 spec 只能改选择器/结构，不能删除其行为断言。
- 前端文案一律 `t('key', '中文默认')`，同步补 `web/src/shared/locales/zh-CN|en-US/{business,formDesigner}.ts`。颜色用主题 token，支持 `html.dark`。
- `shiki` 锁定 `1.24.0`（`npm i -E shiki@1.24.0`），只在预览弹窗打开时动态 import，用 `shiki/core` + `shiki/engine/javascript` + 语言 php/sql/typescript/vue/json + 主题 `github-dark`。
- 不修改冻结的 `001_baseline.sql` / `001_baseline.php`；新迁移编号 002（动手前 `ls admin/database/core/migrations admin/database/migrations` 确认未被占用）。
- 不提交、不推送，除非用户明确要求；与并行会话冲突的文件先停下确认。
- 浏览器验证用系统 Edge（`CHROME_PATH="/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge"`），整轮只登录一次（5 次 / 15 分钟限流）；管理员密码由用户提供，不写入任何文件。

## Review Focus

1. 嵌套在 group/tabs/grid 里的字段在表格中修改后写回原位置，未修改的兄弟节点与其他属性不变 —— Task 8 `fieldTable.spec.ts` 覆盖。
2. 有关联到系统表（如 `fun_admin`）的 belongsTo 模块修复后能生成且列表页可用 —— Task 2 `CodeGeneratorRelationsTest::test_belongs_to_system_table_generates_relation_model`。
3. 批量装载 / 批量生成中途某项失败（表已被删、计划阻断）时，其余项照常完成并逐行报告 —— Task 11 `batch.spec.ts`。
4. 没有 `development:business:modules` 但有旧页面权限的角色访问旧地址仍能用 —— Task 12 `legacyRoutes.spec.ts`。
5. 编辑弹窗两步保存第二步失败时不丢改动、不进入预览 —— Task 10 `EditGenerationDialog.spec.ts`。

---

## 文件结构

后端（`admin/`）
- 改 `app/common/form/schema/FormSchemaValidator.php`、`FormSchemaCompiler.php`：`relations` 顶层键。
- 改 `app/admin/development/service/FormCrudDefinitionFactory.php`：映射 `relations`、`formWidth/formFullscreen`。
- 改 `app/common/crud/ProductionTemplateContext.php`、`DefinitionValidator.php`、`schema/crud-definition-v1.schema.json`：关联参数顺序、relation 模型文件、features 可选项。
- 改 `app/admin/development/service/BusinessDevelopmentService.php`、`ManagedGenerationService.php`、`controller/Business.php`：两个接口。
- 新 `database/core/migrations/002_code_generator.sql`、`database/migrations/002_code_generator.php`。

前端（`web/src/admin/`）
- 新 `views/development/business/codegen/`：`fieldTable.ts`、`relationTable.ts`、`syncMerge.ts`、`suggestControl.ts`、`previewFiles.ts`、`batch.ts`、`highlighter.ts`（各配 `.spec.ts`）；
  组件 `CodeBlock.vue`、`CodePreviewDialog.vue`、`EditGenerationDialog.vue`、`tabs/{ConfigTab,FieldTab,RelationTab,MenuTab}.vue`、`LoadTablesDialog.vue`、`BatchGenerateDialog.vue`、`DatabaseSyncDialog.vue`、`RecordsPanel.vue`。
- 改 `views/development/business/mine.vue`（代码生成页）、`records.vue`、`database.vue`、`api/development/business.ts`、`router/routes.ts`、`mock/data/adminSeed.ts`。
- 改 `views/form/designer/index.vue`；新 `views/form/designer/components/{DesignerTopBar,DesignerLeftPanel,ButtonsWorkspace,ButtonInspector,ListPreview,CategoryWorkspace}.vue`；改 `ListButtonEditor.vue`（抽出编辑表单）、`ListConfigurationPanel.vue`、`PropsPanel.vue`（页签名）。
- `composables/useDesigner.ts`：导出 `fieldToNode`、`projectFields`（仅导出，不改实现）。

---

### Task 1: Schema 顶层 `relations`

**Files:** Modify `admin/app/common/form/schema/FormSchemaValidator.php`, `FormSchemaCompiler.php`, `admin/app/common/form/schema/FormSchemaMigrator.php`（如需透传）, `web/src/admin/views/form/schema/types.ts`, `web/src/admin/api/form.ts`；Test `admin/tests/FormSchemaRelationsTest.php`

**Interfaces:**
- Produces: Schema 文档可含 `relations: Array<{ type: 'hasOne'|'hasMany'|'belongsToMany'; name: string; table: string; localField: string; foreignField: string; pivot?: { table: string; localKey: string; foreignKey: string } }>`；TS 类型 `FormSchemaRelation` 与 `FormSchemaDocument.relations?: FormSchemaRelation[]`。

- [ ] **Step 1: 写失败测试** `FormSchemaRelationsTest`（基于现有 Schema 编译测试的最小合法文档）：
  - `test_empty_or_missing_relations_do_not_change_hash`：同一文档 无键 / `relations: []` 编译后 `hash()` 相同且 `document()` 不含 `relations`。
  - `test_valid_relations_are_kept_in_canonical_document`：一条 hasOne + 一条带 pivot 的 belongsToMany，`document()['relations']` 原样保留。
  - `test_invalid_relations_are_rejected`（dataProvider）：非法标识符 / name 重复 / name 与字段名冲突 / localField 不存在 / belongsToMany 缺 pivot / hasOne 带 pivot / type=belongsTo → 抛 `FormSchemaException`。
- [ ] **Step 2:** `cd admin && vendor/bin/phpunit tests/FormSchemaRelationsTest.php` → FAIL（未知顶层字段 relations）。
- [ ] **Step 3:** `TOP_LEVEL_KEYS` 加 `relations`；校验规则同上（标识符正则沿用该类现有字段名规则）；Compiler canonicalize 时空数组删除该键。TS 类型补齐。
- [ ] **Step 4:** 同命令 → PASS；`vendor/bin/phpunit tests/BusinessModuleLayoutTest.php` → PASS（哈希不变）。

### Task 2: 关联生成映射与两处缺陷修复

**Files:** Modify `FormCrudDefinitionFactory.php`, `ProductionTemplateContext.php`, `DefinitionValidator.php`, `crud-definition-v1.schema.json`；Test `admin/tests/Console/CodeGeneratorRelationsTest.php`（沿用 `CrudDefinitionFixture` / `CrudRelationsExecutionTest` 的构造方式）

**Interfaces:**
- Consumes: Task 1 的 `relations`。
- Produces: CrudDefinition relation 对象可选 `modelClass`（完整类名，`^app\\[a-z][a-z0-9_\\]*\\[A-Z][A-Za-z0-9]*$`）；生成目标增加 `model/relation/{Target}.php`（每个非自关联目标一个）。

- [ ] **Step 1: 写失败测试**：
  - `test_schema_relations_map_to_definition`：hasOne(localField=id, foreignField=goods_id) → `['type'=>'hasOne','field'=>'id','targetField'=>'goods_id']`；belongsToMany pivot 映射到 `pivotTable/pivotLocalKey/pivotTargetKey`。
  - `test_has_one_and_has_many_use_eloquent_argument_order`：生成模型源码包含 `->hasMany(\…\relation\ShopGoodsSku::class, 'goods_id', 'id')`；belongsTo 仍为 `->belongsTo(…, 'category_id', 'id')`。
  - `test_relation_targets_get_their_own_model_file`：生成文件列表含 `app/admin/generated/shop_goods/model/relation/ShopCategory.php`，内容 `protected $table = 'shop_category'`（去前缀）与 `$connection`；关联方法与服务选项查询引用该完整类名。
  - `test_self_relation_reuses_own_model`：parent_id → 本表时不生成 relation 文件，引用本模块模型类。
  - `test_belongs_to_system_table_generates_relation_model`：关联 `fun_admin` 生成 `relation/Admin.php`，生成模块执行测试（按 `CrudRelationsExecutionTest` 方式）列表接口返回关联数据不报错。
  - `test_modules_without_relations_are_byte_identical`：无关联 fixture 生成的全部文件哈希与修改前一致（先在 Step 2 前记录基准）。
- [ ] **Step 2:** `vendor/bin/phpunit tests/Console/CodeGeneratorRelationsTest.php` → FAIL。
- [ ] **Step 3:** Factory 映射 `relations`；Template 按类型输出参数顺序；relation 目标生成独立模型文件（研究 `targets()`/templates 如何登记多文件输出，按同一机制登记）；Validator/JSON schema 接受 `modelClass`。
- [ ] **Step 4:** 本测试 + `tests/Console/CrudRelationsExecutionTest.php` + `tests/BusinessModuleLayoutTest.php` → PASS。若 `CrudRelationsExecutionTest` 断言了旧的反向参数，记 Ruling 后按新顺序更新该断言。

### Task 3: 表单宽度与全屏

**Files:** Modify `BusinessDevelopmentService::PUBLISH_CONFIG_KEYS`, `FormCrudDefinitionFactory::config()`, `crud-definition-v1.schema.json`（features 可选 `formWidth`、`formFullscreen`）, `DefinitionValidator.php`, `ProductionTemplateContext.php`（约 1058 行 el-dialog/el-drawer）；Test `admin/tests/Console/CodeGeneratorFormSizeTest.php`

- [ ] **Step 1: 写失败测试**：`test_unset_size_keeps_generated_view_identical`（视图文件哈希不变）；`test_form_width_and_fullscreen_reach_dialog_and_drawer`（dialog：`width="800px"`、`:fullscreen="true"`；drawer：`size="800px"`）；`test_form_width_out_of_range_is_rejected`（359、1601 抛异常）；`test_publish_config_accepts_new_keys`。
- [ ] **Step 2:** 运行 → FAIL。 **Step 3:** 实现（未设置时不写入 features）。 **Step 4:** 本测试 + `BusinessModuleLayoutTest` → PASS。

### Task 4: 同步表结构接口

**Files:** Modify `controller/Business.php`, `BusinessDevelopmentService.php`；Test `admin/tests/BusinessDatabaseSyncTest.php`（MySQL 集成，参照 `tests/Console/MySqlCrudInspectTest.php` 建临时表）

**Interfaces:**
- Produces: `#[Post('/modules/{id:\d+}/schema/database-sync')] Business::databaseSync(int $id)` → `BusinessDevelopmentService::databaseSync(int $moduleId): array{inspectionHash:string, added:list<array>, removed:list<string>, changed:list<array{field:string, from:array, to:array}>}`。

- [ ] **Step 1: 写失败测试**：建表并装载为模块 → 加列 `stock int`、删列 `remark`、改 `title` 长度 → `added[0]` 与「从数据库创建」对同一列生成的节点一致（同一转换路径）、`removed == ['remark']`、`changed[0]['field'] == 'title'` 且 from/to 只含 columnType/nullable/comment；可视化新建模块调用抛「仅数据库装载的模块可同步」；不写库（Schema 版本数不变）；`PermissionCode::derive(Business::class, 'databaseSync') === 'development:business:database-sync'`。
- [ ] **Step 2:** → FAIL。 **Step 3:** 用 `inspectDatabase` + 创建时相同的字段→节点转换计算差异。 **Step 4:** → PASS。

### Task 5: 下载 zip 接口

**Files:** Modify `Business.php`, `BusinessDevelopmentService.php`, `ManagedGenerationService.php`（暴露预览文本计划）；Test `admin/tests/BusinessGenerationArchiveTest.php`

**Interfaces:**
- Produces: `#[Get('/modules/{id:\d+}/formal-generation/archive')] Business::formalGenerationArchive(int $id)` → `application/zip`，`Content-Disposition` 文件名 `{code}-{YmdHis}.zip`；无 generate 权限 403。

- [ ] **Step 1: 写失败测试**：zip 条目 = 预览中非 delete 文件的路径集合，内容等于 remoteContent；无权限 403；derive 得到 `development:business:formal-generation-archive`；临时文件响应后删除。
- [ ] **Step 2–4:** FAIL → 用 `ZipArchive` 实现 → PASS。

### Task 6: 菜单与权限迁移

**Files:** Create `admin/database/core/migrations/002_code_generator.sql`, `admin/database/migrations/002_code_generator.php`；Modify `web/src/admin/mock/data/adminSeed.ts`, `admin/tests/fixtures/auth-requirements.json`（只新增两条路由）；Test `admin/tests/CodeGeneratorMigrationTest.php`

- [ ] **Step 1: 写失败测试**：执行两遍迁移后 —— id 42 `name` 为「代码生成」；两条新权限 `status=1`、`pid` 与 `development:business:preview-formal-generation` 相同；持有 `inspect-database` 的角色获得 `database-sync`、持有 `generate` 的角色获得 `formal-generation-archive`（查 `casbin_rule`），其他角色不变；重复执行无重复行。`AuthRequirementSnapshotTest` 通过。
- [ ] **Step 2–4:** FAIL → SQL 用 UTF-8 十六进制字面量写中文、全部 `WHERE NOT EXISTS` 幂等；PHP 版用 `PermissionSyncService` + Db → PASS。

### Task 7: 前端 API 与代码高亮

**Files:** Modify `web/src/admin/api/development/business.ts`, `web/package.json`；Create `codegen/highlighter.ts`, `codegen/CodeBlock.vue`, `codegen/highlighter.spec.ts`

**Interfaces:**
- Produces: `businessDevelopmentApi.databaseSync(id): Promise<DatabaseSyncDiff>`；`businessDevelopmentApi.formalGenerationArchive(id): Promise<Blob>`（`http.download`）；`highlight(code: string, lang: CodeLanguage): Promise<string>`；`type CodeLanguage = 'php'|'sql'|'typescript'|'vue'|'json'|'text'`；`<CodeBlock :code :language />`（行号、复制按钮，`text` 或失败时退化为转义纯文本）。

- [ ] **Step 1:** 测试：`highlight('<?php echo 1;', 'php')` 含 `<span`；`text` 返回转义后的原文；加载失败时 CodeBlock 显示纯文本不抛错。
- [ ] **Step 2–4:** FAIL → `npm i -E shiki@1.24.0` + 实现 → `cd web && npx vitest run src/admin/views/development/business/codegen/highlighter.spec.ts` PASS。

### Task 8: 字段表格 / 关联 / 同步合并 / 控件推荐 模型

**Files:** Create `codegen/{fieldTable,relationTable,syncMerge,suggestControl}.ts` + 同名 `.spec.ts`；Modify `views/form/composables/useDesigner.ts`（仅 `export` `fieldToNode`、`projectFields`）

**Interfaces:**
- Produces:
  - `interface FieldRow { path: string; parentPath: string; field: string; title: string; columnType: string; required: boolean; inForm: boolean; inList: boolean; filter: string; sortable: boolean; component: string; dictionary: string; listWidth: number; groupTitle: string; container: boolean }`（`filter` 为 '' 表示不可查询，否则为操作符，取值沿用 `registry` 的 `LIST_FILTERS`）
  - `schemaToFieldRows(doc: FormSchemaDocument): FieldRow[]`
  - `type FieldRowPatch = Partial<Pick<FieldRow, 'title'|'required'|'inForm'|'inList'|'filter'|'sortable'|'component'|'dictionary'|'listWidth'>>`
  - `applyFieldPatches(doc, patches: Record<string, FieldRowPatch>): FormSchemaDocument`（经 `projectFields` → 合并 → `fieldToNode`，与设计器同路径）
  - `moveFieldWithinParent(doc, path: string, toIndex: number): FormSchemaDocument`
  - `interface RelationRow { origin: 'field'|'subform'|'model'; type: 'belongsTo'|'hasOne'|'hasMany'|'belongsToMany'; name: string; table: string; localField: string; foreignField: string; labelField?: string; pivot?: {table:string; localKey:string; foreignKey:string}; path?: string; editable: boolean }`
  - `schemaToRelationRows(doc): RelationRow[]`；`applyRelationRows(doc, rows: RelationRow[]): FormSchemaDocument`
  - `mergeDatabaseSync(doc, diff: DatabaseSyncDiff, pick: { added: string[]; removed: string[]; changed: string[] }): FormSchemaDocument`
  - `suggestControl(field: string, columnType: string): string | null`

- [ ] **Step 1: 写失败测试**：
  - fieldTable：tabs/group 内字段列出且 `groupTitle` 正确；repeatable/subform 一行 `container: true`、子字段不展开；只改一行时其他节点 `toStrictEqual` 原节点；`filter` 由 '' 改 'like' 后节点 `list.filter === 'like'`；`required` 切换增删 `{type:'required'}`；跨父容器 move 抛错、同容器 move 顺序正确；与设计器 `updateField` 对同一改动产出相同节点。
  - relationTable：belongsTo 行来自字段 `database.relation`；编辑它写回字段节点；hasOne/belongsToMany 写顶层 `relations`；删除全部 model 关联后文档无 `relations` 键；subform 行 `editable: false`。
  - syncMerge：只合并勾选项；changed 只改 `database.*`，保留用户改过的 title/type/list/dataSource；added 追加到根节点末尾。
  - suggestControl：`created_at`/datetime→`datetime`、`status`→`radio`、`cover|image|avatar`→`image`、`content` text→`richtext`、其他→null。
- [ ] **Step 2–4:** FAIL → 实现 → `npx vitest run src/admin/views/development/business/codegen/` PASS。

### Task 9: 预览代码弹窗

**Files:** Create `codegen/previewFiles.ts`(+spec), `codegen/CodePreviewDialog.vue`(+`CodePreviewDialog.spec.ts`)

**Interfaces:**
- Consumes: Task 7 `CodeBlock`、`highlight`；现有 `GenerationFileDiff.vue`、`FILE_STATUS_META`、`businessDevelopmentApi.{previewFormalGeneration,formalGeneration,previewPublish,publish,adoptResolvedBaseline}`。
- Produces: `groupPreviewFiles(files): Array<{ group: 'backend'|'database'|'frontend'|'lang'|'test'|'other'; files: BusinessGenerationFile[] }>`、`fileLanguage(path): CodeLanguage`；`<CodePreviewDialog v-model="visible" :module="BusinessModule" @generated @published />`。

- [ ] **Step 1: 写失败测试**：分组规则（`app/admin/generated/**/controller|service|model|validate`→backend，`database/generated/*.sql`→database，`web/src/admin/{views,api}/generated/**`→frontend，`app/admin/lang/**`→lang，`tests/**`/`web/tests/**`→test）；弹窗显示状态计数标签；无内容（无 generate 权限）显示提示文字；blocked 时「生成到项目」禁用；切「与本地对比」渲染 `GenerationFileDiff`；插件目标不显示「动态发布」；「下载 zip」调用 `formalGenerationArchive`。
- [ ] **Step 2–4:** FAIL → 实现 → PASS。

### Task 10: 编辑生成信息弹窗

**Files:** Create `codegen/EditGenerationDialog.vue`, `codegen/tabs/{ConfigTab,FieldTab,RelationTab,MenuTab}.vue`, `codegen/EditGenerationDialog.spec.ts`

**Interfaces:**
- Consumes: Task 8 模型；`businessDevelopmentApi.{module,saveSchema,savePublishConfig,schemaVersions}`；`VersionHistoryDrawer`；字典接口 `dictApi`；菜单树 `menuApi.tree()`。
- Produces: `<EditGenerationDialog v-model="visible" :module-id="number" initial-tab?="config|fields|relations|menu" @saved @preview />`；设计器同样使用它（Task 13）。

- [ ] **Step 1: 写失败测试**：配置页签含设计器原「基本信息」全部项（来源、绑定表、所属菜单、树形列表/父级字段/新增页父级选择）；生成目标只读；字段页签批量「全选列表」；保存顺序 saveSchema → savePublishConfig；saveSchema 冲突时不调用 savePublishConfig 并提示核对；第二步失败弹窗保持打开、提示「字段已保存，发布设置未保存」、保留改动；「保存并预览」任一步失败不 emit `preview`；有未保存改动时「打开表单设计器」先提示。
- [ ] **Step 2–4:** FAIL → 实现 → PASS。

### Task 11: 装载、同步、批量生成

**Files:** Create `codegen/batch.ts`(+spec), `codegen/LoadTablesDialog.vue`, `codegen/DatabaseSyncDialog.vue`, `codegen/BatchGenerateDialog.vue`, `codegen/dialogs.spec.ts`

**Interfaces:**
- Produces: `runSequential<T, R>(items: T[], worker: (item: T) => Promise<R>, onProgress?: (done: number) => void): Promise<Array<{ item: T; ok: true; value: R } | { item: T; ok: false; error: string }>>`。

- [ ] **Step 1: 写失败测试**：`runSequential` 第 2 项抛错时第 3 项仍执行、结果顺序与输入一致；装载弹窗把已装载表置灰、按 `inspect → from-database` 顺序逐张调用并逐行显示结果；同步弹窗按勾选调用 `mergeDatabaseSync` 后 `saveSchema`；批量生成先逐个 preview 汇总，确认后只对未阻断模块调用 formalGeneration（各用自己的 confirmToken）。
- [ ] **Step 2–4:** FAIL → 实现（业务名称/标识默认值复用 `visual.vue` 现有推导函数，必要时抽到 `constants.ts`）→ PASS。

### Task 12: 代码生成页与旧路由

**Files:** Modify `views/development/business/mine.vue`, `records.vue`（主体抽成 `codegen/RecordsPanel.vue`）, `database.vue`, `router/routes.ts`, `mine.spec.ts`, `records.spec.ts`；Create `codegen/legacyRoutes.spec.ts`；locales

- [ ] **Step 1: 写失败测试**：列表列与工具栏/行操作按 spec 4.2（含常驻「设计」）；同步按钮对可视化新建行禁用并有提示；`?tab=records` 打开生成记录页签；有 `development:business:modules` 时 `/development/business/database` 跳到代码生成页并打开装载弹窗、`/records` 跳到记录页签并保留 `moduleId`；无该权限时两条旧路由渲染原页面；`visual.vue` 创建成功仍进入设计器（`visual.spec.ts` 保持通过）。
- [ ] **Step 2–4:** FAIL → 实现 → `npx vitest run src/admin/views/development/business` PASS。

### Task 13: 设计器外壳（顶栏、页签、表单设置、预览/生成）

**Files:** Modify `views/form/designer/index.vue`；Create `designer/components/DesignerTopBar.vue`(+spec)；Modify `web/tests/contracts/form-publish.spec.ts`

- [ ] **Step 1: 写失败测试**：顶栏元素与状态点颜色映射（saved/unsaved/failed/离线）；⌘Z/Ctrl+Z 撤销、⇧⌘Z/Ctrl+Shift+Z 重做；「⋯」含动态发布（插件目标隐藏）、版本历史、导入/导出 JSON、打开运行时；页签只有 表单设计/按钮与工具/分类管理；「⚙ 表单设置」打开 `EditGenerationDialog`；「预览代码」「生成」打开 `CodePreviewDialog` 且 dirty 时禁用；冲突横幅显示并可「核对版本」。
  `form-publish.spec.ts`：把对三步弹窗的字符串断言改为断言 `CodePreviewDialog.vue` 含 `confirmToken`、`previewFormalGeneration`、`formalGeneration`、不含 `allowOverwrite`；所属菜单断言改指向 `ConfigTab.vue`。
- [ ] **Step 2–4:** FAIL → 实现（移除基本信息页签与三步弹窗）→ `npx vitest run src/admin/views/form tests/contracts/form-publish.spec.ts` PASS。

### Task 14: 表单设计页签三栏

**Files:** Create `designer/components/DesignerLeftPanel.vue`(+spec)；Modify `designer/index.vue`, `designer/components/{DesignerCanvas,DesignerCanvasNode,PropsPanel,SchemaNodeTree}.vue`

- [ ] **Step 1: 写失败测试**：左栏「控件/大纲」切换，控件搜索按名称过滤、分组计数；大纲常驻（不再是抽屉）、点击节点选中、含「添加布局分组」；选中节点显示上移/下移/复制/删除；属性面板页签为 表单/列表/数据库列/属性，校验/联动/数据源/原始 JSON 为折叠项；预览模式隐藏左右栏并在页签行显示 创建/编辑/只读/详情 与「预览数据」。现有 `controlPalette.spec.ts`、`PropsPanel.spec.ts`、`structuredEditor.spec.ts` 仍通过。
- [ ] **Step 2–4:** FAIL → 实现（窄屏 <1100px 属性栏抽屉、<760px 控件栏抽屉）→ PASS。

### Task 15: 按钮与工具页签

**Files:** Create `designer/components/{ButtonsWorkspace,ButtonInspector,ListPreview}.vue`(+`ButtonsWorkspace.spec.ts`)；Modify `ListButtonEditor.vue`（编辑表单抽成 `ButtonInspector`，原抽屉删除）、`ListConfigurationPanel.vue`

- [ ] **Step 1: 写失败测试**：每个位置「继承默认/自定义/全部隐藏」分别对应 `buttons[loc]` undefined / 数组 / `[]`；点击预览中的按钮选中并在右栏编辑；右栏五个页签字段齐全（与原抽屉一致）；拖拽改顺序写回 `order`；工具开关同步预览图标；「未选中行/选中 2 行」切换按 `selection.min/max` 显示禁用；示例行套用 `disabledWhen`；「刷新目录」调用原 `loadButtonCatalog`。现有 `ListButtonEditor.spec.ts`、`ListConfigurationPanel.spec.ts` 改选择器后行为断言全部保留并通过。
- [ ] **Step 2–4:** FAIL → 实现 → PASS。

### Task 16: 分类管理页签

**Files:** Create `designer/components/CategoryWorkspace.vue`(+spec)；Modify `ListConfigurationPanel.vue`

- [ ] **Step 1: 写失败测试**：三张图卡互斥，选「顶部选项页签」写 `category.enabled=true` 并关闭 `leftTree`，反之亦然，「不分类」两者都关；左侧分类树分组显示来源/字段对应/选择行为/节点操作；未选父级字段时「新增子节点」「包含后代」禁用（沿用现有规则）；预览随方式切换。
- [ ] **Step 2–4:** FAIL → 实现 → PASS。

### Task 17: 全量验证

- [ ] `cd admin && vendor/bin/phpunit > /tmp/codegen-php.log 2>&1; tail -40 /tmp/codegen-php.log`：与开始前基线对比，无新增失败（开始前先跑一次记录基线）。
- [ ] `cd web && npx vitest run > /tmp/codegen-web.log 2>&1; tail -30 /tmp/codegen-web.log` 全部通过；`npm run type-check` 0 错误（基线已有的错误单独列出）。
- [ ] 后端 `php start.php reload`，执行迁移；系统 Edge + Playwright（登录一次）截图：代码生成列表、装载弹窗、编辑弹窗四个页签、预览代码、设计器三个页签与手机预览，亮色 + 暗色各一套，逐张与样机核对。
- [ ] 手工走一遍：装载一张表 → 编辑字段 → 预览 → 生成 → 打开生成页面可用；对生成文件手改后再生成出现冲突并被阻断。
