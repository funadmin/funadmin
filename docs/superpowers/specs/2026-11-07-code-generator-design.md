# 开发工具 › 代码生成（SaiAdmin 风格重做，融合现有业务开发）

日期：2026-11-07　状态：已获用户认可（2026-11-07，含两份样机）
设计稿：`.superpowers/design/codegen-design.html`（页面样机 + 现有功能映射表）

## 1. 背景与意图

用户希望「开发工具」有一个类似 SaiAdmin（v6.saithink.top `#/tool/code`）的代码生成功能：
以**数据表**为中心的列表 → 一个「编辑生成信息」弹窗（配置信息 / 字段配置 / 关联配置）→
带文件页签和语法高亮的**预览代码** → 生成。

用户原话的要求：页面「要重新开发」，同时「要照顾之前已经有的功能，要融合进来」。范围：**全部做完**
（含批量生成、hasOne / belongsToMany、下载 zip）。

FunAdmin 现状：SaiAdmin 页面上的绝大部分配置项已经存在，只是分散在
「我的业务」列表、「可视化创建」「从数据库创建」向导、拖拽表单设计器（字段属性 + 列表配置 + 发布设置）、
「生成记录」页里。生成能力（正式生成、三方合并、冲突阻断、WAL 事务、生成记录与恢复、动态发布/运行时、
插件目标、Schema 版本与回滚）比 SaiAdmin 更完整。所以这次的本质是：**重做入口与交互，数据与生成链路沿用。**

## 2. 目标 / 非目标 / 反目标

**目标**
- 一个「代码生成」页面替代「我的业务 / 可视化创建 / 从数据库创建 / 生成记录」四个菜单入口。
- 列表、装载数据表（多选）、编辑生成信息（4 个页签）、预览代码（文件树 + 页签 + 高亮）、批量生成、同步表结构、下载 zip。
- 表单设计器页面改版（功能不变，见 4.8）。
- 关联配置支持 belongsTo、hasOne、hasMany、belongsToMany。
- 新增配置：表单宽度、表单全屏。

**非目标**
- 不另建 SaiAdmin 式的生成配置表（gen_table / gen_column）。
- 不提供 ThinkORM / EloquentORM 选择（FunAdmin 只用 Eloquent）。
- 不改 AI 开发模块；不改插件市场；不改动态发布的运行时实现。
- 不重写生成模板，只做本设计列出的增量（关联、表单宽度/全屏）。

**反目标（即使功能能用也算失败）**
- 表单设计器和代码生成页面看到/改到的不是同一份数据，或互相覆盖。
- 现有业务模块在**用户没有修改任何配置**的情况下，再次预览得到的生成文件或 schemaHash / definitionHash 发生变化
  （`BusinessModuleLayoutTest` 固定的哈希必须不变）。
  唯一例外是 4.4「关联生成的两处现有缺陷」的修复：只影响配置了 hasMany 或 belongsTo 关联的模块，且修复前生成的代码本身是错的。
- 丢失任何现有能力：版本历史/回滚、动态发布/运行时、生成记录/恢复/重试资源登记、插件目标、冲突保护、权限控制。
- 绕过现有冲突保护（例如提供「直接覆盖本地修改」的捷径）。

## 3. 方案对比（用户已选 A）

| 方案 | 做法 | 取舍 |
|---|---|---|
| **A. 直接编辑现有业务 Schema（已选）** | 字段表格、关联页签修改的就是表单设计器那份 Schema 文档，保存走现有 `schema/save`（带 expectedSchemaHash 冲突检测、自动生成版本） | 单一数据源，版本/回滚/冲突/设计器全部继续生效；代价是表格需要把嵌套在分组/栅格/标签页里的字段展平再写回 |
| B. 另建生成配置表，生成时转 Schema | SaiAdmin 做法 | 两份数据互相覆盖，版本回滚要重做，违背「融合」 |
| C. 后端字段 Patch 接口 | 前端只提交行改动，PHP 改写 Schema | 与设计器已有的前端 Schema 编辑重复，每加一个字段属性要前后端各改一次 |

## 4. 已确定的设计

### 4.1 入口与菜单
- 页面沿用组件路径 `development/business/mine`（菜单 `BusinessDevelopment`，权限 `development:business:modules`），
  内容整体替换为新页面；菜单名称改为「代码生成」（en-us：Code Generator）。
- 菜单 `BusinessVisual`、`BusinessDatabase`、`BusinessRecords` 隐藏（不删除，路由与权限保留）。
  - `/development/business/database` → 跳转到代码生成页并自动打开「装载数据表」弹窗。
  - `/development/business/records` → 跳转到代码生成页「生成记录」页签（保留 `moduleId` 等查询参数）。
  - `/development/business/visual` 仍是新建表向导页面（「新建表」按钮跳过去）；创建成功后照旧进入表单设计器（新表还没有字段，拖拽设计是主要方式）。
- **表单设计器完整保留**（用户明确要求）：路由 `development/business/designer`、页面与全部功能不变——
  基本信息 / 表单设计 / 按钮与工具 / 分类管理 四个页签，撤销、重做，编辑模式与桌面 / 平板 / 移动预览，控件面板、设计画布、表单大纲、属性面板、版本历史。
  代码生成页面只是多了几个进入它的入口，并与它共用同一份 Schema；设计器的「返回列表」回到代码生成页。
- 权限：页面本身要求 `development:business:modules`；页签、工具栏按钮、行操作各自按原有权限码独立控制显示。
  旧路由**只有在用户拥有 `development:business:modules` 时才跳转**到新页面；没有该权限但拥有原页面权限的角色，旧路由仍渲染原页面
  （`records.vue`、`database.vue`），不让任何角色因菜单合并失去已有的访问能力。（实施者决定）
- 已有安装需要一条新迁移完成菜单改名与隐藏（核心 SQL + PHP 两条链路各一条，编号按当时仓库最新顺延；
  2026-11-07 的提交 `4a938362` 刚把迁移整理成单一基线，新迁移是基线之后的第一条，动手前确认没有其他会话占用编号）。
  `CoreMenuPlan` 与前端 mock 种子同步更新。

### 4.2 页面 ① 代码生成列表
- 页签：「数据表」（默认）、「生成记录」（复用 `records.vue` 的内容，提取成组件）。页签状态反映在 URL 查询参数 `tab=records`。
- 搜索：关键词（表名称 / 表描述 / 业务标识）、生成目标（核心 / 插件）、状态。数据来自现有 `GET /modules`。
- 列：选择框、表名称（下方小字业务标识）、表描述、来源（数据库装载 / 可视化新建）、生成目标（核心 / 插件 code）、
  生成类型（单表 CRUD / 树表 CRUD，取 `list.tree.enabled`）、状态（沿用现有 lifecycle / generation / recovery 状态标签逻辑）、更新时间、操作。
- 工具栏：装载数据表、新建表、批量生成、删除（批量）。各按钮按现有权限码 `v-perm` 控制。
- 行操作：预览、编辑、设计（进入表单设计器，常驻显示）、同步、生成、更多（动态发布、打开运行时、版本历史、生成记录、删除）。
  - 「同步」只对**数据库装载**的模块可用；可视化新建的模块表结构由 Schema 生成，按钮禁用并提示原因。

### 4.3 页面 ② 装载数据表（弹窗）
- 选择数据源（现有 connections）→ 表格多选（现有 `GET /database/tables`），可搜索。
- 已装载的表、系统核心表置灰并说明原因（「已装载」/「系统核心表，不可装载」——后者以后端 `inspect` 拒绝为准，前端只做提示）。
- 选择生成目标（核心 / 插件，复用 `GET /targets`）。
- 确认后**前端逐张执行**现有 `inspect` → `from-database`（带 inspection hash），业务名称/标识按现有创建向导的默认规则推导。
  每张表一行结果（成功 / 失败原因），部分失败不回滚已成功的表。完成后刷新列表。

### 4.4 页面 ③ 编辑生成信息（大弹窗，可切换全屏）
弹窗右上角「版本历史」复用 `VersionHistoryDrawer`。底部：取消 / 保存 / 保存并预览。
保存 = 先 `schema/save`（若 Schema 有改动），成功后再 `publish-config`（若发布配置有改动）。两次写入不是原子的：
- `schema/save` 返回的 hash 冲突沿用设计器的冲突提示（`saveConflict` 逻辑），不静默覆盖，此时不再提交发布配置。
- 第一步成功、第二步失败：弹窗保持打开，提示「字段已保存，发布设置未保存」并保留未保存的发布配置改动可重试；「保存并预览」在任一步失败时都不进入预览。
- 不新增合并接口；`publish-config` 没有并发检测是现有行为，本次不改。（实施者决定）

**配置信息页签**（字段全部来自现有 publish_config / Schema，除标注「新增」外）
- 基础：表名称（只读）、表描述（Schema title）、业务标识（只读）、类名（只读，按标识推导）、数据源（只读）、备注。
- 生成：生成目标（**只读**展示，创建/装载时决定；设计稿里的可选单选框以本文为准。已生成模块改目标会牵涉文件路径、命名空间和冲突基线，不在本次范围）、生成类型（单表 / 树表；树表需选父级字段，写 `list.tree`）、
  接口前缀 `apiPrefix`、前端路由 `routePath`。
- 页面效果：表单效果 `formMode`（dialog / drawer）、**表单宽度 `formWidth`（新增）**、**表单全屏 `formFullscreen`（新增）**、左侧树筛选（`list.leftTree`，复杂配置跳设计器）。
- 功能开关：softDeletes、batchDelete、import、export、dataScopeEnabled(+dataScopeField)、memberApiEnabled(+memberApiOwnerField)。

**字段配置页签**
- 一行一个字段节点。列：拖拽排序、字段名称、字段描述（title）、物理类型（只读，database.columnType）、
  必填（validation required）、表单（!hidden）、列表（list.show）、查询（list.filter 非空）、排序（list.sort）、
  查询方式（list.filter 的操作符，可选值沿用设计器）、页面控件（type，沿用设计器 34 种控件目录）、
  数据字典 / 选项（dataSource，字典选择复用现有字典接口；复杂数据源跳设计器）、列宽（list.width）。
- 批量操作：全选列表、全选查询、「按字段名推荐控件」（新增，纯前端规则：如 `*_at`→datetime、`status`→radio+字典、`image|cover|avatar`→image、`content`→richtext；只填充用户确认的行）。
- 展平规则：只列出 `kind=field` 的节点；嵌套在 group/grid/tabs/collapse 里的字段照常列出，并显示所在分组名；
  repeatable / subform 容器显示为一行只读「子表（在设计器中编辑）」，其子字段不展开。
- 拖拽排序只在**同一父容器内**调整顺序；跨容器移动请用设计器。
- 写回必须是**最小修改**：只改用户动过的属性，未触碰的节点和属性保持原样（键顺序由后端 canonicalize 处理）。
- 「打开表单设计器」：有未保存改动时先提示保存。

**关联配置页签**
- belongsTo：来自字段节点的 `database.relation`（type=belongs_to）。在此页签新增 belongsTo 等同于把某字段的控件设为「关联选择」并填写 relation；两处编辑同一份数据。
- hasMany（子表单形式）：来自 repeatable/subform 节点，只读展示，点击跳设计器。
- **hasOne、belongsToMany、以及纯模型级 hasMany（新增）**：存放在 Schema 文档新增的顶层 `relations` 数组：
  `{ type: 'hasOne'|'hasMany'|'belongsToMany', name, table, localField, foreignField, pivot?: { table, localKey, foreignKey } }`。
  - 语义：`localField` 是本表字段（hasOne/hasMany 通常是主键），`foreignField` 是对方表字段（hasOne/hasMany 时是对方表里指向本表的外键）；
    belongsToMany 时 `localField`/`foreignField` 是两端被 pivot 引用的键，`pivot.localKey`/`pivot.foreignKey` 是中间表里的两列。
  - 它们**只生成模型关联方法**，不生成表单控件、列表列或选项来源，所以没有「显示字段」（设计稿关联表格中该列只对 belongsTo 有意义）。
    页签上注明「这些关联只影响正式生成的模型代码，动态发布与运行时不使用」。
  - 映射到 CrudDefinition：`field` = localField，`targetField` = foreignField（`DefinitionValidator` 要求 `field` 是本模块字段，满足）。
  - 只经过 Schema 的各层都要认识这个键：`FormSchemaValidator::TOP_LEVEL_KEYS`、`FormSchemaCompiler`（canonicalize 与 hash）、
    `FormSchemaMigrator`、OpenAPI FormSchema 定义、TS `FormSchemaDocument` 类型；设计器的导入、导出、替换和版本对比要原样保留它。
    动态发布与运行时忽略 `relations`，不因它阻止发布。（实施者决定）
  - 文档中没有 `relations` 或为空数组时，必须**不出现在 canonical 文档里**，保证现有 schemaHash 不变。
  - `FormSchemaValidator` 校验字段名/表名为合法标识符、name 不重复且不与字段名冲突、localField 存在、belongsToMany 必须有 pivot、非 belongsToMany 不能有 pivot。
  - `FormCrudDefinitionFactory` 把它们映射为 CrudDefinition `relations`（生成层 `DefinitionValidator` 与 `ProductionTemplateContext` 已支持这四种类型和 pivot 模型）。
- **关联生成的两处现有缺陷（本次一并修复）**：
  1. 参数顺序：`ProductionTemplateContext` 对 belongsTo/hasOne/hasMany 一律输出 `->{type}(Target, field, targetField)`。
     belongsTo 正确（外键在本表），但 Eloquent 的 `hasOne/hasMany(related, foreignKey, localKey)` 需要 `(Target, targetField, field)`，
     现有子表单 hasMany 生成的 `hasMany(T, 'id', 'goods_id')` 是反的。修复为按类型输出正确顺序。
  2. 目标类：模板总是输出 `\{当前模块 model 命名空间}\{Target}::class`，而该类从未生成（belongsTo 还带 `with => true` 和选项查询，列表页会因类不存在而报错）。
     修复：为每个关联目标表**在当前模块内生成一个轻量模型文件**（`.../model/relation/{Target}.php`，只含 `$table`、`$connection`、主键），
     关联方法与选项查询引用它的完整类名；目标就是本模块自身（自关联）时直接用本模块模型，不生成。
     这样不依赖目标表是否已是业务模块，系统表（如管理员表）也能关联。
     （修订：原先写的「目标表必须已是业务模块，否则报错」会让现有关联到系统表的模块无法生成，已放弃。）
  - 这两处修复会让**配置了 hasMany 或 belongsTo 的已有模块**再次预览时出现模型 / 服务文件的 update 和新增的 relation 模型文件，属于修复错误代码，列入反目标的例外；
    现有测试固定哈希的模块不含这两类关联（实施时复核）。（实施者决定，交付时向用户说明）

**菜单与权限页签**
- menuEnabled、parentId（菜单树选择）、menuName、icon、sortOrder（均为现有 publish_config）。
- 权限码只读展示：按现有 `PermissionCode::derive` 推导出的生成控制器各方法权限码。

### 4.5 页面 ④ 预览代码（全屏弹窗）
- 数据来自现有 `POST /modules/{id}/formal-generation/preview`。有 `development:business:generate` 权限时才返回文件内容（现有行为）；
  没有时只显示文件列表与状态，代码区提示「需要生成权限才能查看内容」。
- 左侧文件树按类别分组：后端（controller / service / model / validate / 会员端 API 控制器）、数据库（migration / permissions / lang SQL）、
  前端（index.vue / Form.vue / Detail.vue / api.ts）、语言包、测试。分组依据文件路径前缀。
- 每个文件用颜色点标状态：create / update / auto-merged / keep-local / delete / conflict 系列（沿用 `FILE_STATUS_META` 文案）。
- 右侧：文件页签 + 当前路径 + 视图切换「生成内容」（remoteContent，语法高亮）/「与本地对比」（复用 `GenerationFileDiff`）+ 复制。
- 冲突处理**完全沿用现有逻辑**：计划 blocked 时不能生成，冲突文件在「与本地对比」里查看，conflict-no-base 用现有「采纳已解决」接口。不新增覆盖捷径。
- 底部按钮：关闭、动态发布（走现有 publish preview/publish）、生成到项目（现有 formal-generation，需要 confirmToken）。
- 「下载 zip」（新增）：见 4.7。
- 高亮：引入 Shiki，使用细粒度按需加载（core + JavaScript 正则引擎，不用 wasm），语言 php、sql、typescript、vue、json，
  一个深色主题；代码区在亮色/暗色主题下都用深色背景。只在打开预览弹窗时动态 import，不进主包。
  单文件超过 524288 字节时后端本就不返回内容，前端显示「文件过大，未提供预览」。

### 4.6 批量生成
- 前端对选中的模块**逐个**调用 preview，汇总成一张确认表：模块、可否生成（blocked/冲突数/需要的权限）、文件数。
- 用户确认后对**不阻断**的模块逐个调用 formal-generation（每个模块用自己的 confirmToken），每行显示结果；
  阻断的模块跳过并给出「去预览处理冲突」入口。任何一个失败不影响其他模块，不做跨模块回滚（每个模块自身仍有 WAL 事务）。
- 不新增后端批量接口。

### 4.7 新增后端接口
1. **同步表结构预览**：`POST /admin/development/business/modules/{id}/schema/database-sync`
   - 仅数据库装载的模块；重新 inspect 表，返回与当前 Schema 的差异：
     `added`（新列 → 用与「从数据库创建」相同的服务端转换规则生成的字段节点）、
     `removed`（Schema 有、表里已没有的字段名）、`changed`（columnType / nullable / comment 变化，含旧值新值）、`inspectionHash`。
   - 不写库。前端展示差异，用户勾选后由前端合并进 Schema（新增节点追加到根节点末尾；删除按用户勾选移除节点；
     changed 只更新 database.* 物理属性，**不动**用户改过的 title、type、list、dataSource），再走普通 `schema/save`。
2. **下载 zip**：`POST /admin/development/business/modules/{id}/formal-generation/archive`
   - 需要 `development:business:generate` 权限（与预览返回内容同一门槛）。
   - 内容 = 本次 preview 的 remoteContent（即「将要生成的版本」），按项目相对路径放进 zip；不含 delete 状态文件；不写任何生成记录以外的状态。
   - 用 PHP `ZipArchive`（本机已启用 zip 扩展），响应 `application/zip`，文件名 `{业务标识}-{yyyymmddHHMMSS}.zip`。前端用 blob 下载。
- 两个新路由的权限码由 `PermissionCode::derive` 自动推导，需要随菜单迁移一起登记权限资源，并加入超级管理员可见范围；
  `tests/fixtures/auth-requirements.json` 等鉴权快照同步更新（只能新增这两条）。
- `PUBLISH_CONFIG_KEYS` 增加 `formWidth`（整数 px，360–1600）、`formFullscreen`（布尔）；
  CrudDefinition `features` 增加对应的**可选**属性；生成的列表页把它们传给 el-dialog / el-drawer 的 width / fullscreen。
  未设置时生成结果与现在**逐字节一致**。


### 4.8 表单设计器改版（用户追加，功能全部保留，页面重做）
样机：`.superpowers/design/designer-redesign.html`（用户已认可 ①–⑧ 全部内容）。

**总体**
- 顶栏一行：返回（回代码生成页）、表单名 + `key → 表名 · 生成目标`、撤销/重做图标（新增快捷键 ⌘Z / ⇧⌘Z，Windows 为 Ctrl）、
  编辑/桌面/平板/手机分段切换、保存状态点（绿=已保存、黄=未保存、红=保存暂停、灰=离线草稿）、
  「⚙ 表单设置」、「⋯」（动态发布〔插件目标不显示〕、版本历史、导入/导出 JSON、打开运行时）、「预览代码」（次按钮）、「生成」（唯一实心主按钮）。
- 页签：表单设计 / 按钮与工具 / 分类管理。**「基本信息」页签移除**，其内容由「⚙ 表单设置」打开代码生成的「编辑生成信息」弹窗承担（同一组件、同一份数据）；
  基本信息里有而编辑弹窗没有的项（来源 created/adopted、绑定表、新增页父级选择等）补进编辑弹窗的配置信息页签。
- 「生成正式模块」三步弹窗移除：「预览代码」「生成」打开 4.5 的预览代码弹窗；原第一步的发布设置并入「表单设置」。
- 保存冲突：顶栏下方整条横幅 +「核对版本」，核对对话框与三种处理方式不变。
- 窄屏：<1100px 右栏变为选中字段时弹出的抽屉；<760px 左栏也收成抽屉。

**表单设计页签**
- 三栏：左 248px（「控件 / 大纲」切换；控件为紧凑两列 + 搜索 + 分组计数，拖拽与点击追加都保留；
  大纲从抽屉改为常驻页签，可拖拽排序/改层级，点击选中画布字段，含「添加布局分组」）；
  中间画布占满剩余宽度（选中字段显示上移/下移/复制/删除浮动操作）；右 320px 属性面板（页签：表单 / 列表 / 数据库列 / 属性，
  校验、联动、数据源、原始 JSON 折叠在表单页签下方）。
- 预览模式收起左右栏；创建/编辑/只读/详情切换与「预览数据」移到页签行右侧，仅预览时显示。

**按钮与工具页签**
- 左栏按位置管理：顶部操作、行操作、分类顶部操作、分类节点操作，每个位置一个「继承默认 / 自定义 / 全部隐藏」分段切换
  （对应现有 `buttons[location]` 为 undefined / 数组 / 空数组），按钮可拖拽排序、添加；下方「通用工具」五个开关（refresh/search/columns/density/fullscreen）。
- 中间列表页实时预览：按钮画在真实位置，点击即选中，可拖拽排序；工具图标随开关亮灭；「未选中行 / 选中 2 行」切换以体现选择数量约束；
  示例行套用显示/禁用条件；分类按钮位置在启用分类后出现在左侧树区域。
- 右栏直接编辑选中按钮（取代抽屉），五个页签：基本 / 条件 / 交互 / 成功后 / 高级，承载原抽屉全部字段；未注册/无权限/目录不可用告警置顶；「模拟预览」保留。
- 「重新加载动作目录」改为页签行右侧「动作目录 vN · 刷新目录」，说明放提示气泡。

**分类管理页签**
- 三选一图卡：不分类 / 顶部选项页签（`list.category`）/ 左侧分类树（`list.leftTree`），明确互斥，只显示所选方式的设置。
- 左侧分类树：数据来源（当前业务 / 其他已发布业务 + 模块选择）、字段对应（来源主键、显示、父级、排序 → 本表右表关联字段）、
  选择行为（单选/多选、包含后代）、节点操作四个开关（并提示按钮外观在「按钮与工具」调整）。
- 右侧列表页实时预览（示例数据）。

**实现约束**
- 只改展示层：`useDesignerStore`、Schema 结构、保存/冲突/离线草稿/版本逻辑、`ListConfigurationPanel` 的数据语义不变；
  现有 designer 相关 vitest（controlPalette、structuredEditor、saveConflict、ListButtonEditor、ListConfigurationPanel、PropsPanel、VersionHistoryDrawer）
  若因结构调整需要改写，只能改选择器/结构，不能删掉其覆盖的行为断言。

## 5. 约束
- 前端：Vue 3 `<script setup>` + Element Plus + 现有 `DataTableShell` / `SearchForm` / `PageWrapper` 组件；文案一律 `t(key, 中文默认)`，补 zh-cn / en-us。
- 颜色遵循现有主题 token（主色 #5D87FF 体系、`html.dark`），按钮颜色不全灰（沿用 plain 按钮配方）。
- 新增 npm 依赖只有 `shiki`（锁定具体版本）。
- 后端：PHP 8.5、webman 注解路由、Eloquent；不修改冻结基线文件；迁移只向前。
- 不提交、不推送，除非用户要求；与并行会话（权限 / 迁移 / CMS）改动的文件冲突时先确认。

## 6. 测试
- vitest：Schema ↔ 字段表格展平/写回（嵌套分组、tabs、子表容器只读、未改动节点逐字节不变、同容器排序）；
  关联页签映射（belongsTo 写字段节点、其他写 `relations`）；同步差异合并（不覆盖用户改过的属性）；
  批量装载/批量生成的部分失败汇总；预览文件分组与状态；无生成权限时的降级；旧路由跳转。
- PHPUnit：`relations` 校验与 canonical（空数组不改变 hash）；Factory 映射四种关联与目标类解析；
  生成的模型里 hasOne/hasMany 参数顺序为 `(Target, 外键, 本地键)`、belongsTo 不变、每个关联目标都生成了 `model/relation/{Target}.php` 且引用其完整类名、自关联不生成；
  database-sync 差异（新增/删除/变化列，非数据库装载模块拒绝）；archive（权限、内容与 preview 一致、文件名）；
  formWidth/formFullscreen（未设置时现有 `BusinessModuleLayoutTest` 哈希不变，设置后模板输出正确）；菜单迁移可重复执行。
- 全量后端、前端测试与 `vue-tsc` 类型检查；用系统 Edge + Playwright 截图核对四个界面的亮色/暗色（登录只做一次，注意 5 次/15 分钟限流）。

## 7. 留给实施者的判断
- 组件拆分与文件组织（建议 `views/development/business/codegen/` 下按弹窗拆分）。
- 「按字段名推荐控件」的具体规则表。
- 树表 CRUD 只配置父级字段（现有 `list.tree.parentField`），**不新增** `list.tree` 键；名称显示沿用生成层/运行时已有约定。设计稿里「名称 name」一项以本文为准。
- 生成记录页签的组件提取方式。
