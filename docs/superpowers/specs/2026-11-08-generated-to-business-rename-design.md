# 代码生成产物目录 generated → business 改名

日期：2026-11-08　状态：用户已确认　用户决定：名称用 `business`；**全部迁移，不考虑兼容性（开发阶段）**

## 意图
用户觉得 `generated` 不顺眼，希望生成的业务模块统一叫 `business`。代价已告知（权限、菜单、文件、生成记录需迁移），用户确认改名。

## 改名范围（新生成与运行时）
| 项 | 现在 | 改为 |
|---|---|---|
| 后端目录 / 命名空间 | `app/admin/generated/{dir}/…` · `app\admin\generated\{dir}\…` | `app/admin/business/{dir}/…` · `app\admin\business\{dir}\…` |
| 后端测试 / SQL | `tests/generated/` · `database/generated/` | `tests/business/` · `database/business/` |
| 前端页面 / 接口模块 / 测试 | `web/src/admin/views/generated/` · `api/generated/` · `web/tests/generated/` | `views/business/` · `api/business/` · `web/tests/business/` |
| 后台接口前缀 | `/admin/generated/{entity}` | `/admin/business/{entity}` |
| 菜单路由 / 组件 | `/generated/{entity}` · `component=generated/{entity}/index` | `/business/{entity}` · `component=business/{entity}/index` |
| 权限码 | `generated:{entity}:{method}` | `business:{entity}:{method}` |
| 自定义动作注册键 | `generated.{entity}.{key}` | `business.{entity}.{key}` |
| 发布配置默认模块名 | `module: 'generated'`、apiPrefix/routePath `/generated/{entity}` | `business`、`/business/{entity}` |

不改（内部标识，用户看不到，改动只增加迁移风险）：权限/菜单行的 `source_type='generated'`、`GeneratedFileBaseline` 等类名与表名、语言包类 `GeneratedLanguagePacks` 的类名（其中按目录读取的路径要改）。CMS 等插件里与「生成列」相关的 `generated` 字样无关，不动。
不冲突确认：`app/admin/` 下无 `business` 目录；`web/src/admin/views/business` 与已有的 `views/development/business`（代码生成页面）是不同路径；权限前缀 `business:` 与已有 `development:business:*` 不冲突。

## 已有模块（用户决定：不做兼容与升级迁移）
- 不提供数据库升级迁移、文件迁移命令、旧路由重定向，也不保留 `generated` 的任何识别分支（PermissionCode、ArtifactPaths、路由识别等只认 `business`）。
- 本仓库：`i18n-demo` 示例文件直接移到 `views/business/`、`api/business/` 并改引用；用户生成的 `order` 模块删除旧生成文件与开发库里旧的菜单/权限/生成记录后，用新代码重新正式生成。
- 开发库（funadmin_rbac）的清理由控制者在改名完成后执行，执行前备份。

## 约束
- 冲突保护不放宽。
- 「现有模块生成结果不变」的约束在本次改名中按设计失效，但除 generated→business 的替换外，生成内容不得有其他差异（哈希基线按替换后的内容更新）。
- 不提交；与并行会话（CMS 等）冲突的文件先停下确认。

## 测试
- 后端：路径/命名空间/权限码/注册键的生成测试；`PermissionCode::derive` 只认 business；已有快照与哈希基线按替换更新。
- 前端：路由识别、预览文件分组、自定义动作注册键与「待生成」判断、mock。
- 全量后端/前端测试、类型检查；浏览器打开迁移后的 order 页面、执行自定义动作。
