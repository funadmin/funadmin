# OSS 云存储插件

支持阿里云 OSS、腾讯云 COS、七牛云 Kodo，安装、配置并启用后接管宿主统一公开附件上传。后台附件、CMS 正文/封面/图集、头像等继续使用原上传 API，客户端不接触云密钥。AI 会话私有附件目前仍使用其既有私有存储，不会变成公开 URL。

## 安装与配置

1. 将整个 `oss` 目录（含已构建的 `vendor/`）放到后端 `plugins/oss/`，在插件中心的本地插件中安装并启用。安装后的后端模块由发布器写入 `app/admin/plugins/oss/`，前端写入项目 `web/src/admin/modules/oss/`。不要手工提前创建这些安装目录，以免形成未登记发布目标。
2. 前端模块沿用项目现有插件部署机制，安装后在前端项目执行 `npm run build:webman`，并按宿主要求重载 worker。
3. 宿主须有有效的 `OAUTH_ENCRYPTION_KEY`（Defuse ASCII safe key）。保管原密钥；换密钥前应完成原配置的重新加密。插件不会生成弱替代密钥。
4. 进入 `/#/admin/plugin/oss/storage` 添加配置，再设为当前存储。新启用但未配置时显示“尚未接管”，现有上传继续原方式。插件配置好当前存储后自动接管，无需修改 CMS、编辑器或附件上传地址。
5. 阿里云填写 HTTPS 官方 endpoint、Bucket 和 AccessKey；腾讯云填写带 APPID 的 Bucket、Region 和 SecretId/SecretKey；七牛填写 Bucket、区域（z0/z1/z2/na0/as0 等，支持 SDK 自动发现）和 AccessKey/SecretKey。填写真实的 HTTP(S) 访问域名和可选安全目录前缀。云端访问权限和 CDN 由桶管理员配置，插件不会把私有桶改成公开桶。
6. “测试连接”会写入并删除一个随机测试对象，结果成功才代表真实云联通。本插件开发测试不使用真实密钥，不声称已对用户桶做过真实云验证。

## 接管、切换与旧文件

- 一次上传固定使用一次配置快照；切换只影响后续上传，普通上传和最终分片合并均适用。
- 当前存储配置保存在插件数据库中，不覆盖宿主 upload_driver。停用插件恢复宿主原选择；云存储错误明确返回失败，不自动回退 local。
- 每个配置的随机 ID 绑定一个不可变存储位置，附件 driver 使用 `oss_{16位ID}`。更换厂商、桶、地域、域名或目录时添加新配置。原配置可修改名称和凭据，并继续定位历史对象。
- 附件删除按原配置和对象 key 处理；插件停用、原驱动缺失、key 缺失或云删除失败会保留记录，不能回退到 local 删除。批次部分失败时保留失败记录并刷新列表。
- 密钥加密保存在 oss_profile；接口只返回“已设置”状态，留空表示保留旧值，不写入 config.php。配置页面按方法使用 oss:storage:index（查看）、oss:storage:save（保存）、oss:storage:activate（启用）、oss:storage:test（测试连接）权限，权限码由控制器方法推导。
- 卸载保留配置表与附件记录，不清空云对象。首版不提供 profile 物理删除和远程 purge。重新安装可继续使用保留数据；升级通过标准版本迁移执行。
- 云与数据库之间没有分布式事务。云写入超时、落库失败且补偿删除失败时，脱敏日志保留 driver/profile/key 供人工对账清理；不输出 SDK 原始异常、签名或密钥。

## 依赖与开发

插件携带锁定的官方 SDK：aliyuncs/oss-sdk-php 2.7.3、qcloud/cos-sdk-v5 2.6.17、qiniu/php-sdk 7.14.0。开发者可在本目录使用 `composer install --no-dev --no-scripts --no-plugins` 重建 vendor，安装端无需运行 Composer。宿主仍优先加载已有公共依赖，按插件中心的 Composer 冲突检测处理。

七牛 SDK 7.14.0 在 PHP 8.5 下存在上游隐式 nullable 参数弃用提示（Config 构造函数）；不影响已验证的调用协议，但开发环境可能显示该提示。没有修改 SDK 源码或全局关闭弃用报告。七牛删除使用 SDK 的认证和 HTTP 原语设置有限超时，因为 BucketManager::delete 没有暴露超时参数。

宿主新增通用 storage.upload.resolve、storage.driver.resolve、storage.drivers 过滤扩展点；宿主不依赖 OSS 厂商 SDK。上传及删除请求沿用宿主认证、CSRF、格式、大小、分片、水印和附件钩子。

从项目根目录验证：

```sh
admin/vendor/bin/phpunit -c admin/phpunit.xml admin/tests/Oss
cd web
npm test -- --run tests/oss
node scripts/check-oss.mjs
node scripts/build-oss.mjs
```

开发 ZIP 默认无可信签名，不能绕过插件中心的上传包签名校验。可从本地源码安装，或使用插件打包命令和部署方自己的签名密钥产生可信分发包。
