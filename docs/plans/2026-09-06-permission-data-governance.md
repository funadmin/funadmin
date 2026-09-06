# 权限资源表数据治理执行记录

> 状态：**已执行**。已落 forward-only 迁移 `053_permission_resource_tree_repair.sql` 并登记 checksum。
> 执行前快照：2026-09-06，fun_permission 207 行 / fun_admin_menu 相关 4 行。
> 背景：旧英文种子区（id 1-178）已被并行迁移删除，但删除后留下孤儿与残留脏数据。

## 一、孤儿节点挂回（4 行）

旧根节点 `Sys`(id=1) 已删除，以下节点 pid=1 悬空：

| id | 名称 | 处置 |
|---|---|---|
| 192 | 系统管理 | 挂到新根 `Console 系统`（新建 group，pid=0） |
| 179 | 字典管理 | 同上 |
| 220 | 个人资料 | 同上 |
| 260 | 上传文件 | 改挂 283（附件管理）——它属于附件 code 家族 |

## 二、乱码修复（2 行）

| 表 | id | 现值 | 修复为 |
|---|---|---|---|
| fun_permission | 321 | `æ¸…é™¤æ'ä»¶æ•°æ®` | 清除插件数据 |
| fun_admin_menu | 23 | `ç®¡çå‘˜ç®¡ç` | 管理员管理 |

## 三、英文/表名命名统一为中文（菜单 2 行 + 权限 14 行）

菜单：

| id | 现值 | 修复为 |
|---|---|---|
| 20 | Dictionary | 字典管理 |
| 40 | fun_blacklist | 黑名单（CRUD 生成） |

权限（generated 家族，code 前缀 `generated:blacklist:`）：

| id | act | 修复为 |
|---|---|---|
| 393（group） | - | 黑名单（CRUD 生成） |
| 394 | list | 查看列表 |
| 395 | detail | 查看详情 |
| 396 | create | 新增 |
| 397 | update | 编辑 |
| 398 | status | 修改状态 |
| 399 | delete | 删除 |
| 400 | restore | 恢复 |
| 401 | destroy | 彻底删除 |
| 402 | batch-delete | 批量删除 |
| 403 | batch-restore | 批量恢复 |
| 404 | batch-destroy | 批量彻底删除 |
| 405 | import | 导入 |
| 406 | export | 导出 |

## 四、插件家族命名微调（1 行）

| id | 现值 | 修复为 | 理由 |
|---|---|---|---|
| 313 | 编辑 | 更新插件 | code=system:plugin:update，与 372 语义对齐 |

## 五、不在本次范围（建议项，另立）

1. **plugin code 双家族**：前端按钮门控用 `system:plugin:*`（310-321），后端路由鉴权用 `console/systemplugin:*`（356-385）。建议前端 perm 指令统一到 `console/systemplugin:*` 后删除 310-321 家族——涉及前端 vue 文件改动，单独立项。
2. 根节点数量（Console 系统/插件中心/CRUD 生成器/系统升级/黑名单生成 = 5 根）是否合并，看你偏好，本次不动。

## 六、验证结果

- 权限聚焦契约 7/7 通过；
- 数据库实测：孤儿节点 0、上传权限父级为附件管理、generated blacklist 14 个名称均非纯英文；
- 菜单 20/23/40 已统一为中文；
- 迁移 checksum 与 `fun_system_migration` 登记一致；
- 后端引导接口返回 200。
