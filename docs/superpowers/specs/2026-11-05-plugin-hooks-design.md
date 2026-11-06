# 插件后端钩子与前端钩子设计

日期：2026-11-05

## 意图与已确认范围

用户已确认插件可只有钩子，无需应用、控制器或菜单；后端钩子支持函数、类方法和返回渲染结果，前端钩子支持函数和 Vue 组件。采用统一声明、两端分别执行、跟随插件启停的方式。

用户补充明确要求：任何位置都能调用，支持跨插件。PHP 普通服务、控制器、模板、CLI、队列与插件可使用同一后端接口；浏览器中的公共页面、会员页面、后台页面、普通组件及插件组件可使用同一前端接口。此文档已通过独立设计审阅，并由用户以“实施”指令确认。

## 全局调用与跨插件

两个 runtime 都与应用、菜单、路由目录解耦，由宿主初始化基础服务；不要求调用者是插件，也不要求调用者声明自己的菜单。相同运行环境内的核心业务和任意插件共用同一注册表。PHP runtime 不依赖 HTTP request；浏览器 runtime 放 `src/shared`，不依赖后台 Pinia 会话才能导入。前后端是不同进程，浏览器钩子与 PHP 钩子通过正常业务 API 协作，不会自动远程执行另一端处理器。

任何业务或插件都可自定义 point，无需预先写进核心白名单。推荐核心扩展点使用如 `upload.completed` 的领域名，插件自有扩展点使用 `plugin.{code}.{event}`；命名约定不限制其他插件订阅。Manifest 的 id 用于区分订阅贡献，并不限制可以调用的 point。

例如插件 A 调用 `Hook::filter('plugin.pricing.calculate', $amount, $context)`，插件 B、C 都可以订阅这个点。这里调用的是公开扩展协议；不会开放绕过注册、任意执行 B 的内部方法的入口。可选协作不强制依赖：没有监听器时 action 无操作，filter 原样返回；必须依赖 B 的业务通过已有 `requires.plugins` 声明，启用时验证。

同一调用链可嵌套跨插件调用；设置最大调度深度 32，超出后记录调用链并抛出明确异常，避免 A→B→A 无限递归。前端深度绑定调用上下文而非全局计数，互不相关的异步调用互不干扰。处理器内继续调用其他钩子时必须使用执行器提供的 `context.hooks.action/filter`，这个绑定接口继承调用链、signal 和清理作用域；PHP 对应为 `context['hooks']->action/filter`。全局 `Hook`/`hooks` 用于宿主发起新的根调用，不隐式传播浏览器异步上下文；文档和示例明确禁止用新的根调用绕过嵌套链的深度限制，平台不承诺能追踪故意另开的根调用。

## Approaches

1. 只使用现有 Webman Event 与前端 mitt：代码少，但缺少插件归属、过滤返回值、启停同步及组件插槽契约。
2. 统一 Manifest 声明、独立前后端执行器（采用，用户已认可方向）：复用生命周期和激活快照，增加动作、过滤与组件插槽。
3. 动态加载远程 JS / 任意后台脚本：需要另一个发布和运行体系，与当前源码随前端构建的机制不符。

## 约束

- 沿用 Manifest v2、新增可选字段；不含钩子字段的现有插件继续有效。
- 沿用当前统一源包目录和发布、归属、哈希校验、恢复机制。不改已有插件目录迁移决定。
- 不安装新依赖、不执行业务数据库迁移、不覆盖当前工作区其他修改。
- PHP 与 JS 都是可信插件代码；配置描述符只选取已验证插件文件中的具名导出，不接受客户端传入的文件、PHP 调用表达式或 JS 代码。
- 新前端源码必须经过现有前端构建。启停已在构建中的钩子不需要重新构建。
- 插件启用与权限边界保留；前端权限过滤只决定显示和交互，业务授权仍由后端接口执行。

## 声明与源码

`plugin.json` 新增可选数组 `serverHooks`、`clientHooks`。每条记录具有插件内唯一 `id`、`point`、`type`、整数 `priority`（默认 100）。相同类型与扩展点按 priority 升序、plugin code、id 的 ASCII 字符顺序排序，两端不依赖浏览器语言排序。point 使用点分标识；未被宿主调用的扩展点只是未触发，不自动产生事件。

后端条目：`type` 为 `action` 或 `filter`，`file` 为插件私有目录 `hooks/` 下 PHP 相对路径，`handler` 为该文件返回的映射中的键。例如：

```json
{
  "serverHooks": [
    {"id":"upload-audit","point":"upload.completed","type":"action","file":"hooks/upload.php","handler":"afterUpload","priority":100}
  ],
  "clientHooks": [
    {"id":"dashboard-widget","point":"dashboard.cards","type":"component","component":"HookStats","priority":100},
    {"id":"table-actions","point":"table.actions","type":"filter","file":"hooks.ts","export":"extendActions","priority":100}
  ]
}
```

`hooks/upload.php` 返回如 `['afterUpload' => callable]` 的映射。callable 可为闭包、已加载的命名空间函数、类方法或可调用对象；插件负责在该文件中定义/加载自己的处理器。不从 JSON 直接解析任意 PHP 函数。文件每进程只加载一次；每次调用重新检查启用状态；部署 PHP 新版本后沿用 Webman 进程 reload 流程。

前端函数条目 `type` 为 `action` 或 `filter`，`file` 指向 `adminWeb.source` 内 `.ts` / `.js` 模块，`export` 指定具名函数导出。组件条目 `type=component`、`component` 引用现有 `adminWeb.components` 键。`adminWeb.menu`、`adminWeb.routes` 可为空，只有前端函数的插件允许空 components；存在组件/路由引用时必须校验对应组件。`adminWeb` 沿用现有前端源码发布字段名，不限制共享钩子仅在管理页面可用。

前端条目新增 `audiences` 数组，可为 `public`、`member`、`admin`，缺省为 `["admin"]`，用于选择当前页面会话可获得的贡献，与应用是否存在无关。一个处理器可同时声明多个 audience。可选 permission 仅用于 admin 项，并只能引用本插件声明权限或现有允许的核心权限；声明 public 的贡献不携带私有上下文。会员业务的访问权限仍由会员 API 校验，本次不另造会员权限字符串体系。

audience 按当前页面路由决定，采用精确匹配、不自动继承：`meta.public=true` 的公共页（包括公开登录/注册页）使用 public；其余 `meta.member=true` 的会员页使用 member；后台受保护页面使用 admin。已经登录的会员访问公共首页仍为 public，后台 Cookie 不会让会员页获得 admin 贡献。希望三类页面共用的贡献显式声明三个值。带 permission 的项只允许 audiences=["admin"]，需要公共版本时声明独立无私有权限的贡献。

只有后端钩子的插件无需 `adminWeb`、`app/{code}` 或 `app/admin`。后端 hooks 文件保留在私有插件目录中，安装、ZIP 打包、更新和恢复均覆盖这些文件；不会发布到 public，也不会制造应用目录用于通过激活检查。前端文件按现有发布规则进入 `src/admin/modules/{code}`。

Manifest 校验器检查字段、id 重复、类型组合、引用、文件扩展名、路径归属与符号链接，不执行处理器源码。只有 runtime 执行器在通过激活检查后才加载代码。

## 后端执行契约

提供 `Hook::action(string $point, mixed $payload = null, array $context = []): void` 与 `Hook::filter(string $point, mixed $value, array $context = []): mixed`；实例执行器允许注入 reader 和 reporter 便于测试。action 调用参数 `(payload, context)`，忽略返回值；filter 调用参数 `(value, context)`，每次返回值传入下一个处理器，包含 null、false、0 和空集合。没有处理器时 action 无操作、filter 原样返回。

context 为宿主明确提供的数据与插件归属信息，不隐式暴露整份配置或请求。当前调用的 pluginCode、point 和 id 由执行器填充，不允许调用方覆盖。本次不定义对外可调用任意钩子的 HTTP 接口。

新增插件级激活断言，与依赖、状态、needs_reinstall、operation_token 共用判断；原应用级 gate 继续检查应用范围。每次调度从同一个 reader 快照确定当前处理器列表，钩子声明纳入已有 activation_hash。禁用/更新后的后续调度不再选择旧处理器；已开始执行的 PHP 调用不强行中断。损坏或不可信快照不执行插件代码，action 记录问题后无操作，filter 抛出明确运行时异常。

处理器运行异常：action 记录插件、point、id 和异常并继续后续处理器；filter 记录后抛出异常，停止链条，防止静默使用不完整业务数据。优先级只控制顺序，不暗示事务或队列；需要异步时由处理器投递任务，提交后事件由宿主在提交后调用。

返回 HTML 属于普通 filter 返回值，可由 PHP 宿主渲染；不会把任意 HTML 注入 Vue。动作钩子不提供业务拦截语义；需要阻断业务的扩展使用可抛异常的 filter。

## 前端执行与组件插槽

共享 runtime 提供 `hooks.action(point, payload?, context?): Promise<void>`、`hooks.filter<T>(point, value, context?): Promise<T>`，顺序与后端一致并支持异步函数。action 返回值忽略、记录错误后继续；filter 保留所有显式返回值并在失败时 reject。异步导入或处理器完成时重新检查注册版本，失效 action 不启动后续处理器、失效 filter reject 专用取消异常，调用处丢弃过期结果。

函数上下文提供 pluginCode、point、id、AbortSignal 和 `onDispose(cleanup)`。调用者可传宿主 scope signal，处理器收到由页面调用作用域与插件注册版本组合的 signal。页面卸载/keep-alive 停用、禁用、更新、会话切换或登出任一发生时，相应清理回调执行一次、signal abort；异步完成后才注册到已经结束作用域的 cleanup 立即执行。插件用它注销监听器、停止定时器、撤销事件绑定。运行时无法强制回滚第三方已经产生的副作用，这一限制写进开发文档。

`<PluginSlot name="dashboard.cards" :context="{ user }" />` 按顺序异步加载已注册组件，将宿主 context 作为 prop 传入。每个插件组件独立错误边界；加载或渲染失败仅移除该贡献并记录错误。禁用、权限减少或登出同步移除对应组件，执行组件自身卸载和 runtime cleanup。插槽自身不分配卡片尺寸，组件负责自己的展示。

前端管理页面模块清单沿用已登录会话下 enabled-modules 入口，增加 clientHooks、generation 字段，纯函数插件也必须包含在响应中。新增只读描述接口：公共页/会员页使用 `/plugins/hooks/frontend`，后台使用 `/admin/plugins/hooks/frontend` 以适配既有 Cookie 的 Path=/admin；仅接受固定 audience 值。member 走现有会员认证，admin 走现有后台认证，匿名只获得显式 public 项。接口只返回描述，不触发处理器。会话切换先清理旧会话贡献，再同步当前 audience；同浏览器任一时刻以当前页面 audience 和会话身份确定贡献，页面组件可在自己的会话上下文调用任意 point。

后端只返回允许该用户使用的贡献和必需引用，不下发后端处理器路径。只从 Vite 构建期 modules 白名单加载文件，不执行 URL import。后端响应损坏、请求失败或引用缺失时清理当前插件贡献，宿主页仍可使用；去重版本为 activation generation + 当前 audience/会话 + 规范化可见描述符哈希。相同 generation 但权限或描述符变化仍必须重新同步和卸载。

同步触发：登录初始化、当前页面完成启停操作之后、窗口重新获得焦点、可见页面每 30 秒。同一页面内同步请求去重且只应用最新请求结果。来自其他浏览器/进程的禁用在下一次同步移除前端贡献，不承诺瞬时推送；服务端权限/启用检查立即约束后续 API。登出清空函数、组件、定时器与待完成导入。

## 首批真实接入点与验收样例

- PHP `upload.completed`：每次成功逻辑附件上传触发一次，包括复用已有附件，payload 为整理的附件元数据和 `created`/`reused` 标记。普通上传与分块合并最终形成附件时在 attach 统一边界触发；未完成的分块不触发。旧事件保持原有行为，新扩展点不依赖旧 `afterUploadFile` 的 halt 返回值。action 失败不改变已成功上传的响应。
- PHP `upload.result`：本次仅接入 `AdminUpload::upload()` 普通上传最终返回数据，宿主保护已有所有核心键，仅允许添加 `extensions` 对象中的插件字段。filter 返回结构不合法或修改核心值时抛出异常，由宿主返回明确处理失败，不删除已成功持久化的附件。分块尚未完成的响应和其他上传适配器不改写；开发文档说明其他宿主可以自行选择接入点。
- Vue `dashboard.cards`：现有仪表盘卡片区域之后增加插槽。
- Vue `dashboard.loaded` action、`dashboard.quickActions` filter：接入现有仪表盘挂载/激活和 `shortcuts` 快捷入口列表。以页面作用域 signal 防止 keep-alive 激活/离开和重复同步产生重复监听；过滤结果经宿主校验为内部路由条目，点击仍走现有路由权限检查。
- 跨插件演示：A 调用 `plugin.demo.transform`，B 提供 PHP/JS filter，B 禁用后结果回落原值；B 的函数与组件可通过 audiences 在公共页和会员页使用，初次接入不必为所有核心页面放插槽。
- 提供一个无 app、无菜单、无路由的测试/示例插件，包含 backend hooks、frontend hooks.ts 与一个卡片组件；证明代码可发现、安装启用、触发、禁用失效和重新启用。PHP 渲染返回字符串和 JS 函数通过测试与文档示例覆盖，不额外创建演示业务数据库。

## 验证

PHP 测试覆盖 Manifest 原有插件与纯钩子插件、路径与重复声明拒绝、callable 各种形式、动作顺序与错误隔离、filter 链及 false/null、禁用/依赖/坏快照、跨两 reader 的启停同步、处理器加载前 gate、CLI 无 request 的跨插件调用、可选/强依赖、递归深度限制、更新后 reload 约定和上传真实接入。

前端测试覆盖函数与组件分发、跨插件与嵌套调用、异步顺序、同 generation 权限投影变化、页面 scope 与注册 scope 联合清理及迟到 cleanup、禁用/登出清理、延迟导入与旧请求不能恢复已禁用贡献、匿名/会员/后台贡献隔离与切换、函数独占插件、组件错误边界、焦点/周期同步、插槽上下文和仪表盘接入。跑相关现有插件测试、类型检查及前端构建；全量既有失败与本次失败分开记录。

## 实施边界

本次不接管现有 Webman Event 全部事件，不支持后台输入 PHP/JS 代码，不做通用支付/存储驱动框架、不新增任意远程插件执行 API、不新增跨浏览器实时推送。新增匿名读取描述接口仅用于明确 public 的前端贡献。其他业务与插件随时通过显式调用 action/filter 或放置 PluginSlot 接入同一机制。具体文件拆分、测试夹具命名与日志实现交由实现阶段依照现有代码决定。

## 实施核对

- 既有站点尚未初始化插件激活目录时，全局默认入口允许可信空注册表，保持无插件场景的 filter 原值返回；只要目录已存在，active 缺失或损坏仍拒绝执行。显式注入 reader 默认采用严格策略。
- 存储声明保持 Manifest 的可选语义，安装/更新仅在声明 storage 时创建私有存储，不要求纯钩子插件提供空目录。
- 通用 PluginSlot 在 keep-alive 停用时清理并取消导入，重新激活创建新作用域；宿主上下文与 signal 替换会更新组件并释放旧作用域。
- 无应用示例已经过真实安装、前端源码发布、启用、跨插件调用、禁用、更新和卸载测试；ZIP 包含私有 hooks 与前端源码，未发布到 public。
