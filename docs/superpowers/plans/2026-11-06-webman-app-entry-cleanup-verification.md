# Webman 应用根目录入口清理

日期：2026-11-06。

## 文件处理

| 文件（相对 admin/） | 结论与处理 |
| --- | --- |
| app/event.php | ThinkPHP bind/listen/subscribe 结构且无运行时加载引用，删除；事件使用 config/event.php。 |
| app/provider.php | 已为空的旧 Provider 入口，无加载引用，删除；原生容器使用 config/container.php。 |
| app/service.php | ThinkPHP 服务清单，无 Webman 加载引用，删除；服务启动使用 config/bootstrap.php。 |
| app/middleware.php | 与 config/middleware.php 重复、未被 Webman 启动链加载，删除；语言中间件测试同步读取实际配置。 |
| app/AppService.php | 实现 Webman Bootstrap，保留并登记到 config/bootstrap.php，在插件启动服务之前注册本地存储驱动。 |
| app/BaseController.php | 在用的自定义控制器基类，使用 support Request 与 Webman Validator，保留；原版权归属注释不作为框架依赖判断依据。 |
| app/ExceptionHandle.php | 继承 Webman ExceptionHandler 且由 config/exception.php 引用，保留。 |
| app/functions.php | 已合并公共函数，由 config/autoload.php 加载，保留。 |
| app/.gitignore、app/.htaccess | 忽略规则与 Apache 源码访问保护，保留。 |
| app/common.php、app/Request.php | 此前会话已移除；公共函数使用 app/functions.php，请求类使用 support/Request.php。本轮将仍引用 app Request 的测试改为验证 config('app.request_class')。 |

应用根目录仍在使用的主要 PHP 文件为 AppService.php、BaseController.php、ExceptionHandle.php、functions.php。不扩展清理各业务模块和插件包的其它文件，保留并行会话修改。

## 启动问题验证

新增真实子进程 Webman bootstrap 回归，确认应用服务已向容器登记 StorageDriverRegistry，且可解析 local 驱动。修改前观察到 `registered=false, driver=null`，登记 AppService 后变为 `registered=true, driver=local`。

真实 bootstrap 同时验证：请求类为 support Request，异常处理类为 app ExceptionHandle，syscfg 函数来源为 app/functions.php。没有迁移仍在使用的业务基础类。

## 测试与检查

- 启动、请求、语言中间件、异常、后台基础控制器、MCP stdio、前端 API 路由相关 PHPUnit：52 项、282 次断言全部通过。日志 `/tmp/app-root-cleanup-scoped.log`。
- 前端 auth-service-decomposition、backend-controller-layout、install-environment 契约：3 个文件、13 项通过。日志 `/tmp/app-root-cleanup-frontend.log`。
- `php webman list --raw` 退出码 0，插件、安装和 MCP 命令均可发现。日志 `/tmp/app-root-cleanup-cli.log`。
- `php tests/lint.php`：891 个 PHP 文件通过，退出码 0。日志 `/tmp/app-root-cleanup-lint.log`。
- `git diff --check` 通过；执行源码和测试没有继续引用删除的根目录 event/provider/service/middleware 文件或 app Request。

## 全量后台快照

`vendor/bin/phpunit`：1071 项、7813 次断言，1 个失败、1 个 SDK 上游弃用提示、113 项环境跳过，退出码 1。日志 `/tmp/app-root-cleanup-full-backend.log`。不能标为全量通过。

失败项：`Tests\MemberIntegrationTest::test_v2_login_is_also_limited_per_account_across_ip_addresses`，第 6 次请求期望 429，实际 401。该项属于并行会员 API 工作，单独重跑同一测试通过（1 项、6 次断言，退出码 0，日志 `/tmp/app-root-cleanup-member-isolated.log`）；本轮没有修改该限流控制器或测试。

未提交或推送代码，未执行生产迁移，未重载服务。
