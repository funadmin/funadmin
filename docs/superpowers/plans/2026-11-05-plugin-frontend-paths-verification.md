# 插件前端路径验证

## 本轮调整

- 保留另一会话已确定的根部署 `/`、hash 路由和 `admin/public/` 构建输出。
- 插件历史包和开发包下载链接使用 `VITE_APP_BASE_API`，支持相对、绝对及带尾斜杠的 API base。
- 开发包响应中的下载地址按当前 API 配置归一化，保留审计信息和哈希等原有字段。
- Vite 代理 `/plugin-assets/` 到后端，按路径段匹配，不误代理页面和构建资产。
- 验证根路径及子目录部署下插件页面的实际浏览器 URL。
- 并行 Hook 会话已修复完整管理 API 前缀，保留其实现并覆盖回归。

## 结果

- 9 个相关测试文件、49 项测试通过。
- 独立只读审查未发现重要问题；审查方另行运行的 12 项测试通过。
- `npm run build:webman` 通过，包含 `vue-tsc --noEmit`；产物输出到 `admin/public/`，构建保留该目录中的其它文件。
- `git diff --check` 通过。
- 前端全量：923 项，806 通过、117 失败，60 个测试文件失败。与先前 116 个失败的基线相比，新增的是并行 CMS 工作中的 `tests/cms/content.spec.ts > publishes the revision the editor loaded and keeps its optimistic lock`；本轮路径相关用例全部通过。旧失败明细见之前验证记录。

## 当前默认地址

- 后端开发配置：`http://127.0.0.1:8787`。
- 后台登录：`/#/admin/login`。
- 插件页面示例：`/#/admin/plugin/demo/items`。
- 插件接口示例：`/admin/plugin/demo/index`。
- 插件公开资源：`/plugin-assets/demo/public/…`。

未修改后台业务目录、执行数据库迁移或重载后端进程。保留其它会话的根部署、Hook 和 CMS 修改。
