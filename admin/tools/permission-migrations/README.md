# 按方法权限迁移工具

- `gen-plugin-sql.php` / `gen-host-sql.php`：按 `database/permission-maps/` 中的冻结映射生成 MySQL 核心迁移
  `025_plugin_permission_per_method.sql`、`027_host_permission_per_method.sql`（与 PHP 迁移 034/036 等价）。
  这两个文件已随 2026-11-06 的基线 squash 归档到 `database/core/migrations/archive/baseline-2026-11-06/`，结果已包含在新基线中；
  工具与 `verify-mysql.sh` 现指向归档链，仅用于追溯旧库升级行为。
  迁移已在环境中执行后不要再覆盖；`tests/PermissionMigrationSqlTest.php` 检查冻结部分与脚本输出一致。
- `verify-mysql.sh`：在一次性 MySQL 8.4 容器中执行迁移链，写入升级前状态（每个旧权限一个角色、插件菜单、停用的旧权限），
  025/027 各执行两次后逐个比较 (角色, 接口) 能否访问，并检查 CMS 站点操作、菜单改绑与停用状态。需要 podman 或 docker。
