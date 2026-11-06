# 插件市场空配置与安装后 CMS 路由验证

日期：2026-11-05。

## 根因与修复

- 现有 PLUGIN_MARKETPLACE_URL 为空，已安装插件列表仍自动请求 /api/v3/plugins/check-updates，导致 HTTP 客户端收到不完整 URI。
- 工厂解析市场根地址并保留已有旧配置回退。根地址为空时，自动更新检查直接返回 []，不访问云会话或网络；无已安装插件也不发更新请求。
- 主动使用云市场时，传输层在网络请求之前验证完整 HTTP(S) 地址并返回含 PLUGIN_MARKETPLACE_URL 的可操作配置提示；空配置不阻止本地插件操作。
- 正常配置继续保留根路径前缀、鉴权及超时。Adapter 保留已知 MarketplaceException 的语义和状态，避免重复包装为网络错误。
- .env.example 已注明本地插件不依赖云市场地址。未修改用户实际 .env，未填写猜测的远程域名。

## CMS 页面加载失败

- 用户安装并启用了 CMS，数据库已有插件记录、站点和路由表，后台和公开 PHP 文件均已发布。
- 旧 Worker 启动于 20:57:28，CMS 文件在 21:28 发布；实际 HTTP /admin/plugin/cms/sites 为宿主 404，而新 PHP 进程可发现同一路由。
- 21:31:55 对本地开发 Webman 执行 reload，加载新路由并使市场修复生效。
- 重载后，后端与 Vite 代理下的 /admin/plugin/cms/sites 均进入正常登录校验：无 Cookie 请求为 401，已不再是接口不存在。
- 只读验证确认 cms:site:list 权限已注册、站点可查询，当前站点数为 0。用户刷新管理页面后应创建第一个站点；没有代替用户创建站点或修改权限。
- /cms/hello.html 已进入 CMS 页面处理，因未绑定匹配站点/发布页面返回 HTML 404，区别于此前宿主 JSON 404。

## 测试

- 新增 MarketplaceConfigurationTest，先观察缺失配置、无效地址、错误包装及 URL 空白的失败，再修复。
- 定向后端：MarketplaceConfigurationTest + PluginCenterIntegrationTest + PluginCenterHttpTest，14 项通过、102 次断言。
- 修改的 3 个 PHP 文件语法检查通过，git diff --check 通过。
- 实际应用配置下创建市场服务后，自动更新检查返回 []，未访问网络或修改业务数据。
- 全量后端：834 项、6344 次断言，5 个错误、3 个失败、108 项跳过。未声称全量通过。

## 全量失败名称与边界

- `Tests\Console\AiProfileImageConsumerTest::test_default_job_assembly_resolves_frozen_profile_current_key_and_private_image_before_provider_request`
- `Tests\Console\CoreDatabaseAuditTest::test_manifest_reads_only_root_baseline_declarations_without_executing_seeds`
- `Tests\Console\CoreMigrationSourceTest::test_default_source_is_repository_root_not_webman_php_migrations`
- `Tests\Console\CoreMigrationSourceTest::test_upgrade_manifest_requires_complete_signed_authoritative_core_sql`
- `Tests\Console\CoreMigrationSourceTest::test_signed_zip_is_rejected_without_core_contract_and_uses_mounted_sql_not_local_php`
- `Tests\AdminRouteInventoryTest::test_module_route_contracts_do_not_disappear_during_migration`
- `Tests\BusinessModuleLayoutTest::test_backend_application_imports_resolve_to_declared_classes`
- `Tests\LoadLangPackContractTest::test_application_runtime_contains_no_thinkphp_class_references`

其中 BusinessModuleLayoutTest 的静态扫描把已安装 CMS 的合法 PHP 分组 use 导入当成类名（例如 app\cms\service\{Input,SiteService,SiteAccess}）。冷启动路由和实际站点查询均能解析这些类；本轮未为静态扫描问题改写已发布插件的受管文件。其余错误来自已知 AI 私有目录、核心迁移路径、路由摘要和 ThinkPHP 残留。
