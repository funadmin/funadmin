# 技术文档插件验收记录

日期：2026-11-06　分支：`feat/document-plugin`（worktree `/tmp/funadmin-document-plugin`）
环境：macOS arm64，PHP 8.5.11，MySQL 26.7，Edge（Playwright headless）。验收用独立数据库 `funadmin_doc_e2e` 与独立端口（后端 8797、前端 5185），未触碰日常使用的 `funadmin_rbac`、8787、5175。

## 自动测试

| 范围 | 命令 | 结果 |
|---|---|---|
| 后端（服务、控制器、权限、版本、并发、内容安全、种子、生命周期、迁移、布局） | `cd admin && vendor/bin/phpunit tests/Document/ tests/DocumentMigrationTest.php tests/DocumentPluginLayoutTest.php` | 46 测试 / 222 断言通过 |
| 前端（内容转换与安全、store、编辑器、工作台组件、文案） | `cd web && npx vitest run tests/document/` | 35/35 通过 |
| 前端类型检查 | `cd web && node scripts/check-document.mjs` | 0 错误 |
| 插件清单 | `cd admin && php webman plugin:validate document` | 通过 |
| 插件打包 | `cd admin && php webman plugin:package document` | 生成 55 个文件的安装包 |

## 真实环境验收（通过插件中心安装）

- [x] 通过插件中心 `local/document/install` 安装：建表、注册 5 项权限、写入 10 个目录与 1 篇入门文档。
- [x] 后台菜单出现「技术文档 / 文档中心」，三栏工作台正常渲染。
- [x] 新建文档、保存草稿（含代码块、任务列表、提示块、Mermaid）、发布两个版本、查看修订、恢复第 1 版（结构完整保留）、全文搜索命中代码块内容。
- [x] 旧 `lockVersion` 保存返回 409，不覆盖内容；缺 CSRF 的写请求返回 403。
- [x] 只读角色账号：可查看列表和详情；新建、保存、发布、删除、目录管理均返回 403（服务端拦截）。
- [x] 浏览器：编辑后显示「有未保存的修改」，保存后显示「已保存」；预览中 Mermaid 渲染为图且显示节点文字；任务列表为只读勾选框；大纲可用；版本历史抽屉列出两个版本；导出 Markdown 内容正确（含 `> [!WARNING]` 与 ```` ```mermaid ````）；390px 宽度无横向滚动。
- [x] 浏览器控制台仅有宿主登录页已知的两条报错（登录前 `/auth/me` 401、验证码路由 404），与插件无关。

## 验收中发现并已修复

1. 空 `storage/` 不进入安装包，导致安装失败 → 加 `.gitkeep` 与回归测试。
2. 预览中 Mermaid 读取不到源码（属性被净化器丢弃）→ 源码改以 `<code>` 文本携带，加回归测试。
3. Mermaid 节点文字为空（HTML 标签位于 `foreignObject`，被 SVG 净化移除）→ 改用 SVG 文字标签。
4. 任务列表勾选框被净化成文本框 → 净化后依据 `data-checked` 重建只读复选框，加回归测试。

## 终审后修复（独立复审发现，均有回归测试）

1. 保存后编辑器内容被整体替换，光标跳到文末、保存途中的输入丢失 → 编辑器只在切换/恢复/重新载入/导入时替换内容。浏览器复测：自动保存后继续输入，文字接在光标处（`ABCDCasbin`）。
2. 修改「文档信息」后下一次保存绕过乐观锁 → 元数据只合并元数据，保留 lockVersion、冲突与未保存状态。
3. Markdown 导入遇到 `<?php`、`<template>`、`&&` 等会损坏内容 → 不再对 Markdown 源码做 HTML 净化，原始 HTML 只作为字面文本。
4. 发布/归档不带 lockVersion → 必须携带，旧锁返回 409；且不递增 lockVersion，正在编辑的人不会被迫丢弃修改。
5. 挂起的自动保存在发布/切换后触发，把文档改回草稿 → 定时器由 store 管理并在相关操作时取消；无修改时不发保存请求。
6. 先保存失败时仍提示「发布成功」→ 发布/归档返回真实结果，失败时提示原因。
7. 归档丢弃未保存的修改 → 归档前先保存。
8. 仅有编辑权限的人可通过编辑取消归档/发布 → 保存与恢复不再改变发布状态；新增 `has_unpublished_changes` 标记已发布文档的未发布修改。
9. 前端测试被 `web/.gitignore` 忽略 → 增加 `!/tests/document/`。

全新库重新安装后复测：旧锁发布 409；编辑已发布文档保持「已发布 + 有未发布修改」；归档后编辑仍为归档；恢复后重新发布正常。

## 已知问题（未在本次修复）

- **宿主缺陷，启用任一插件都会出现**：插件中心「启用」接口返回 500，但插件实际已启用。原因是 `admin/app/common/plugin/marketplace/MarketplaceTelemetry.php` 的 `pluginRecord()` 对 Eloquent 模型调用了 ThinkPHP 写法 `->field()->find()`（`BadMethodCallException`），来自提交 `2504215f`，不属于本插件。
- 安装后需 `php start.php reload` 才能加载新路由（安装接口返回 `rebuildRequired: true`，与 `docs/plugins.md` 一致）。
- 编辑器工具栏、文档信息对话框、版本历史抽屉的文案仍为中文；工作台主体与全部提示已支持中英文。
- 没有目录管理界面（接口与权限已就绪，目前只能使用内置目录或直接调用接口）。
- 其余延期的次要问题见执行台账：目录成环、重新启用时补回被删的内置条目、元数据后写者胜、恢复失败仍提示成功、归档提示文案与列表不一致、后端未校验节点结构、Mermaid 预览 id 冲突、未实现代码高亮/附件插入/Mermaid 源码再编辑。

## 未验收

- Windows / Linux 环境；暗色主题；屏幕阅读器。
- 大量文档（数千篇）下的列表与 `LIKE` 搜索性能。
