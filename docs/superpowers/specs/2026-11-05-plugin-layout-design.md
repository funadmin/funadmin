# 插件目录统一

> 后台目录已由 `2026-11-05-plugin-sibling-directories-design.md` 更新为 `app/admin/plugins/{code}`。当前完整规范见 `docs/plugins.md`。

用户明确要求源包和安装目录统一，删除旧结构。此要求取代上一轮保留源包简写目录及旧发布记录兼容的决定。

插件包内所有可发布源码按项目相对路径组织：

- `app/admin/plugin/business/{code}/{layer}` → 后端项目相同相对路径。
- `app/{code}` → 后端项目相同相对路径。
- `admin-web/src/admin/modules/{code}` → 配置的前端项目 `src/admin/modules/{code}`。
- `public/plugin-assets/{code}/public` → 后端公开目录 `plugin-assets/{code}/public`。

`plugin.json`、`Plugin.php`、`config.php`、`database/migrations` 和 `storage` 保持元数据、生命周期、迁移和运行存储职责，不属于发布源码重定位。权限与 URL 保持上一轮 Webman 规则。

采用直接统一目录的方案，删除旧目录、旧资源标识、旧命名空间的兼容分支；旧包及旧资源登记明确拒绝，不自动搬移、不静默删除旧文件。原子发布、文件归属、哈希校验、失败恢复继续保留。不得覆盖用户其他改动或操作生产数据库。
