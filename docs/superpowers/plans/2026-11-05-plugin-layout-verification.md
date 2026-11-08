# 插件目录统一验证

> 历史验证快照。当前同级 `plugin/plugins` 结构及公共模型验证见 `2026-11-05-plugin-sibling-directories-verification.md`。

当前规范：`docs/plugins.md`。

## 已验证行为

- 脚手架、CRUD、业务生成白名单、Manifest、ZIP 和安装发布使用相同的项目相对路径。
- 后台为 `app/admin/plugin/business/{code}`，前端为 `web/src/admin/plugins/{code}`，静态资源为 `public/plugin-assets/{code}/public`。
- 旧后台、前端、静态资源及迁移目录被拒绝，包括没有相应 Manifest 声明的旧物理目录。
- 旧资源登记、旧恢复日志及旧恢复上下文在文件或注册表恢复前被拒绝；原文件、登记和恢复材料保持原状。
- 新目录的普通回滚和持久备份人工恢复继续有效。
- ZIP 保留空模型、视图等目录，重复打包产物一致。
- 发布内容逐文件与源包一致，资源登记的 `source_path` 与源包相对路径一致，包括 macOS 临时目录经过 realpath 的情况。
- 旧迁移目录不再参与业务表归属判断。

## 测试

- 后台目录、打包、CRUD、恢复、生命周期、插件中心和业务生成相关回归：135 项，128 通过、7 项 MySQL 环境测试跳过，1499 次断言。
- 当前工作区 PHP 语法检查：783 个文件通过。
- 前端全量：887 项，771 通过、116 失败；失败名称集合与上一轮基线一致，没有新增。原有失败明细见上一轮验证记录。
- 本机没有 Docker，真实 MySQL DDL 专项没有执行。未运行生产数据库迁移，未向服务发送重载信号。

## 独立审查

独立只读审查发现旧恢复日志、未声明旧物理目录、旧迁移扫描三处遗漏，均通过先复现后修复的回归测试处理。另补充了空目录打包和持久备份恢复验证。

工作区存在并行开发的 CMS 和 Hook 改动，保留这些修改，未回退其接口或文件。

## 全量后台快照

最后一次全量发现了 763 项测试：1 个错误、3 个失败、103 项跳过。并行修改期间测试数量持续增加，记录以该次运行时的工作区为准。

- `Tests\Console\AiProfileImageConsumerTest::test_default_job_assembly_resolves_frozen_profile_current_key_and_private_image_before_provider_request`：基线已有的私有目录符号链接错误。
- `Tests\AdminRouteInventoryTest::test_module_route_contracts_do_not_disappear_during_migration`：基线已有的 authorization 路由摘要差异。
- `Tests\LoadLangPackContractTest::test_application_runtime_contains_no_thinkphp_class_references`：基线已有的插件之外的 ThinkPHP 引用。
- `Tests\PluginHookDescriptorTest::test_exact_audience_permissions_and_no_application_required`：并行 Hook 工作中的空对象/空数组断言在全量运行后已由其修改方更新；单独重跑该文件 3 项、9 次断言全部通过。

本轮目录统一相关的最后一次回归为 135 项、1499 次断言，除 7 项 MySQL 环境跳过外全部通过。未对上述无关基线问题扩大修改范围。
