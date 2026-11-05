# 插件钩子实施与验证记录

日期：2026-11-05

## 已实现

- PHP 全局 Hook facade、实例执行器与绑定调用链，动作/过滤/函数/类方法/可调用对象，深度 32、插件级启用与依赖检查、私有文件哈希校验。
- Manifest v2 可选 backendHooks/frontendHooks；无应用/菜单/路由、仅前端函数的插件可正常使用。
- 前端共享 hooks、PluginSlot、动作/过滤异步执行、组件错误边界、会话与权限投影同步、取消信号与清理。
- public/member/admin 精确匹配；后台描述入口 /admin/plugins/hooks/frontend 与既有 Cookie Path=/admin 一致；public/member 走 /plugins/hooks/frontend。
- 任意扩展点与跨插件调用，不依赖应用目录；可选依赖无监听器时返回原值，必需依赖继续由 requires.plugins 管理。
- 上传完成/响应与仪表盘卡片/加载/快捷入口的真实接入。
- 两个可安装示例：docs/examples/plugin-hooks/hookdemo、hookconsumer；无 app，无菜单/路由。验证安装、发布、启用、跨插件调用、禁用、更新、卸载与 ZIP 内容。
- 不安装新依赖，不执行业务数据库迁移，不覆盖无关工作区改动。

## 定向验证

后端：

```sh
cd webman-admin
php vendor/bin/phpunit tests/PluginHookRuntimeTest.php tests/PluginHookManifestTest.php tests/PluginHookDescriptorTest.php tests/PluginHookExampleTest.php tests/UploadHooksIntegrationTest.php tests/AttachmentHttpTest.php tests/AttachmentIntegrationTest.php tests/PluginHttpActivationTest.php tests/PluginCenterHttpTest.php tests/PluginCenterIntegrationTest.php tests/Console/PluginScaffoldExecutionTest.php tests/Console/PluginPackageExecutionTest.php tests/Console/PluginPublicationExecutionTest.php tests/Console/PluginLifecycleExecutionTest.php
```

结果：88 tests，497 assertions，0 failures / 0 errors，7 skipped；81 项实际执行通过。跳过项来自现有生命周期真实 MySQL 测试，本轮使用隔离 SQLite/临时目录，未运行生产数据库迁移。日志：/tmp/funadmin-hooks-php-final.log。

前端：

```sh
cd admin-web
npx vitest run src/shared/plugins/hooks src/admin/router/pluginModules.spec.ts tests/router/pluginModules.spec.ts src/admin/router/menu-tree.spec.ts
npm run type-check
npx vite build --mode webman --outDir /tmp/funadmin-hooks-build
```

Vitest：6 files / 50 tests 通过。类型检查与 Webman 模式生产构建通过；构建输出位于临时目录，没有替换现网静态资源。日志分别为 /tmp/funadmin-hooks-web-final.log、/tmp/funadmin-hooks-type-final.log、/tmp/funadmin-hooks-build.log。

PHP syntax：php tests/lint.php 检查 787 个 PHP 文件通过；另对最终路由归属变更文件运行 php -l 通过。工作区 git diff --check 通过。

两个前端示例复制到隔离源码工程 /tmp/funadmin-hooks-example-web，复用已安装 node_modules，类型检查和 Webman 模式构建通过；日志 /tmp/funadmin-hooks-example-type.log、/tmp/funadmin-hooks-example-build.log。

## 回归修复证据

- 上传完成动作缺失、上传响应未过滤：新测试先失败，接入后通过；包括复用文件、分块未完成/合并、动作异常、过滤器修改核心字段或异常。
- Cookie 路径：真实路由测试先 NOT_FOUND，新增 /admin 描述别名后通过；浏览器启动测试断言正确凭证与地址。
- 纯钩子安装：安装示例先失败“插件未声明 storage.path”，安装与更新仅在声明 storage 时创建目录后，通过整个生命周期。
- 通用插槽：独立代码审阅发现 keep-alive 停用和上下文更新问题，回归测试先失败后修复；包含停用期间更新注册表、迟到导入、旧 signal 与新 signal 切换。
- 描述入口归属：插件标识恰为 plugins 时，核心描述 URL 曾返回 404；明确核心控制器归属后通过，保留插件应用守卫。
- PHP/JS 同优先级排序：大小写 id 测试先得到 aA，使用与 PHP 一致的 ASCII 排序后得到 Aa。
- 管理 API 自定义前缀：/gateway/admin/ 回归测试先失败，按完整 APP_CONFIG.baseApi 组装后台描述 URL 后通过。

## 独立审阅与范围外结果

已完成 fresh-context 全功能代码审阅；有意义的插槽清理/上下文问题已修复并验证。原审阅把上下文过期列为 Minor，本轮按可能造成错误业务记录上下文的 Important 处理并修复；没有遗留本功能审阅问题。

扩大 PHPUnit 运行时曾得到 746 tests、5909 assertions、7 errors、2 failures、103 skipped（日志 /tmp/plugin-hooks-phpunit.log）：错误涉及同时开发中的 CMS SQLite utf8mb4_bin、AI 私有 symlink 校验，失败涉及 authorization 路由摘要和遗留 ThinkPHP 源码契约。本轮不把该全量结果说成通过，定向结果才是本功能完成的验证依据。现有前端旧契约中也有引用已迁移根路径的 ENOENT，未改成虚假的通过。

## 运行约定

首次新增前端插件源码后需要构建；新增/更新 PHP 文件与类需要 Webman reload。已经收录的插件启停通过激活清单及前端同步控制；其他浏览器页面的前端贡献在下次可见轮询/焦点同步后移除，间隔 30 秒，服务端后续调用立即按新激活状态判断。

默认全局入口仅在整个激活目录从未存在时允许空注册表；已存在但损坏的激活清单继续拒绝执行。组件和函数的外部副作用需要插件通过 onDispose 注销，已发生的副作用无法由 runtime 自动回滚。

本次保留当前 Rbac 工作区及现有暂存安排；没有代为合并、推送或部署。
