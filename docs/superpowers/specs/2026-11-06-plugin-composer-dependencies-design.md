# 插件 Composer 依赖支持设计

日期：2026-11-06　状态：已按用户“实现呀”指示执行

## 背景与目的

插件可能需要第三方 composer 包。现状：

- `plugin.schema.json` 的 `requires` 为 `additionalProperties: false`，只允许 `php` / `funadmin` / `plugins`，无法声明 composer 依赖。
- 安装流程（`PluginPackageService`）不执行 composer。
- 唯一支持：`PluginEntryFactory::loadVendorAutoload()` 在创建插件入口对象时 `require_once plugins/{code}/vendor/autoload.php`。
- 缺陷 1：只有生命周期操作、`get_plugin_instance()`、`PluginFormComponentRegistry` 会触发该加载。已发布的控制器（`app/admin/plugins/{code}/...`，走宿主 PSR-4）和 `HookRuntime` 直接 `include` 的 serverHooks 都不会触发，worker 重启后插件运行时代码引用 vendor 类会 `Class not found`。
- 缺陷 2：Composer 生成的 autoloader 以 `register(true)` 前置注册，插件 vendor 中与宿主同名的包会在整个进程内覆盖宿主版本。

目标：让插件依赖“能稳定加载、不污染宿主、冲突可见可控、宿主侧依赖可声明可校验”。

## 目标

1. **A｜自带 vendor**：插件可在包内携带 `vendor/`，任意运行时入口（控制器、钩子、入口对象）都能加载到其依赖。
2. **A｜宿主优先**：插件 vendor 的 ClassLoader 追加注册，宿主已有的类始终用宿主版本。
3. **A｜冲突检测**：安装/更新/启用/重部署时比对插件 vendor 与宿主已安装包，发现冲突时提示管理员，由管理员选择继续或取消。
4. **B｜宿主依赖声明**：`plugin.json` 的 `requires.composer` 声明需要宿主提供的包与版本约束；不满足则拒绝，并给出可执行的 `composer require` 命令。

## 非目标

- 宿主不执行 `composer install/require`（安全、耗时、需重启 worker、失败难回滚）。
- 宿主不内置或自动运行 php-scoper；仅在文档中推荐插件作者自行使用。
- 不引入 `composer/semver`；版本约束语义沿用 `DependencyValidator::matches()`（支持 `^`、`>=`、`>`、`<=`、`<`、`=`，逗号/空格表示与；不支持 `~`、`||`）。
- 不支持 `requires.composer` 中声明 `php`、`ext-*`（PHP 版本已有 `requires.php`）。
- 运行中禁用插件后不卸载已注册的 autoloader（PHP 无法安全卸载已加载类），沿用“部署后需重载 worker”的现有约定。

## 反目标（技术上能跑但算失败）

- 插件 vendor 中的包替换了宿主正在用的类（如 guzzle、psr/*、illuminate/*）。
- 冲突在安装时静默通过，管理员事后才发现插件异常。
- 宿主侧依赖缺失导致禁用、卸载也失败，插件无法被移除。

## 方案对比

确认冲突的交互方式考虑过三种：

| 方案 | 说明 | 取舍 |
|---|---|---|
| **1. 服务端拒绝一次，前端确认后带标记重发（采用）** | 首次请求检测到冲突且未带 `acceptComposerConflicts=1` 时抛专用异常并返回冲突列表；前端统一拦截、弹窗，确认后带标记重发 | 后端一处校验，前端一个公共处理函数；暂存目录异常时已有清理。代价：云端安装确认后重新下载一次包，本地上传重传一次（文件仍在前端内存） |
| 2. 独立预检接口 | 先调预检再安装 | 云包要先下载、本地包要先上传，做两遍；预检与安装之间存在 TOCTOU；每个入口都要配预检接口 |
| 3. 不拦截，装完标记 | 安装后在详情中显示冲突 | 管理员无法拒绝，不符合需求 |

隔离程度考虑过“宿主自动 php-scoper”与“宿主包优先 + 冲突检测 + 文档推荐 scoper”，采用后者：scoper 需逐包配置，字符串类名、反射、动态加载在加前缀后易失效，宿主自动执行出问题时插件作者难以排查，且宿主需维护 scoper 工具链。

## 已决定的设计

### 1. 统一的插件 vendor 加载器（A）

新增 `app\common\plugin\sdk\PluginVendorAutoloader`（名称可由实现者调整），进程内幂等：

- `ensure(string $pluginsPath, string $code): void`
  - 校验 `$code` 格式；文件为 `{pluginsPath}/{code}/vendor/autoload.php`，不存在则直接返回。
  - 已加载过（按真实路径记录）则返回。
  - `require` 该文件，取得返回的 `Composer\Autoload\ClassLoader`；执行 `unregister()` 后 `register(false)`，使其追加到 autoload 队列末尾。若返回值不是 ClassLoader，记录日志并跳过（不让整个 worker 失败）。
  - 说明：`autoload_files`（函数文件）在 `require` 时立即执行，无法“宿主优先”；同一包的 files 由 Composer 的 `$GLOBALS['__composer_autoload_files']` 按标识去重。这部分风险由冲突检测覆盖。
- 调用点：
  1. **worker 启动**：`app\common\plugin\sdk\Service::register()`（已在 `config/bootstrap.php` 中注册）读取激活快照，对所有已启用插件调用 `ensure()`；快照不可用时跳过，不阻断启动。
  2. **`PluginApplicationGuard`**：通过 `ActivationGate` 校验启用成功后、`$next()` 之前调用 `ensure()`，覆盖运行期间新启用的插件。
  3. **`HookRuntime`**：首次 `include` 某插件 hook 文件前调用 `ensure()`。
  4. **`PluginEntryFactory`**：`loadVendorAutoload()` 改为委托 `ensure()`，去掉自身的静态缓存。

### 2. 冲突检测（A）

新增 `app\common\plugin\sdk\ComposerConflictDetector`（名称可调整）：

- 输入：插件目录。读取 `{plugin}/vendor/composer/installed.json`（兼容 Composer 2 的 `{"packages": [...]}` 与 Composer 1 的数组格式）；文件不存在视为无自带依赖。
- 与宿主比对：“宿主”仅指宿主自身的 `vendor/`。**不得使用全局 `Composer\InstalledVersions`**——Composer 2 会聚合所有已注册 ClassLoader 的 vendor 目录，插件 vendor 被 `ensure()` 加载后也会出现在其中，导致插件与自己旧版本冲突、或被其他插件的 vendor 误判满足。实现上直接读取宿主 `vendor/composer/installed.php`（路径可注入，便于测试），封装为 `HostPackageRepository`（名称可调整），供冲突检测与 B 校验共用。（实现者决定；依据：审阅发现）
- 判定为冲突的情况：
  - 两者均为稳定版本，且“主版本不同”；当主版本为 0 时比较“主.次”（与 `^` 语义一致）。
  - 任一方为 dev/分支版本（无法可靠比较），按冲突处理并在原因中注明。
  - 宿主版本与插件版本兼容（同主版本）不报冲突——运行时用宿主版本，插件作者应保证向前兼容。
- 输出：冲突列表 `[{package, pluginVersion, hostVersion, reason}]`，按包名排序。
- **插件间比对**（用户决定）：除宿主外，同时与“其他已安装且已启用插件”的 `vendor/composer/installed.json` 比对（排除被检测插件自身），判定规则与宿主相同。冲突条目增加 `source` 字段：`host` 或 `plugin:{code}`，`hostVersion` 字段相应改名为 `otherVersion`（实现者可调整命名，但前端需能展示“与谁冲突”）。
- 插件自带的、宿主和其他启用插件都没有的包不算冲突。
- 宿主侧冲突的含义：运行时将使用宿主版本；插件间冲突的含义：运行时使用先加载者（启动时按插件代码字母序加载）。弹窗文案需分别说明。

### 3. 宿主依赖声明与校验（B）

- Schema：`requires` 增加可选 `composer`：
  ```json
  "composer": {"type": "object", "propertyNames": {"pattern": "^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$"}, "additionalProperties": {"type": "string", "minLength": 1}}
  ```
  示例：`"requires": {"plugins": {}, "composer": {"overtrue/pinyin": "^5.0"}}`
- `Manifest` 增加 `composerRequirements(): array<string,string>`。
- `DependencyValidator` 增加 `assertComposerSatisfied(Manifest $manifest)`：
  - 宿主未安装 → 拒绝：`宿主缺少 composer 依赖 overtrue/pinyin（需要 ^5.0），请在项目根目录执行：composer require "overtrue/pinyin:^5.0"，然后重启服务`。
  - 已安装但版本不满足 → 拒绝，信息同上并附当前版本。
  - 版本取自 `HostPackageRepository`（宿主 `installed.php` 的 `pretty_version`），去掉前缀 `v` 后用 `matches()` 比较；dev 版本视为不满足。
  - 多个缺失项一次性列出，合并成一条可复制的 `composer require "a:^1" "b:^2"` 命令。
  - 宿主版本信息来源通过 `HostPackageRepository` 注入，便于测试。
- **执行时机**：仅在 安装、更新（本地/云端）、启用、历史版本重部署 时执行硬校验。**禁用、卸载、清除数据不执行**，避免宿主依赖缺失时插件无法移除。当前 `PluginServiceSupport::validatedManifest()` 被 `plugin()` 在所有生命周期中复用，实现时需区分调用场景（例如增加参数或拆分方法）。
- `plugin:validate` 命令：B 不满足时报错（与现有依赖校验一致）。

### 4. 冲突确认流程（A，方案 1）

- 新增异常 `ComposerConflictException`，携带冲突列表。**它属于前置条件类错误，不是生命周期失败**（我的决定）：
  - 不写 `last_error`、不调用 `recordFailure()`（否则首次未确认的启用会让带确认的重试被“最近一次生命周期操作失败”拦下）。实现上让它成为 `PluginPreconditionException` 的子类（需去掉该类的 `final`），或在 `PluginService::operate()` 中同样豁免。
  - 不在版本历史中写 `status=failed` 记录；`PluginPackagePipeline` 的失败分支需对该异常跳过 `recordHistory()`，并原样重抛（不得被包装成普通 `RuntimeException`，否则控制器无法识别）。
  - 检测点放在暂存解压与 Manifest 校验之后、`deploy()` 之前（可利用现有 `deployGuard` 位置或在其前新增），保证此时没有任何部署副作用。
- 执行时机：与 B 相同的入口（安装、本地更新、云端更新、启用、重部署），在包已解压到暂存目录、Manifest 校验通过之后、写入正式目录 / 改变生命周期状态之前。
- 若存在冲突且请求未带 `acceptComposerConflicts=true`：抛 `ComposerConflictException`。现有失败路径负责清理暂存目录，不留下半装状态。
- 带标记时：继续执行，并在插件操作记录（operation 记录）中写入一条警告，包含冲突列表与操作人，便于追溯。
- **确认结果持久化**（用户决定）：
  - `plugin` 表新增字段 `composer_conflict_ack`（varchar(64)，默认空串），保存已确认冲突清单的指纹：对规范化（按 package+source 排序、仅含 package/source/pluginVersion/otherVersion）的冲突列表做 `sha256`。通过新迁移文件添加，遵循 `database/migrations` 现有编号规则；`PluginServiceSupport::assertLifecycleSchema()` 等列校验如有需要同步更新。
  - 检测时：冲突列表为空 → 放行；指纹等于已保存的 `composer_conflict_ack` → 放行，不弹窗；否则需 `acceptComposerConflicts=true` 才放行，放行后写入新指纹。
  - 冲突变化（宿主升级包、其他插件变更、插件自身更新）会产生新指纹，从而再次提示。
  - 卸载时清空该字段。
- 同一请求内的确认需贯穿整条流水线（我的决定）：更新/重部署内部若再次经过启用或 `validatedManifest()`，必须沿用同一个 `acceptComposerConflicts`，不得在已部署后再次抛出导致回滚。
- `SystemPlugin::execute()` 在通用 `RuntimeException` 分支之前捕获该异常，返回：
  - HTTP/业务码 `409`，`msg`：`插件自带依赖与宿主存在版本冲突`
  - `data`：`{"reason": "composer_conflict", "conflicts": [...]}`
- 控制器的 7 个入口（`installLocal`、`installDiscovered`、`installCloud`、`updateLocal`、`update`、`enable`、`redeployHistory`）读取 `acceptComposerConflicts`（使用已有 `boolean()` 辅助方法）并传入服务层。
- CLI `plugin:validate`：只输出冲突警告，不中断、不影响退出码。

### 5. 前端

- `web/src/admin/api/system/plugin.ts`：上述 7 个接口增加可选参数 `acceptComposerConflicts`（上传类接口放 FormData，其余放请求体）。
- 新增一个公共函数（如 `withComposerConflictConfirm(run: (accept: boolean) => Promise<T>)`）：
  - 先以 `false` 调用；若错误为 409 且 `data.reason === 'composer_conflict'`，弹出 `ElMessageBox.confirm`，以表格/列表展示 `包名 / 插件内版本 / 宿主版本 / 原因`，并说明“继续后运行时将使用宿主版本，插件可能工作异常”。
  - 确认则以 `true` 重发；取消则静默结束，不再弹通用错误提示。
- `index.vue`、`InstallDialog.vue`、`PluginHistoryDrawer.vue` 中对应操作改为经由该函数调用。
- 文案走现有 i18n `t()` 机制，提供中文默认值。

### 6. 文档

更新 `docs/plugins.md`，新增“Composer 依赖”章节：

- 两种方式的选择建议：宿主已有/常见的包用 `requires.composer`；宿主没有的包随插件 vendor 分发。
- 打包规范：`composer install --no-dev --optimize-autoloader`；不要使用 path 仓库（会产生符号链接，安装被拒）；注意 100MB/500MB 体积上限。
- 宿主优先规则与冲突提示的含义；推荐对可能冲突的依赖使用 php-scoper，并给出最小示例。
- 插件内代码引用 vendor 类无需手动 require autoload。
- 同步更新 `docs/examples/plugin-hooks/*` 中如有需要的示例（可选）。

### 7. 测试

- `PluginVendorAutoloader`：幂等；ClassLoader 被追加注册（宿主已有类不被覆盖）；不存在 vendor 时无副作用；非法 code 拒绝。
- `ComposerConflictDetector`：Composer 1/2 两种 installed.json；主版本不同、0.x 次版本不同、dev 版本、同主版本、宿主未安装 五类情况。
- `DependencyValidator::assertComposerSatisfied`：缺失、版本不满足、满足、多项合并命令；禁用/卸载路径不触发。
- Schema：`requires.composer` 合法/非法包名。
- 控制器 HTTP：冲突时返回 409 + `reason`；带 `acceptComposerConflicts` 时成功并写入警告记录；暂存目录被清理；未确认的冲突不写 `last_error`、不写失败历史，带确认重试可成功。
- 确认持久化：安装时确认后启用不再弹窗；冲突集合变化后再次弹窗；插件间冲突（两个插件自带同包不同主版本）能被检出。
- 宿主判定：插件 vendor 已加载后再检测，不会把插件自身旧版本或其他插件的包当作宿主包。
- 运行时：worker 未调用过入口工厂时，插件控制器/钩子仍能加载 vendor 类（可沿用现有 `PluginCenterHttpTest`、`PluginHookRuntimeTest` 的夹具方式）。
- 前端：公共确认函数的确认/取消两条路径（如项目已有前端测试框架）。

## 留给实现者决定

- 新类的具体命名与所在命名空间（建议放 `app\common\plugin\sdk`）。
- 冲突警告写入操作记录的具体字段/格式，前提是可在插件“操作记录”中查看。
- 前端冲突弹窗用 `ElMessageBox` 的 HTML 片段还是独立小组件。
- 测试夹具中伪造 vendor/installed.json 的方式。

## 实现阶段补充

- 采用宿主专用 Composer caret 匹配，正确处理 `^0.x`，不改变旧插件约束语义。
- 冲突检测放在 package coordinator 之前，因为 coordinator 本身会创建记录/改变状态。
- 确认仅在操作成功后持久化；同时兼容核心 status/metadata 与生命周期 result 两种审计表结构。
- MySQL 的正式 migrate 走核心 SQL，所以额外提供 `023_plugin_composer_conflict_ack.sql`；PHP032 用于其他驱动。
- autoload.files 的执行不受追加注册保护；明确记录为依赖作者需 scoper 处理的边界。

- 独立审阅发现冷启动会反射已停用插件控制器。新增 `PluginRouteLoader` 并接入 `support/bootstrap.php`，反射前按激活/归属快照过滤；已启用插件先加载 vendor。未启用插件不会加载 PHP。冷启动时未注册的控制器在启用后需重载 worker 注册路由（不自动重载）。
