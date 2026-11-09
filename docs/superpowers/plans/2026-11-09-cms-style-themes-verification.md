# CMS 四套风格主题验证

2026-11-09。新增 editorial /minimal /aurora /heritage，与原四套合计八套原生 Blade 主题。

## 已通过

- EnterpriseThemesTest：9 tests /804 assertions（含全部主题、内容模型、SEO、多语言、多站过滤、分页、配置模块、资源存在、不写演示数据）。
- CMS/Blade/manifest 专项：185 tests /1706 assertions，无失败，4项环境测试跳过。
- 192个 Blade 模板全部可编译，PHP token parse通过。
- 插件 manifest 校验通过，无警告；源包、admin/app/cms、admin/public 对应模板与资源一致。
- 浏览器1440和390宽度各四主题：无横向溢出、真实内容展示、Element增强、桌面下拉、手机抽屉均通过。
- Aurora关闭JavaScript后：原生目录可打开，搜索保留GET及真实输入框。
- 审查发现的暗色控制与正文、广告、浅色页脚对比度问题已先复现再修正。Aurora8处检查最小12.42:1；各页脚和东方雅韵介绍文案最小4.54:1。
- 四套CSS在本地CMS入口均返回200。
- 本轮范围diff空白检查通过。

## 全量检查的其他失败

`COMPOSER_PROCESS_TIMEOUT=0 composer --working-dir=admin test` 已执行：1283 tests /11779 assertions，5 errors、3 failures、107 skips。

- LocalMarketplaceRemovalTest::test_public_member_auth_remains_at_its_existing_urls_without_market_classes：PHPUnit Metadata CachingParser 的 method_exists 断言。
- MemberIntegrationTest 的 public_member_tokens_rotate_once_and_reject_disabled_members、public_member_registration_uses_native_cache_without_identity_copies、public_member_profile_and_password_changes_persist_and_revoke_tokens、public_member_auth_routes_use_native_requests_and_never_accept_admin_cookie_as_member：同一PHPUnit元数据断言。
- AuthRequirementSnapshotTest::test_every_registered_route_is_covered_by_the_snapshot /test_auth_requirements_match_snapshot：会员路由快照不一致。
- BundledLanguageTest::test_catalogues_preserve_all_legacy_keys_and_have_english_placeholder_parity：语言词条快照不一致（Registered successfully）。

上述文件与会员/认证/语言改动属于其他并行工作，本轮没有修改。日志：/tmp/funadmin-style-themes-full-backend.log。

## 预览

总览：/tmp/funadmin-cms-style-themes-overview.png。各套桌面、手机、手机菜单截图位于 /tmp/funadmin-style-{code}-{desktop,mobile,mobile-menu}.png。预览通过隔离SQLite内存库生成，不写真实站点数据。
