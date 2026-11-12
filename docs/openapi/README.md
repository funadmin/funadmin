# 后台接口文档注释

后台控制器在现有 Webman 路由属性上方维护 OpenAPI PHPDoc 注释，使用 `use OpenApi\Annotations as OA;` 解析别名。路由注册仍由原来的 `#[RouteGroup]`、`#[Get]`、`#[Post]` 等属性负责。

`ApiDocumentation.php` 集中声明文档信息、模块分组、认证方式、请求模型和响应模型。它只供文档工具扫描，不需要加入应用启动流程。

## 维护约定

- 新增接口时，在方法 PHPDoc 中增加对应的 `@OA\Get`、`@OA\Post` 等操作定义。`path` 必须写完整路径，包含类级路由分组前缀。
- Webman 的 `{id:\d+}` 在 OpenAPI `path` 中写作 `{id}`，约束写在 `@OA\Parameter` 的 Schema 中；路径参数始终 `required=true`。
- 参数放入正确位置：路径和查询参数用 `@OA\Parameter`，JSON 请求体用 `@OA\RequestBody`、`@OA\JsonContent`，文件上传用 `multipart/form-data` 和 `format="binary"`。
- `required`、类型、枚举和默认值以控制器及其调用的校验器为准。更新接口缺省字段可能保留原值，不能机械复用创建接口的必填条件。
- `operationId` 在整个文档内唯一。移动控制器或重命名动作时同步维护，避免自动客户端的方法名冲突。
- 修改接口时同步修改路由属性、操作注释以及对应的公共模型；这些声明目前不会自动互相同步。

## 响应协议

| 接口类型 | 成功协议 | 错误协议 |
| --- | --- | --- |
| 普通后台业务接口 | `code=200`，包含 `msg`、`time`、`data` | 业务码 400/422 通常使用 HTTP 200，其他异常以实际 HTTP 状态返回 |
| 浏览器认证、个人资料 | `code=200`，包含 `msg`、`data`，不含 `time` | `code` 与 HTTP 状态一致 |
| `/api/auth` 管理员令牌认证 | `code=0`，包含 `message`、`data` | `code` 为 HTTP 错误码 |
| `/api/v2` 会员 API | `code=200`，包含 `msg`、`time`、`data` | `code` 与 HTTP 状态一致（400/401/429） |

普通后台的 HTTP 200 响应模型区分成功结果和业务校验失败；调用方必须读取响应体 `code`。附件原始内容、ZIP 下载、SSE 和元数据 304 响应分别按其真实响应形式描述，不套用普通 JSON 成功模型。

## 认证与扩展字段

- `AdminSession` 表示 `WEBMAN_ADMIN_SESSION` Cookie。先调用 `/admin/auth/csrf` 获取预登录会话及 CSRF 令牌，再调用 `/admin/auth/login`。
- 浏览器写请求同时要求会话 Cookie 和 `X-CSRF-TOKEN`；同一 security requirement 中两个 scheme 表示同时满足。
- `/api/auth/me` 和 `/api/auth/logout` 使用登录接口签发的 `AdminBearer` 不透明令牌。
- `/api/v2/member` 和 `/api/v2/logout` 使用 `MemberBearer`：由 `/api/v2/token` 签发的会员 access token（JWT，携带会话标识）。
- **鉴权注解（后台与会员通用，`app\common\annotation`）**：控制器统一经过各自的鉴权中间件（后台 `AdminController`，会员 `ApiController`），方法上用注解声明要求，**默认需要登录**：
  - 不标注：需要登录；后台还需要权限，权限码按控制器/方法推导（`{模块}:{资源}:{方法}`，每个方法一个权限）后到权限表查询（含 `is_public`）。
  - `#[NoNeedLogin]`：无需登录。会员接口若仍带有效令牌会注入当前会员；后台不注入管理员上下文，CSRF 与操作日志照常执行。
  - `#[NoNeedPermission]`：后台登录即可，不校验权限码（如插件页面目录）。
  - `#[Permission('code')]`：宿主后台可选，显式指定权限码，数组表示任一满足（一般不需要，推导码即可）；插件后台控制器不使用，固定按推导码鉴权。会员端暂无权限码体系。
  - 方法注解优先于类注解（方法上的 `NoNeedPermission` 可覆盖类上的 `Permission`）；方法声明了权限码时，类上的 `NoNeedLogin` / `NoNeedPermission` 都不会放宽它。目标无法解析时按最严格要求处理。
  - 插件后台控制器总是要求登录并必须声明 `#[Permission]`，`NoNeedLogin` / `NoNeedPermission` 对其不生效。
- `/api/v2/token/refresh` 的 refresh token 只能使用一次，调用方必须保存响应中的新值；旧值再次提交会吊销整个会话。
- 动态表单字段通过 `/admin/form/data/meta/{key}` 获取；插件配置、按钮输入以及其他扩展对象保留开放结构，并在注释中说明其来源，不能把当前示例当作所有插件的固定契约。
- 含密码或 API 密钥的请求字段使用 `writeOnly`，响应不复用带密钥的输入模型。示例只使用占位值。

## 后续接入生成工具

这批注释使用 swagger-php 的 `@OA` PHPDoc 语法，已通过 swagger-php 5.8.3 和 doctrine/annotations 2.0.2 在临时目录的解析校验；应用的 Composer 依赖未变更。

后续接入生成命令时，需要同时扫描控制器和本目录的 `ApiDocumentation.php`，并启用 PHPDoc 注释解析。生成结果可输出 OpenAPI 3.0.3 JSON，供文档页面或接口工具使用。

建议生成时检查：注释语法、完整路径与实际路由一致、操作 ID 唯一、模型引用可解析、路径参数齐全，以及错误响应和认证方式匹配。

## 覆盖范围与防回退测试

- 范围：核心 `admin/app/{admin,api,common,install,...}` 下带路由属性的控制器（不含 `app/admin/plugins/`、`app/admin/business/` 等不入库目录）；`config/route.php` 中指向控制器方法的 `Route::get/post/put/delete/patch/head/options/add` 调用（含 `Route::group` 前缀，如 `/api/v2` 会员 API、`/plugins/hooks/frontend`、默认入口 `/admin/auth/*`）；以及随仓库提供的插件源码 `admin/plugins/<code>/app/**/controller`（CMS、文档、问答、OSS 的后台与前台控制器）。闭包路由（`/health`、`/`）没有控制器方法，不在检查范围。插件安装副本（`app/<code>/`、`app/admin/plugins/<code>/`）与源码相同，不重复扫描。
- 插件标签在 `ApiDocumentation.php` 中登记；插件前台与会员接口使用 `PluginEnvelope` 与 `PluginError`（不含 `time`，`code` 与 HTTP 状态一致；CMS 公开表单与文档读者反馈成功时 `code=0`）。服务端渲染页面、文件下载和 CSV 按真实响应类型描述。
- 代码生成器（`ProductionTemplateContext`）生成后台、插件和会员控制器时同时生成注释：`operationId` 由控制器命名空间、类名、方法和动词组成，按实体唯一；标签为实体标题；写入注释的标题会折叠换行、转义 `*/`、`@` 与双引号，不能提前结束注释或注入注解。
- `admin/tests/OpenApiCoverageTest.php` 静态检查（不依赖 swagger-php，路由规则与 Webman 一致：只取第一个 `RouteGroup`，无法识别的路由属性、`#[Any]`/`Route::any` 以及带路由属性的 trait 方法直接报错）：每个路由方法都有动词和完整 path 一致的 `@OA` 操作、路径参数以 `in="path", required=true` 声明、操作带响应和已登记的标签、`operationId` 全局唯一。同一路由声明 GET 与 HEAD 时，HEAD 由 GET 操作覆盖。检查器位于 `admin/tests/Support/OpenApiCoverage.php`，`admin/tests/Console/CrudGeneratedOpenApiTest.php` 用它检查生成的控制器。
