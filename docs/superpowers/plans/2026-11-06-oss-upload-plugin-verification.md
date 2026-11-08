# OSS 全局上传插件验证

日期：2026-11-06。

## 交付

- 源码：`admin/plugins/oss/`，包含 Manifest、生命周期、加密配置服务、三云官方 SDK、后台管理页面与迁移。
- 页面：安装并启用后为 `/#/admin/plugin/oss/storage`；接口 `/admin/plugin/oss/storage`。
- 开发包：`admin/runtime/plugin-packages/oss-1.0.0.zip`，约 1.3 MiB、992 个文件，包含独立 vendor。
- SHA-256：`2a099342ba408218809812b15189313b42d5ab12b02d5cb9856159ccd408c3df`。
- 使用标准 PluginArchiveService 打包并通过 PluginPackageService 暂存/Manifest/源树检查后丢弃暂存。开发包没有部署方的可信签名，不可绕过上传包签名校验；当前本地源码可在插件中心安装。发布分发包应使用部署方自己的签名密钥。
- 未在当前应用创建未登记的 `app/admin/plugins/oss` 或 `web/src/admin/plugins/oss` 目录，避免阻断后续原生安装。真实发布/移除在隔离项目中验证。

## 已验证行为

- 当前云配置接管统一 UploadService，后台/CMS/编辑器等保持原 API；普通与分片最终写入使用同一入口。私有 AI 附件保留原私有存储，未公开化。
- 每次最终上传固定一个配置实例，贯穿去重、SDK 写入、数据库登记与失败补偿。新上传读新配置，停用插件恢复宿主原选择。
- `oss_{profile ID}` 保存不可变位置，切换当前配置不改变历史文件归属；缺驱动/缺 key 不回退 local 删除，云删除失败保留失败记录，成功组与失败组分别报告并刷新前端列表。
- 云存储失败不转成本地成功。云与 DB 之间不承诺分布式原子性；不确定上传/补偿清理的日志只包含事件、配置/driver 与对象 key，不暴露原始 SDK 异常。
- 密钥加密落库，接口不返回凭据，空值保留，完全更换凭据可恢复不可读的旧密文；行锁保护同一配置的并发修改。审计日志脱敏实际 AccessKey/SecretKey 字段。
- 阿里云、腾讯云和七牛协议调用、URL 编码、对象 key、删除幂等、有限超时、失败脱敏和 source 文件保留已覆盖。包括 SDK 实际腾讯序列化、七牛 Auth 签名/token 策略测试，不连接真实桶。
- 上传 MIME 由服务端检测，只有匹配的有效位图以内联图片类型保存，其它类型按 octet-stream；支持的 SDK 同时标记 attachment。七牛 token 强制 `detectMime=-1`，避免自动嗅探恢复不安全的 HTML MIME。
- 远程写入前应用现有图片水印；本地水印和失败清理继续正常。
- 原生安装、未配置启用、停用、升级、卸载、重装与加密 profile 保留已在隔离项目验证。卸载不清空云文件，不提供远程 purge 或 profile 物理删除。

## 最终检查

- `vendor/bin/phpunit`（admin）：1047 项、7458 次断言，113 项环境跳过、1 项 SDK 上游弃用提示，没有错误或失败，退出码 0。日志 `/tmp/oss-full-backend-reviewed.log`。
- `npm test -- --reporter=json --outputFile=/tmp/oss-full-frontend.json`（web）：1359 项全部通过。JSON `/tmp/oss-full-frontend.json`，日志 `/tmp/oss-full-frontend.log`。并行会话已修复此前 9 项路由/CLI 契约失败，最终快照无失败。
- OSS 页面专项：2 个测试文件、12 项通过。日志 `/tmp/oss-front-final.log`。
- OSS、宿主上传/删除与审计专项：106 项、442 次断言通过，1 项 SDK 弃用提示；其后增加的密钥恢复专项通过，最终完整后台再次覆盖了它。日志 `/tmp/oss-complete-scoped.log`、`/tmp/oss-recovery-green.log`。
- 云驱动专项：64 项、255 次断言通过，含实际 SDK 协议与 MIME 用例。
- `node scripts/check-oss.mjs`、`node scripts/build-oss.mjs` 通过，检查插件源码类型并独立生产编译。日志 `/tmp/oss-types2.log`、`/tmp/oss-front-build2.log`。
- `npm run build:webman` 通过，包含宿主类型检查与生产构建。日志 `/tmp/oss-host-build.log`。
- 本轮 22 个 PHP 源码/测试文件语法检查及 `git diff --check` 通过。
- 设计审查与最终独立只读审查完成，最终无未处理 Important/Critical 问题。

## 验证边界

真实阿里云/腾讯云/七牛上传与删除需要用户的测试桶凭据，尚未执行。没有运行当前生产数据库的插件迁移、安装或启用，没有主动重载服务，没有提交或推送代码。MySQL SQL 由已测试 Schema builder 导出，未在真实 MySQL 上执行。

七牛 SDK 7.14.0 在 PHP 8.5 存在上游 `Config.php:44` 隐式 nullable 参数弃用提示；不影响本轮协议测试，但保留在结果中。未修改 vendor 源码，未全局屏蔽弃用报告。
