# 代码生成追加：新建表弹窗 · 属性面板 · 自定义动作

日期：2026-11-08　状态：用户已认可（「按建议做」）
样机：`.superpowers/design/followup-design.html`　前序设计：`2026-11-07-code-generator-design.md`

## 用户决定
1. 「可视化创建」独立页面放弃，改为代码生成页「新建表」弹窗（样机 ①）。
2. 表单设计器右侧属性面板按样机 ② 重做。
3. 按钮动作扩展采用方案 A：设计器里新建自定义动作，正式生成时一并生成处理代码。
4. 正式生成仍使用**已发布**的 Schema（不改后端规则，界面已有提示）。（我的判断：用户未要求改变，按「沿用现有生成链路」处理。）
5. 用现有 `fun_order` 模块完整走一遍「编辑 → 动态发布 → 预览 → 生成 → 打开页面 → 手改后再生成被阻止」。

## A. 新建表弹窗
- 代码生成页工具栏「新建表」打开弹窗，不再跳转页面。字段：业务名称（必填）、业务标识（必填，按名称建议，规则同现有 `normalizeBusinessCode`）、生成目标（核心 / 插件两张卡片，插件时选择插件，沿用 `useBusinessTarget`）、数据库连接（下拉；没有连接列表接口时沿用当前默认值 + 可编辑，见实施者判断）、数据表名（默认只读显示「前缀 + 标识」，点击可改）、表格类型（普通 / 树形分段）。
- 去掉：创建方式（使用已有表）、备注。
- 提交沿用 `createVisual` 接口与现有 payload；成功后进入表单设计器（同现在）。
- 旧路由 `/development/business/visual`：有 `development:business:modules` 时跳到代码生成页并 `?create=1` 打开弹窗；否则仍渲染旧页面（与其他旧入口一致，复用 `useLegacyCodegenRedirect`）。

## B. 属性面板
- 只改展示：标签在上、输入在下；分组「基础（显示名称、控件类型、分组、占位提示）/ 布局（宽度：1/4·1/3·1/2·整行 = 6/8/12/24，非这四值时显示「自定义 N 列」并可在属性页签精确输入）/ 状态（必填、显示、只读三个切换块一行）」。
- 头部：字段标题 + 控件类型标签 + `field · 列类型 · 位置`。
- 折叠区：校验规则 / 联动条件直接写出规则摘要（如「必填 · 最大 32 字符」），数据源、原始 JSON 同现在。
- 「编辑禁改」改名「只读」，含义不变。列表 / 数据库列 / 属性页签同样改为上下排列。
- 修掉输入框被截到面板外的问题；适配 320px 宽和窄屏抽屉。

## C. 自定义动作（方案 A）
**目标**：下拉里没有的动作，可在设计器里新建；正式生成后得到一个可编辑的处理方法和已登记权限，按钮自动绑定。安全模型不变：仍走现有「注册动作」执行链（权限、二次确认、参数类型校验、幂等、审计）。

- **定义存放**：Schema `list.customActions: Array<{ key, label, locations: ('row'|'toolbar')[], batch: boolean, requiresConfirmation: boolean, effect: 'write'|'read', parameters: Array<{ name, type: 'string'|'integer'|'number'|'boolean' }> }>`。
  key `^[a-z][a-z0-9_]{0,40}$` 唯一；label 1–50 字；locations 非空；batch 仅 toolbar；parameters ≤ 5、name 合法标识符且唯一。缺省或空数组不进入 canonical 文档（现有哈希不变）。
- **注册键**：`generated.{entity}.{key}`；**权限码**：`generated:{entity}:action-{key 转 kebab}`（obj/act 与现有生成权限行一致的推导方式，由实施者对齐）。
- **生成文件**（仅核心目标；插件目标的模块在 UI 中不提供新建自定义动作，并说明原因）：
  - `app/admin/generated/{dir}/action/{Class}Actions.php`：每个动作一个 public 方法桩 `public function {camelKey}(array $parameters, array $context): array`，默认 `throw new \RuntimeException('未实现：{label}')` 或返回提示信息（实施者选一种，需让未实现时调用方得到明确错误）。作为普通生成文件参与三方合并，用户在里面写的逻辑再次生成不被覆盖。
  - `app/admin/generated/{dir}/config/actions.php`：返回注册定义数组（handler 指向上面类的方法、permission、locations、targets：row→record、toolbar 且 batch→selection、toolbar 非 batch→none、parameters/parameterTypes、effect、requiresConfirmation、resultContract json）。
  - 生成的权限迁移 SQL 增加每个动作的权限行（与其他生成权限同父级、同 source）。
- **注册加载**：`FormRegistryFactory::actions()` 合并 `config/form.php` 的 actions 与所有 `app/admin/generated/*/config/actions.php`；键冲突报错。
- **设计器**：按钮检查器「动作」下拉增加「自定义动作」分组（列出本模块 customActions，尚未生成的标「待生成」）和「＋ 新建自定义动作」入口（小表单：名称、标识、位置、批量、二次确认、读/写、参数）。选中后按钮绑定 `{ type: 'registered', key: 'generated.{entity}.{key}' }`，参数绑定沿用现有参数绑定 UI。删除自定义动作时提示并一并移除绑定它的按钮。`useButtonDraft` 校验对「待生成」的自定义动作使用 Schema 里的定义作为元数据。
- **生成前的运行时**：动态发布的运行时页面上，未生成的自定义动作按钮显示为禁用并提示「正式生成后可用」（不报错、不隐藏）。

## 约束（沿用前序）
现有模块未改配置时生成结果与哈希不变；冲突保护不放宽；文案 `t()` + zh-CN/en-US；主题 token + 暗色；不提交。
