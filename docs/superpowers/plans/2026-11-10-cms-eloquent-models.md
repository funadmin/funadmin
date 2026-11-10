# CMS 查询迁移到 Eloquent 模型 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 为 CMS 插件的 26 张 `fun_cms_*` 表建立 Eloquent 模型，把实体读写从 `Db::table('cms_*')` 迁到 `XxxModel::query()` / `fill()->save()`。所有可观察行为保持不变：SQL 语义、写入的字节、返回的数组结构、锁、缓存失效都不变。

**Architecture:** 新增 `app\cms\model`（源码 `admin/plugins/cms/app/cms/model/`）：一个抽象基类 `CmsModel`、4 个作用域 trait、26 个表模型。基类关闭自动时间戳、不声明 JSON cast、`$guarded = []`。服务层按“迁移规则”逐组替换，每组一个任务。联表投影、动态表名、宿主表、Schema/升级代码，以及带 `Query\Builder` 类型的导出管线保留在 `Db::`。最后用一个白名单测试把剩余的 `Db::table` 固定下来。

**Tech Stack:** PHP 8.5、webman、illuminate/database 13.39（`support\Db`、`app\common\model\BaseModel`）、PHPUnit（默认 sqlite 内存库）。

**Spec:** 无独立规格。本计划依据维护者的需求（“CMS 从 `Db::table()` 重构为 Eloquent 模型”）和下文“现状盘点”写成。

## 现状盘点（实现前必读）

- 共 244 处 `Db::table`，分布在 27 个文件里：`SiteService` 25、`ContentService` 34、`FormSubmissionService` 20、`SiteRoleService` 18、`TemplateTagService` 18、`FormService` 16、`CategoryService` 14、`MenuService` 14、`RouteService` 11、`ContributionService` 11、`ModelService` 10、`MutationAuthorization` 7、`SiteAuthorization` 6、`FormPermissionUpgrade` 6、`Content.php` 6、`Sites.php` 5、`SiteRoleSchema` 4、`ContributionPolicy` 4、`SiteConfiguration` 3、`BuiltinTags` 3、`Renderer` 2、`SiteAccess` 2，`ThemePresentation`、`Seo`、`PublicSite`、`PermissionCatalog`、`Models.php` 各 1。另有 `lockForUpdate` 20 处、`insertGetId` 18 处、`join` 9 处、`Db::transaction` 34 处。
- 时间戳全部手写，值来自 `Input::now()`，即 **UTC** `gmdate('Y-m-d H:i:s')`。而应用时区是 `Asia/Shanghai`（`config/app.php`），所以 Eloquent 自动时间戳会写入错误的时间。
- JSON 列写入 `Input::json()` 生成的字符串（`JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES`）。读取时由 `Input::row()` 解码 `settings/field_schema/data/parameters` 这几列，其余 JSON 列（`fields/texts/values/result/before_state/after_state`）在各服务里手动解码。Eloquent 的 `array` cast 用默认 flags 编码，写入的字节会变。
- `deleted_at` 列存在于 `cms_site`、`cms_category`、`cms_content`、`cms_content_locale`、`cms_template_tag`、`cms_site_role`。CMS 代码**从不写入** `deleted_at`，只用 `whereNull('deleted_at')` 过滤（56 处）。这 6 张表也从不被 `delete()`。
- 4 张无 `id` 的关联表：`cms_site_admin`、`cms_site_model`、`cms_site_role_permission`、`cms_site_role_member`。
- 读出的行统一经过 `Input::row()/rows()`：先 `(array)` 转换，再把 `id`/`*_id` 转成字符串，再解码 JSON。`ContributionService::normalize()` 也用 `(array)`。对 Eloquent 模型做 `(array)` 会得到受保护属性，**必须先解包**。
- 宿主表读取：`attach` 5 处、`admin` 1 处、`member` 1 处、`permission` 1 处，都先做 `Db::schema()->hasTable` 防御。
- 测试：`admin/tests/Cms/CmsTestCase::setUp` 每个用例用 `*Schema::install()` 重建表。`admin/tests/Cms/bootstrap.php` 把 `app\cms\*` 和 `app\admin\plugins\cms\*` 自动加载到**插件源码**，所以新增的 `app\cms\model\*` 无需额外配置就能被测试加载。当前基线：`vendor/bin/phpunit tests/Cms` 共 296 个用例，4 个跳过（MySQL 用例，需要 `CMS_MYSQL_DATABASE=funadmin_cms_test_<hex>`）。测试夹具里的 `Db::table` 不改。
- 发布：`PluginAppPublicationService::sourceUnits()` 把 `plugins/cms/app/cms` 整体作为发布单元 `application:cms` 复制到 `app/cms`。该单元内部不限制子目录，所以 `model/` 会被自动发布。登记表按文件逐个记录 sha256，并记录整棵树的 `tree_hash`，`model/` 下的文件自动计入。`PluginModificationDetector::isModified()` 会把安装副本里多出的文件视为已修改。`performUpdate()` 要求新版本号高于已装版本，除非走 `redeployPlugin`。

## 迁移规则（每个任务都适用）

1. **转换**：`Db::table('cms_x')` 换成 `X::query()`，链式条件逐字保留。查询仍走默认连接，因此仍在外层 `Db::transaction` 里，模型不得声明 `$connection`。
2. **插入**：`$id=(string)Db::table(..)->insertGetId($data)` 改成 `$m=new X(); $m->fill($data)->save(); $id=(string)$m->getKey();`。关联表（无 id）用 `X::query()->insert()/insertOrIgnore()/updateOrInsert()`。禁止 `save([...])`、`create()`、`forceCreate()`。
3. **更新/删除**：按条件更新、批量更新、乐观锁更新（`->where('lock_version',$v)->update([...])`，用返回的行数判断冲突）保持构建器写法：`X::query()->where(..)->update($data)` / `->delete()`。不要改成“先查出来再 `fill()->save()`”，那样会多一次 SELECT，还会只写脏字段。
4. **锁**：带 `lockForUpdate()` 的读取改成 `X::query()->...->lockForUpdate()->first()/get()/pluck()`，锁的位置和条件不变。`SiteService::lock()` 里的 `transactionLevel()` 断言不变。
5. **时间戳**：`CmsModel::$timestamps = false`。`created_at/updated_at` 继续由调用方用 `Input::now()` 显式写入，原来没写的地方现在也不写。
6. **JSON**：模型不声明 cast。写入继续用 `Input::json()`，读取继续用 `Input::row()` 或原有的手动解码。
7. **软删除**：本计划不用 `SoftDeletes`/`LaravelSoftDelete`（见“需维护者决定”）。原来的 `whereNull('deleted_at')` 改为 `->alive()` 局部作用域，生成的 SQL 相同。原来没有过滤的查询仍然不过滤。
8. **作用域只做替换，不做校验**：`->forSite($id)` 等价于 `->where('site_id',$id)`，`Input::id()` 等校验留在调用处，原样保留，异常的类型和先后顺序不变。
9. **出口**：服务和控制器不得返回模型或 Eloquent Collection。原来经 `Input::row/rows` 转换的地方继续转换，原来按属性访问 `$row->x` 的地方照常访问（模型支持）。
10. **缓存失效**：`PublicPageCache` 的失效调用原位不动。禁止使用模型事件、observer、`booted()` 钩子。
11. **保留在 `Db::` 的五类代码**（理由写在 Review Focus 之前的白名单里）：
    - (a) 带别名的联表投影（`cms_x as l` + join），连同它们内部的子查询；
    - (b) 动态表名：`RouteService` 的 `$table`/`targetTable()`、`MenuService` 的目标校验、`MutationAuthorization` 的 `'cms_'.$kind`、`FormSubmissionService::delete` 的 `foreach` 表；
    - (c) 宿主表 `attach/admin/member/permission`；
    - (d) `*Schema.php` 和 `FormPermissionUpgrade`，属于生命周期/升级代码，需针对迁移当时的表结构工作，不依赖模型的演进；
    - (e) 类型为 `Illuminate\Database\Query\Builder` 的导出/筛选管线（`FormSubmissionService::filtered/rows/batches`）和原始聚合（`FormService::counts` 的 `selectRaw ... groupBy`）。
    `Db::transaction`、`Db::schema`、`Db::connection` 不在本次迁移范围内。

## Global Constraints

- 插件源码为准，后端代码只写在 `admin/plugins/cms/app/cms/...` 和 `admin/plugins/cms/app/admin/plugins/cms/...`。
- **每个任务结束时，源码和安装副本必须逐字节一致**。同步命令：`rsync -a --delete --exclude .DS_Store admin/plugins/cms/app/cms/ admin/app/cms/ && rsync -a --delete --exclude .DS_Store admin/plugins/cms/app/admin/plugins/cms/ admin/app/admin/plugins/cms/`。校验命令：`diff -r -x .DS_Store admin/plugins/cms/app/cms admin/app/cms && diff -r -x .DS_Store admin/plugins/cms/app/admin/plugins/cms admin/app/admin/plugins/cms`，无输出即通过。安装副本（`admin/app/cms`、`admin/app/admin/plugins/*`、`web/src/admin/plugins/*`）自 890ff960 起已被 gitignore，但运行中的应用依赖它们，所以本地必须同步。
- **不改变行为**：SQL 条件、写入值（含时间戳和 JSON 字节）、返回数组的键与类型（id 为字符串）、HTTP 响应、锁、缓存失效都与迁移前相同。
- **实现者不执行任何 git 操作**（不 add、不 commit、不建分支）。下文的“交付”步骤只是汇报给协调者。
- `admin/tests/Cms` 已被 gitignore，但仍是主测试套件。新测试照常写在这里。
- **每个任务结束时全套件保持绿色**：`cd admin && vendor/bin/phpunit tests/Cms` 结果为 OK，跳过数不超过 4；`cd admin && vendor/bin/phpunit` 无新增失败。
- 模型命名空间是 `app\cms\model`，目录是 `admin/plugins/cms/app/cms/model/`；trait 放在 `app\cms\model\concern`。不要放进 Console layer `app/admin/plugins/cms/model`，因为服务在前台应用和后台控制器之间共用。
- `plugin.json` 的版本从 `1.5.0` 升到 `1.5.1`，在 Task 8 完成，不新增迁移文件。

## 白名单（迁移完成后仍允许的 `Db::table`）

| 文件 | 允许保留 | 类别 |
|---|---|---|
| `service/SiteRoleSchema.php` | 全部（4） | d |
| `service/FormPermissionUpgrade.php` | 全部（6） | d |
| `service/ContentService.php` | `listing()` 的联表及其子查询 | a |
| `service/CategoryService.php` | 联表查询（`cms_category_locale as l`） | a |
| `service/TemplateTagService.php` | `models`/`categories` 两条联表 | a |
| `service/ContributionPolicy.php` | 联表查询 | a |
| `service/SiteAuthorization.php` | 角色联表；`admin` 表 | a、c |
| `service/SiteRoleService.php` | `cms_site_admin as m` join `admin` | a、c |
| `service/RouteService.php` | `$table`/`targetTable()` 3 处 | b |
| `service/MenuService.php` | 目标表校验 1 处 | b |
| `service/MutationAuthorization.php` | `'cms_'.$kind` 1 处 | b |
| `service/FormSubmissionService.php` | `foreach` 动态表删除 1 处；`filtered()` 管线 | b、e |
| `service/FormService.php` | `counts()` 原始聚合 | e |
| `service/ContributionService.php`、`Renderer.php`、`ThemePresentation.php`、`Seo.php`、`PermissionCatalog.php`、`controller/Content.php` | 宿主表 `member/attach/permission` | c |

## Review Focus

- **时间戳时区**：任何写入路径都不能出现 Eloquent 自动写入的本地时间（Asia/Shanghai）。`created_at/updated_at` 仍是 UTC，原来不写的地方仍然不写。由 Task 1 的 `test_models_never_write_timestamps_themselves` 和 Task 2 的 `test_site_save_keeps_utc_timestamps_and_unescaped_json` 固定。
- **JSON 字节**：含中文和斜杠的 settings、表单 texts 写入后，原始字符串里不能出现 `\u` 或 `\/`。由 Task 2 的 `test_site_save_keeps_utc_timestamps_and_unescaped_json` 和 Task 6 的 `test_form_texts_are_stored_unescaped` 固定。
- **字符串 ID**：新插入的行通过 `getKey()` 拿到的是 int，返回值和嵌套的 `*_id` 必须仍为字符串。由 Task 1 的 `test_row_unwraps_models_exactly_like_stdclass` 和 Task 2 的同一测试固定。
- **已软删的行**：手工置了 `deleted_at` 的站点、栏目、内容仍然查不到；原来不过滤的查询（如保存后回读）仍然能读到。由 Task 2 的 `test_soft_deleted_site_stays_invisible` 固定。
- **并发锁与乐观锁**：`lockForUpdate` 总数保持 20，表单行锁和 `lock_version` 冲突仍返回 409。由 Task 6 的 `test_stale_form_version_conflicts` 和 Task 8 的锁计数断言固定。可用 MySQL 时另跑 `MySqlLockTest`。

---

### Task 1: 模型基类、作用域与 26 个模型

**Files:**
- Create: `admin/plugins/cms/app/cms/model/CmsModel.php`
- Create: `admin/plugins/cms/app/cms/model/concern/{BelongsToSite,Localized,HasStatus,HasDeletedAt}.php`
- Create: `admin/plugins/cms/app/cms/model/` 下的 26 个模型：`Site, SiteLocale, Domain, SiteAdmin, ContentModel, ModelVersion, SiteModel, Category, CategoryLocale, Content, ContentLocale, ContentRevision, Route, Menu, MenuItem, TemplateTag, TemplateTagVersion, SiteRole, SiteRolePermission, SiteRoleMember, SiteAuthAudit, MemberSubmission, Form, FormSubmission, FormFile, FormEvent`（依次对应 `cms_site … cms_form_event`）
- Modify: `admin/plugins/cms/app/cms/service/Input.php`（增加 `attributes()`，`row()` 改为调用它）
- Test: `admin/tests/Cms/ModelContractTest.php`

**Interfaces:**
- Produces:
  - `abstract class CmsModel extends \app\common\model\BaseModel`：`public $timestamps = false; protected $guarded = []; protected $casts = [];`，不设 `$connection`。
  - 每个模型声明 `final class X extends CmsModel { protected $table = 'cms_x'; }`，使用 `$table`，不用 `$name`。无 id 的 4 张表另加 `protected $primaryKey = null; public $incrementing = false;`。
  - `trait BelongsToSite { scopeForSite(Builder $q, string $siteId): Builder }`，等价于 `where('site_id',$siteId)`。用于所有有 `site_id` 列的表。
  - `trait Localized { scopeForLocale(Builder $q, string $locale): Builder }`，等价于 `where('locale',$locale)`。用于有 `locale` 列的表。
  - `trait HasStatus { scopeEnabled(Builder $q): Builder }`，等价于 `where('status',1)`。用于 `status` 为 tinyint 的表，不用于 `status` 为字符串的 `cms_content_locale`、`cms_member_submission`、`cms_form_submission`。
  - `trait HasDeletedAt { scopeAlive(Builder $q): Builder }`，等价于 `whereNull('deleted_at')`，不加表名限定。只用于上述 6 张表。
  - `Input::attributes(object|array|null $row): ?array`：Eloquent 模型返回 `getAttributes()`，其他输入返回 `(array)$row`，null 返回 null。`Input::row()` 改为先调用它，其余逻辑不变。

- [ ] **Step 1: 写失败测试** `ModelContractTest extends CmsTestCase`：
  - `test_every_cms_table_has_exactly_one_model`：`Schema::tables()+SiteRoleSchema::tables()+ContributionSchema::tables()+FormSchema::tables()` 与 `glob(model/*.php)` 中各模型 `(new X)->getTable()` 的集合相等（26 张）。
  - `test_model_traits_match_columns`：对每个模型，`Db::schema()->hasColumn($table,'site_id')` 等价于使用了 `BelongsToSite`；`locale` 列对应 `Localized`；`deleted_at` 列对应 `HasDeletedAt`；`id` 列对应 `getIncrementing()===true`。用 `class_uses` 判断 trait。
  - `test_models_never_write_timestamps_themselves`：`$m=new MenuItem(); $m->fill([...不含时间戳...])->save();` 之后 `Db::table('cms_menu_item')->find($m->getKey())->created_at` 为 null。并断言 `(new Site)->usesTimestamps()===false`、`(new Site)->getCasts()` 中没有 `settings` 键。
  - `test_scopes_compile_to_the_same_sql`：`Content::query()->forSite('1')->alive()->toSql()` 与 `Db::table('cms_content')->where('site_id','1')->whereNull('deleted_at')->toSql()` 相同，绑定也相同。
  - `test_row_unwraps_models_exactly_like_stdclass`：`$this->site()` 之后，`Input::row(Site::query()->first())` 与 `Input::row(Db::table('cms_site')->first())` 完全相等（`assertSame`，含 `id` 为字符串、`settings` 已解码）。
- [ ] **Step 2: 运行确认失败**：`cd admin && vendor/bin/phpunit tests/Cms/ModelContractTest.php`，预期因类 `app\cms\model\Site` 不存在而失败。
- [ ] **Step 3: 按 Interfaces 实现基类、trait、26 个模型和 `Input::attributes`。** 每个模型只放表名、主键设置和 trait，不放关联、访问器或 cast。
- [ ] **Step 4: 运行通过**：同上命令 PASS；`vendor/bin/phpunit tests/Cms` 结果 OK（296 个原有用例加新用例，跳过 4）。
- [ ] **Step 5: 同步并校验副本**：执行 Global Constraints 中的 rsync 和 diff 命令，diff 无输出。
- [ ] **Step 6: 交付**：向协调者汇报改动文件清单，不执行 git。

### Task 2: 站点组（站点、语言、域名、配置、访问）

**Files:**
- Modify: `admin/plugins/cms/app/cms/service/{SiteService,SiteAccess,SiteConfiguration,PublicSite,SiteAuthorization}.php`
- Test: `admin/tests/Cms/ModelParitySiteTest.php`

**Interfaces:**
- Consumes：Task 1 的 `Site, SiteLocale, Domain, SiteAdmin, SiteModel, ContentModel`，作用域 `forSite/forLocale/enabled/alive`，以及 `Input::attributes`。
- Produces：公开方法签名不变。`SiteService::lock(string $siteId): array` 仍要求在事务中调用。

- [ ] **Step 1: 写测试**（迁移前就应通过，用来锁定行为）：
  - `test_site_save_keeps_utc_timestamps_and_unescaped_json`：`site()` 之后保存 `settings` 为 `['note'=>'中文/路径']`。断言 `Db::table('cms_site')->value('settings')` 包含 `中文/路径`，不含 `\u` 和 `\/`；`created_at` 与 `gmdate('Y-m-d H:i')` 的分钟前缀一致；返回数组中的 `id` 和 `primary_domain_id` 都是 string。
  - `test_soft_deleted_site_stays_invisible`：手工 `update(['deleted_at'=>Input::now()])` 后，`get()` 抛 404，`listing('1')` 不含该站点，`byHost('cms.test')` 返回 null。
- [ ] **Step 2: 运行**：`vendor/bin/phpunit tests/Cms/ModelParitySiteTest.php`，在旧代码上即应 PASS（行为基线）。
- [ ] **Step 3: 按迁移规则 1–10 转换以上 5 个文件。** `SiteService::save` 中“新站点为所有启用模型写入 `cms_site_model`”改为 `SiteModel::query()->insert([...])`。`listing()` 的 `whereIn` 子查询改为 `SiteAdmin::query()->select('site_id')->where('admin_id',..)`。`PublicSite` 的 `cursor()` 改为 `Route::query()->...->cursor()`，循环体中的属性访问不变。`SiteAuthorization` 中的角色联表和 `admin` 表保留（白名单 a/c），其余转换。
- [ ] **Step 4: 运行**：`vendor/bin/phpunit tests/Cms`，结果 OK。重点关注 `SiteConfigurationTest`、`PublicPageCacheTest`、`SiteRoleTest`、`PublicTest`。
- [ ] **Step 5: 同步并校验副本**，diff 无输出。
- [ ] **Step 6: 交付**，不执行 git。

### Task 3: 站点角色组

**Files:**
- Modify: `admin/plugins/cms/app/cms/service/{SiteRoleService,MutationAuthorization}.php`
- Test: 现有的 `SiteRoleTest`、`SiteRoleApiTest`、`SiteRoleDelegationTest`、`PermissionTest`

**Interfaces:**
- Consumes：`SiteRole, SiteRolePermission, SiteRoleMember, SiteAuthAudit, SiteAdmin`。
- Produces：签名不变。

- [ ] **Step 1: 写测试** `ModelParitySiteTest::test_role_audit_json_is_unescaped`：修改一个角色名为中文后，`cms_site_auth_audit.after_state` 的原始字符串不含 `\u`。
- [ ] **Step 2: 运行**：在旧代码上 PASS。
- [ ] **Step 3: 转换。** `insertOrIgnore` 改为 `SiteAdmin::query()->insertOrIgnore(..)`。关联表的删除用 `X::query()->where(..)->delete()`。`SiteRoleService` 第 19 行的 `admin` 联表和 `MutationAuthorization` 的 `'cms_'.$kind` 保留（白名单）；`MutationAuthorization` 其余固定表名的查询转换。
- [ ] **Step 4: 运行** `vendor/bin/phpunit tests/Cms`，结果 OK。
- [ ] **Step 5: 同步并校验副本。**
- [ ] **Step 6: 交付。**

### Task 4: 内容模型、栏目、路由、导航

**Files:**
- Modify: `admin/plugins/cms/app/cms/service/{ModelService,CategoryService,RouteService,MenuService}.php`
- Test: 现有的 `FoundationTest`、`ModelFieldConfigurationTest`、`MenuContractTest`、`PhaseTwoModulesTest`

**Interfaces:**
- Consumes：`ContentModel, ModelVersion, SiteModel, Category, CategoryLocale, Route, Menu, MenuItem`。
- Produces：签名不变。`RouteService::targetTable(string $type): string` 保留。

- [ ] **Step 1: 写测试** `ModelParitySiteTest::test_category_and_menu_ids_stay_strings`：新建栏目和导航项，断言返回的 `id/parent_id/category_id/menu_id` 都是 string 或 null。
- [ ] **Step 2: 运行**：在旧代码上 PASS。
- [ ] **Step 3: 转换。** `CategoryService` 的 `orderByRaw('CASE WHEN locale = ? ...')` 无别名，可以转换（Eloquent 支持）。第 57 行的联表保留。`RouteService` 中 `$table` 相关的 3 处、`MenuService` 的目标校验保留，其余（`cms_route` 的锁读、插入、更新）转换。`ModelService::seed()` 也转换：它只在启用时和测试 setUp 中调用，此时模型类已经发布。
- [ ] **Step 4: 运行** `vendor/bin/phpunit tests/Cms`，结果 OK。
- [ ] **Step 5: 同步并校验副本。**
- [ ] **Step 6: 交付。**

### Task 5: 内容、修订、投稿、渲染与模板标签

**Files:**
- Modify: `admin/plugins/cms/app/cms/service/{ContentService,ContributionService,ContributionPolicy,Renderer,TemplateTagService}.php`
- Test: 现有的 `ContentTest`、`ContributionServiceTest`、`ContributionEndpointTest`、`TagsTest`、`TemplateTest`、`PageFormTest`

**Interfaces:**
- Consumes：`Content, ContentLocale, ContentRevision, MemberSubmission, TemplateTag, TemplateTagVersion, ModelVersion`。
- Produces：`ContributionService::paginate(\Illuminate\Database\Eloquent\Builder $query, int $page): array`（参数类型由 `Query\Builder` 改为 Eloquent Builder）。`ContributionService::normalize()` 改为先调用 `Input::attributes($row)`，不再直接 `(array)`。

- [ ] **Step 1: 写测试** `ModelParityContentTest`：
  - `test_offline_with_stale_lock_version_conflicts`：用旧的 `lock_version` 调用 `offline()`，抛出 code 为 409 的异常，`cms_content_locale.status` 不变。
  - `test_submission_rows_are_normalized_arrays`：`ContributionService` 的列表方法返回的每项都是 array，`id/member_id` 为 string，`data` 已解码。
- [ ] **Step 2: 运行**：在旧代码上 PASS。
- [ ] **Step 3: 转换。** `ContentService::listing()` 整条联表（含 `whereIn(Db::table('cms_content_model'))` 和 `Db::table('cms_category')` 子查询）保留。`TemplateTagService` 的两条联表保留。其余转换，包括原来没有 `whereNull` 的回读（如 `TemplateTagService` 第 57 行），这些回读不加 `alive()`。乐观锁 `->where('lock_version',..)->update()` 保留构建器写法，继续用返回的行数判断冲突。
- [ ] **Step 4: 运行** `vendor/bin/phpunit tests/Cms`，结果 OK。
- [ ] **Step 5: 同步并校验副本。**
- [ ] **Step 6: 交付。**

### Task 6: 表单与提交（锁敏感）

**Files:**
- Modify: `admin/plugins/cms/app/cms/service/{FormService,FormSubmissionService}.php`
- Test: 现有的 `FormServiceTest`、`FormSubmissionServiceTest`、`FormAdminTest`、`FormPublicTest`；新增 `admin/tests/Cms/ModelParityFormTest.php`

**Interfaces:**
- Consumes：`Form, FormSubmission, FormFile, FormEvent, Site, SiteLocale`。
- Produces：签名不变。`FormSubmissionService::filtered(): Query\Builder`、`rows()`、`batches()` 和 `FormService::counts()` 保持原样（白名单 e）。

- [ ] **Step 1: 写测试** `ModelParityFormTest`：
  - `test_form_texts_are_stored_unescaped`：保存带中文和 `/` 的 texts，原始的 `cms_form.texts` 不含 `\u` 和 `\/`。
  - `test_stale_form_version_conflicts`：用旧版本调用 `setStatus()`，抛出 code 为 409 的异常，`lock_version` 不变。
  - `test_lock_reads_keep_for_update`：sqlite 语法会丢掉 `for update`，查询日志里观察不到，因此改为对源码做静态断言：`substr_count(file_get_contents(<FormService.php>),'lockForUpdate()')===1`，`FormSubmissionService.php` 中为 4。真实的加锁行为由 `MySqlLockTest` 覆盖。
- [ ] **Step 2: 运行**：在旧代码上 PASS。
- [ ] **Step 3: 转换。** `cms_form` 行锁改为 `Form::query()->where('site_id',..)->where('id',..)->lockForUpdate()->first()`，位置、条件、所在事务都不变。`invalidate()`/`delete()` 中的批量锁读改为 `FormSubmission::query()->...->lockForUpdate()->get([...])/pluck('id')`。`lock_version` 的自增更新保留构建器 `update()`。`foreach (['cms_form_file','cms_form_event'] as $table)` 保留。提交时的插入改为 `fill()->save()`，`cms_form_file` 的插入改为 `FormFile` 的 `fill()->save()`。
- [ ] **Step 4: 运行** `vendor/bin/phpunit tests/Cms`，结果 OK。有 MySQL 测试库时再跑：`CMS_MYSQL_DATABASE=funadmin_cms_test_<hex> TEST_MYSQL=1 vendor/bin/phpunit tests/Cms/MySqlLockTest.php`。
- [ ] **Step 5: 同步并校验副本。**
- [ ] **Step 6: 交付。**

### Task 7: 后台控制器与内置标签

**Files:**
- Modify: `admin/plugins/cms/app/admin/plugins/cms/controller/{Content,Sites,Models}.php`、`admin/plugins/cms/app/cms/tags/BuiltinTags.php`
- Test: 现有的 `AdminTest`、`TagsTest`、`PhaseTwoTemplateTest`、`SeoTest`

**Interfaces:**
- Consumes：`SiteModel, Content, ContentLocale, ContentRevision, Domain, SiteLocale, ContentModel, CategoryLocale`。
- Produces：HTTP 响应不变。

- [ ] **Step 1: 写测试** `ModelParitySiteTest::test_model_binding_upsert_is_idempotent`：对同一模型调用 `Sites::modelSave` 两次，`cms_site_model` 中该站点和模型的组合只有 1 行，`default_template` 为最后一次的值。
- [ ] **Step 2: 运行**：在旧代码上 PASS。
- [ ] **Step 3: 转换。** `updateOrInsert` 改为 `SiteModel::query()->updateOrInsert(..)`。Eloquent 的 `__call` 会转发它并返回 builder，原代码本来就忽略返回值，之后直接 `return true`。`Content.php` 中 `attach` 列表保留（白名单 c）。`Base.php` 的 `Db::transaction` 不动。
- [ ] **Step 4: 运行** `vendor/bin/phpunit tests/Cms`，结果 OK。
- [ ] **Step 5: 同步并校验副本**，两组目录都要同步。
- [ ] **Step 6: 交付。**

### Task 8: 白名单门禁、版本号与收尾校验

**Files:**
- Create: `admin/tests/Cms/QueryBuilderAllowlistTest.php`
- Modify: `admin/plugins/cms/plugin.json`（`"version": "1.5.1"`）、`admin/tests/Cms/CommunityWiringTest.php:26`（改为断言 `'1.5.1'`）、`admin/plugins/cms/README.md`（新增 `### 1.5.1`：内部改用 Eloquent 模型，无迁移，无行为变化；升级流程为停用、更新、启用）

**Interfaces:**
- Consumes：Task 1–7 的全部结果。

- [ ] **Step 1: 写测试** `QueryBuilderAllowlistTest`：
  - `test_db_table_only_remains_in_allowlisted_files`：扫描 `plugins/cms/app` 下所有 `.php` 文件中 `Db::table(` 的出现次数（含 `\support\Db::table(`），按文件与一个常量 `ALLOWED = ['cms/service/SiteRoleSchema.php'=>4, ...]` 比较，要求**相等**。实际数值在 Task 7 完成后用 `grep -c` 得到，并与白名单表逐项核对，不得出现表外文件。
  - `test_lock_count_is_unchanged`：全目录 `lockForUpdate(` 的出现次数为 20。
  - `test_no_save_with_array_argument`：全目录不出现 `->save([`、`::create(`、`forceCreate(`。
  - `test_models_do_not_escape_services`：`app/cms/model` 中任何文件都不出现 `$casts = [` 后跟非空数组、`booted(`、`observe(`、`$connection`。
- [ ] **Step 2: 运行**：`vendor/bin/phpunit tests/Cms/QueryBuilderAllowlistTest.php` PASS。如有超出白名单的残留，回到对应任务修正，不要放宽白名单。
- [ ] **Step 3: 升级版本号并更新 README 和 `CommunityWiringTest`。**
- [ ] **Step 4: 全量验证**：`cd admin && vendor/bin/phpunit tests/Cms` OK（跳过 4）；`cd admin && vendor/bin/phpunit` 无新增失败；`grep -rn "Db::table('cms_" admin/plugins/cms/app | wc -l` 与白名单合计一致。
- [ ] **Step 5: 同步并校验副本**，diff 无输出。确认 `admin/app/cms/model/` 已存在且有 26 个模型文件加 1 个基类。
- [ ] **Step 6: 交付**：汇报剩余 `Db::table` 数量、版本号以及发布说明。已登记安装的环境会因为安装副本新增 `model/` 而在插件中心显示“已修改”，需要按正式流程“停用、更新（1.5.1）、启用”重新发布，或用 `redeployPlugin` 重新部署。不执行 git。

## 需维护者决定

- **软删除**：本计划用显式的 `alive()` 作用域，保证 SQL 与现在一致，转换也纯机械。另一种做法是在 6 张表上采用项目惯例 `LaravelSoftDelete`，但需要逐条给原来不过滤的查询加 `withTrashed()`，并接受 `deleted_at` 被 cast 成 datetime。建议本次用作用域，惯例化另作后续任务。
- **宿主表**：建议保留 `Db::table('attach'|'admin'|'member'|'permission')`。宿主模型的命名空间正在迁移（`app\common\model\Member` 与 `app\admin\system\model\Member` 并存），而插件对宿主表本来就做了 `hasTable` 防御。
- **版本号**：建议 `1.5.1`。
