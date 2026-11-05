# 插件前端路径收敛

用户要求适配另一会话的前端根路径部署改动。保留已选定的 Vite base `/`、hash 路由、同端口构建输出 `webman-admin/public/` 及保留已有公开文件的构建方式。

插件源码仍放在 `admin-web/src/admin/modules/{code}`，页面为 `/#/admin/plugin/{code}/…`，管理 API 默认 `/admin/plugin/{code}/…`，公开资源 `/plugin-assets/{code}/…`。本次不修改后台业务目录。

用共享 API URL 拼接函数处理下载链接的管理 API base；管理端 Hook 请求的完整 API base 已由并行会话修正，保留该实现并验证回归。公开及会员 Hook 端点继续基于后端 origin，与前端资产 base 独立。开发服务器代理插件公开资源，同时保持段边界，避免误代理 SPA 和构建资产。

通过真实路由 href、配置 URL 和代理规则验证根部署与自定义部署 base。保留其它会话的代码和配置，不执行数据库迁移或后端重载。
