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
