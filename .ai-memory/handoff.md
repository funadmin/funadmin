# 当前清理检查点

## 已完成
- 会员管理、会员等级、会员组、多语言、权限资源、黑名单已迁移到 Admin Web，并下线对应旧 Layui 管理入口。
- 附件库与附件分组已迁移；旧独立导航已删除，`sys.attach/selectfiles` 及其附件/分组写操作因配置与插件表单依赖而保留。
- 配置管理与配置分组已迁移；旧 Controller、视图、JS、权限与菜单已删除；`Config`/`ConfigGroup` 模型及 `syscfg()` 运行时读取保留。
- Admin Web 最新构建产物已输出到 `public/admin-web`。

## 验证证据
- `npm test`: 5 个测试文件、28 个用例全部通过。
- `npm run type-check`: 通过。
- `npm run build`: 2672 modules transformed，构建成功。
- PHP 8.4 lint: 131 个 `app/**/*.php` 全部通过。
- PHP 8.4 HTTP: `/backend/upload`、附件/附件分组、配置/配置分组接口未登录均返回 401 JSON。
- 旧配置入口引用归零；旧附件/分组导航移除且文件选择器兼容调用保留。

## 未完成 / 下一步
1. 插件管理：涉及云市场、本地 ZIP、部署替换、数据库迁移、配置钩子、启停和卸载；需先设计可回滚的生命周期 API，再迁移。
2. 系统升级：涉及远程版本、备份、文件覆盖和数据库操作；需单独做生产就绪与回滚门禁，不可直接删除旧入口。
3. 文件选择器：需将 FormBuilder 的 `sys.attach/selectfiles` 替换为 Admin Web/新组件后，才能删除旧 `Attach`/`AttachGroup` Controller、`selectfiles.html`、`attach.js` 和 `attach_group/add.html`。

## 当前剩余旧导航
- 插件管理 `addon`
- 系统升级 `sys.upgrade`

# 2026-09-05 迁移链写冲突协调单（title→name 后续：legacy 时间列删除）

## 背景
- 用户要求：时间字段统一 datetime 三件套（created_at/updated_at/deleted_at），删除旧 create_time/update_time/delete_time。
- 022 已完成三件套切换与回填；运行时代码（app/、extend/、admin-web）对旧 int 三件套引用为零。
- 本会话产出 `028_drop_legacy_time_columns.sql`（62 列守卫式 DROP + 7 个时间索引重建到 created_at）与契约 `admin-web/tests/contracts/legacy-time-columns-drop.spec.ts`；隔离 Docker MySQL 全链 001→028 通过、重跑幂等。两文件已被提交后又被删除（git status 可见 D），可用 `git restore` 恢复。

## live 库（funadmin_git）当前不一致状态
- 迁移仓库 fun_system_migration 仅登记到 021（+012/013/015）；016–027 效果大多已在 live 但未登记（016 非幂等、019 效果经 011 存在）。
- fun_languages/fun_provinces 的旧时间列已被手工删除，导致已提交版 017 无法再跑完（其 INSERT..SELECT 仍读旧列）。
- 017 被中断应用过一次：fun_admin.real_name/last_login_ip 已加列并回填（与其意图一致，无害）。
- 011/013/020 等已执行迁移正被修改：将触发 MigrationService「已执行 migration 内容变化」拒绝，需回滚这些文件或改走新迁移。

## 恢复步骤（单一迁移写者就位后）
1. `git restore database/migrations/028_drop_legacy_time_columns.sql admin-web/tests/contracts/legacy-time-columns-drop.spec.ts`
2. 回滚对已执行迁移（011/013/020 等）的修改，或将其改动改写到新编号迁移。
3. 按序补齐 live：017 剩余部分需先修复其对 languages/provinces 旧列的依赖（改读 created_at 或守卫跳过），再 018→027 幂等应用；016/019 仅登记。
4. 登记 016–028 checksum 后应用 028；核对旧列计数=0、fun_admin_log 时间索引位于 created_at。

# 2026-09-05 完成：legacy int 时间列删除（034）与迁移链收口
- 034_drop_legacy_time_columns.sql 已落地：62 列守卫式 DROP + 7 个时间索引重建到 created_at；live 已应用并登记（仓库 33 条 core）。
- live 补齐登记：014/016/018/019/020/022/023/024/025/026/027/029/030/031/034；其中 016/019/022 仅登记（效果早已存在且脚本非幂等）。
- 双态兼容改造（跨 015 改名/034 删除的迁移必须同时兼容新旧列）：014_user_management_menu、033_crud_workbench（未跟踪 WIP，含 title→name 与 legacy 列补全）；017 此前已改造。
- 未登记保留：032/033（未跟踪 WIP，重跑幂等，由插件会话自行登记）。
- 验证：隔离 MySQL 全链 001→034 通过、034 重跑幂等；契约 23/23 通过；live 旧列计数 0（fun_plugin_resource.create_time 为 031 新建 datetime 列，属设计保留）。

# 2026-09-05 收尾：迁移仓库 checksum 一致性修复
- 审计发现 010/011_admin_web_menu_icons/025 磁盘内容偏离登记 checksum（21:45/21:53 提交修改了已执行迁移，违反不可变约束），已从 git 历史 blob 回滚至登记内容（740a2984/444c395f/1ca38544）。
- 009_user_management_menu、011_member_group_icon 登记在库但文件已删：MigrationService 仅遍历磁盘文件，属无害残留；其效果分别由已登记的 014/019 承接。
- 复核：全登记 checksum 一致；隔离全链 001→034（含 038/039 前全部文件）通过、034 重跑幂等、legacy 计数 0；PHP lint 全过、PHP 测试全过、Vitest 57 文件 264 用例全过、vue-tsc 退出 0。
- 提醒：后续任何对已登记迁移文件的修改都会阻断升级（"已执行的 migration 内容发生变化"），新需求一律走新编号迁移。
