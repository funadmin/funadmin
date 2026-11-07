# 默认开发命令的登录代理修复

日期：2026-11-07。

## 复现与原因

通过 Turbo 当前窗口确认用户访问 `http://127.0.0.1:5175/#/admin/login?redirect=/admin/dashboard`。点击已填写表单的登录按钮后，界面显示“请求失败”。未查看或修改密码。

- 当前前端进程从 `web/` 运行不带 `--mode` 的 Vite。
- `package.json` 的默认 `dev` 是 `vite`，没有加载 `.env.webman` 中的 API 代理；当前项目没有其它提供代理的默认模式配置。
- 对 `5175/admin/auth/csrf` 的实际请求返回 HTTP 200、`text/html`（SPA 页面），无法得到登录需要的 JSON/CSRF。
- 后端 `8787/admin/auth/csrf` 同时返回 HTTP 200、`application/json`、code200，说明初始化接口可达，前端代理是这次已复现的阻断位置。

## 改动

- `npm run dev` / `pnpm dev` 默认执行 `vite --mode webman`；`dev:force` 同样加载 Webman 模式。
- README 说明默认模式、代理地址及 `.env.webman.local` 覆盖方式。
- 没有通过关闭鉴权、CSRF 或修改密码绕过问题。
- 仅替换当前 FunAdmin `5175` 开发实例，显式使用同一端口；后端及其它项目进程未动。

## 验证

- 新增隔离的真实 Vite 启动测试：临时目录包含独立 `.env.webman` 与模拟 HTTP 后端，执行默认 dev 命令对应参数。修改前收到 `text/html`，测试 RED；修改后收到准确的 JSON 响应，测试 GREEN。测试不依赖真实数据库或用户登录凭据。
- 默认启动代理与登录/安装守卫：2 个文件、11 项通过。日志 `/tmp/login-default-proxy-green.log`。
- 实际 `5175/admin/auth/csrf` 修复后返回 HTTP200 JSON、code200，包含 CSRF，captchaEnabled=false；未输出令牌或 Cookie。
- Vite 启动输出确认 `webman` 模式，监听 `127.0.0.1:5175`。
- 原浏览器窗口随后无法读取，复核时报 cgWindowNotFound，未声称完成该窗口中的凭据登录或进入后台。
- 前端全量日志：`/tmp/login-default-proxy-full.log`，JSON `/tmp/login-default-proxy-full.json`；最终结果在完成后追加。

未提交或推送；未重载后端，未改账号、密码、数据库结构或云配置。

## 全量结果

前端共 1472 项，1472 通过、0 失败、0 跳过。
