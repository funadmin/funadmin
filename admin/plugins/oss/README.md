# OSS 云存储插件

支持阿里云 OSS、腾讯云 COS、七牛云 Kodo 和 S3 兼容存储（AWS S3、MinIO、Cloudflare R2 等），安装、配置并启用后接管宿主统一公开附件上传。后台附件、CMS 正文/封面/图集、头像等继续使用原上传 API，客户端不接触云密钥。AI 会话私有附件目前仍使用其既有私有存储，不会变成公开 URL。

## 安装与配置

1. 将整个 `oss` 目录（含已构建的 `vendor/`）放到后端 `plugins/oss/`，在插件中心的本地插件中安装并启用。安装后的后端模块由发布器写入 `app/admin/plugins/oss/`，前端写入项目 `web/src/admin/plugins/oss/`。不要手工提前创建这些安装目录，以免形成未登记发布目标。
2. 前端模块沿用项目现有插件部署机制，安装后在前端项目执行 `npm run build:webman`，并按宿主要求重载 worker。
3. 宿主须有有效的 `OAUTH_ENCRYPTION_KEY`（Defuse ASCII safe key）。保管原密钥；换密钥前应完成原配置的重新加密。插件不会生成弱替代密钥。
4. 进入 `/#/admin/plugins/oss/storage` 添加配置，再设为当前存储。新启用但未配置时显示“尚未接管”，现有上传继续原方式。插件配置好当前存储后自动接管，无需修改 CMS、编辑器或附件上传地址。
5. 阿里云填写 HTTPS 官方 endpoint、Bucket 和 AccessKey；腾讯云填写带 APPID 的 Bucket、Region 和 SecretId/SecretKey；七牛填写 Bucket、区域（z0/z1/z2/na0/as0 等，支持 SDK 自动发现）和 AccessKey/SecretKey；S3 兼容存储填写 Endpoint（AWS 可留空，按 Region 使用官方地址；MinIO、R2 等必填）、Region（R2 填 auto，MinIO 一般为 us-east-1）、Bucket（S3 命名规则：小写字母、数字、点和连字符）、Access Key ID/Secret Access Key，按服务需要勾选 path-style（MinIO 等一般需要）。填写真实的 HTTP(S) 访问域名和可选安全目录前缀。云端访问权限和 CDN 由桶管理员配置，插件不会把私有桶改成公开桶。
6. “测试连接”依次执行三步并逐步返回结果：上传一个随机小对象、读回并校验内容（七牛通过管理接口比对大小与 etag，不访问公开域名）、删除测试对象。上传成功后无论校验结果如何都会尝试删除。测试使用表单当前的值，无需先保存；编辑已有配置时密钥留空沿用已保存的密钥，此时存储位置必须与已保存的一致，已保存的密钥不会被发往别的地址。每个云请求的连接与总超时为 3 秒，整个测试不超过 15 秒（接近超时时跳过读取校验，仍然删除测试对象）。三步都成功才代表真实云联通。本插件开发测试不使用真实密钥，不声称已对用户桶做过真实云验证。

## 接管、切换与旧文件

- 一次上传固定使用一次配置快照；切换只影响后续上传，普通上传和最终分片合并均适用。
- 当前存储配置保存在插件数据库中，不覆盖宿主 upload_driver。停用插件恢复宿主原选择；云存储错误明确返回失败，不自动回退 local。
- 每个配置的随机 ID 绑定一个不可变存储位置，附件 driver 使用 `oss_{16位ID}`。更换厂商、桶、地域、域名或目录时添加新配置。原配置可修改名称和凭据，并继续定位历史对象。
- 附件删除按原配置和对象 key 处理；插件停用、原驱动缺失、key 缺失或云删除失败会保留记录，不能回退到 local 删除。批次部分失败时保留失败记录并刷新列表。
- 密钥加密保存在 oss_profile；接口只返回“已设置”状态，留空表示保留旧值，不写入 config.php。配置页面按方法使用 oss:storage:index（查看）、oss:storage:save（保存）、oss:storage:activate（启用）、oss:storage:test（测试连接；测试未保存的配置即不带 id 时，还需要 oss:storage:save）、oss:storage:delete（删除）、oss:storage:recycle（查看回收站）、oss:storage:restore（恢复）、oss:storage:destroy（彻底删除）权限，权限码由控制器方法推导。
- 删除配置是软删除，配置进入回收站，可以恢复。当前存储配置不能删除（409），需先把其他配置设为当前存储。已删除配置的历史附件仍按原配置读取和删除（驱动解析包含已删除的配置）；已删除的配置不出现在驱动列表，不能设为当前存储或测试。
- 配置接口的 404/409 除中文 `msg` 外在 `data.code` 返回稳定原因：`OSS_PROFILE_NOT_FOUND`（配置不存在）、`OSS_PROFILE_NOT_IN_RECYCLE`（不在回收站）、`OSS_PROFILE_CURRENT`（当前配置不能删除）、`OSS_PROFILE_IN_USE`（仍有附件使用，不能彻底删除）；后台页面按原因显示当前界面语言的提示，未知原因显示服务端提示。
- 上传期间配置被删除：云端写入成功后、宿主写附件行之前，插件再确认配置仍存在且未删除；已删除时删除刚上传的对象，本次上传失败（提示重新上传）。宿主在驱动返回后到写入附件行之间不再回调插件，这段很短的窗口需要宿主在同一事务里加锁才能完全关闭。
- 回收站的“彻底删除”是唯一的物理删除，需要二次确认且不可撤销：只要还有附件（包括回收站中的附件）使用 `oss_<id>` 驱动就返回 409。彻底删除只删除配置行和加密凭据，不删除云端对象。插件不会自动删除任何配置；软删除的配置一直保留，直到管理员在回收站彻底删除。
- 卸载保留配置表与附件记录，不清空云对象。不提供远程 purge。重新安装可继续使用保留数据；升级通过标准版本迁移执行。
- 云与数据库之间没有分布式事务。云写入超时、落库失败且补偿删除失败时，脱敏日志保留 driver/profile/key 供人工对账清理；不输出 SDK 原始异常、签名或密钥。

## S3 兼容存储

- 供应商 `s3` 使用 aws/aws-sdk-php 的 S3Client，与其他三家一样按 `class_exists(\Aws\S3\S3Client::class)` 检测，SDK 缺失时配置显示不可用、不能启用。
- Endpoint 只接受 HTTPS（与阿里云 Endpoint 规则一致），不能带账号、查询串或路径；不接受本机、链路本地（云元数据 169.254.0.0/16）、未指定或保留的 IP，也不接受十进制/十六进制写法的数字主机名和常见元数据主机名。内网 IP 与主机名允许，便于自建 MinIO。主机名不做 DNS 解析，SDK 关闭重定向。另外显式拒绝阿里云 ECS 元数据 `100.100.100.200`、AWS IPv6 元数据 `fd00:ec2::254`、IPv4 映射地址 `::ffff:0:0/96`，以及内嵌被拒绝 IPv4 的 NAT64 地址 `64:ff9b::/96`（例如 `64:ff9b::a9fe:a9fe`）。解析到内网或元数据地址的主机名仍不会被拦截（只允许 HTTPS，元数据服务通常没有有效证书，TLS 会失败）；持有 `oss:storage:save`、`oss:storage:test` 的管理员可以让服务器向内网 HTTPS 地址发请求，只应授予可信管理员。
- 凭据只用配置里保存的静态密钥，不读取环境变量、共享配置文件或实例元数据；不重试；请求只在需要时计算校验和（兼容 MinIO、R2 等不支持新默认校验和的服务）。
- path-style 只影响访问方式、不改变对象位置，编辑时可以修改；Endpoint、Region、Bucket、域名和目录与其他厂商一样保存后不可修改。

## 依赖与开发

插件携带锁定的官方 SDK：aliyuncs/oss-sdk-php 2.7.3、qcloud/cos-sdk-v5 2.6.17、qiniu/php-sdk 7.14.0、aws/aws-sdk-php 3.403.4。开发者可在本目录使用 `composer install --no-dev --no-plugins` 重建 vendor（会运行 composer.json 中的脚本：`pre-autoload-dump` 按 `extra.aws/aws-sdk-php` 只保留 S3 服务模型，`tools/patch-vendor.php` 修正七牛 SDK 的隐式 nullable），安装端无需运行 Composer。宿主仍优先加载已有公共依赖，按插件中心的 Composer 冲突检测处理。

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

## 1.2.0 配置管理、S3 兼容驱动与连接测试

升级：在插件中心更新 OSS（执行迁移 `003_s3_path_style.sql`）后重新启用。003 只给 `oss_profile` 加 `path_style TINYINT(1) NOT NULL DEFAULT 0`，已有配置得到 0；`oss_setting` 不变。003 按 information_schema 守卫，可以重复执行，按 MySQL 8.0 在线 DDL 执行。新增配置删除、回收站（恢复、彻底删除）、S3 兼容供应商和逐步返回结果的连接测试，见上文。权限变化：测试未保存的配置（不带 `id`，可填写任意 Endpoint 与凭据）除 `oss:storage:test` 外还需要 `oss:storage:save`，缺少时返回 403；只测试已保存的配置（带 `id`）仍只需要 `oss:storage:test`。原来只授予测试权限、需要测试新配置的角色，请同时授予保存权限。`id` 不是字符串（例如数组）时，保存与测试接口返回 422。

## 1.1.0 软删除表结构

升级：在插件中心更新 OSS（执行迁移 `002_soft_delete.sql`）后重新启用。已安装的 OSS 插件必须执行 002：1.1.0 的代码按 `deleted_at` 过滤配置，只替换代码而不执行迁移时读取配置会报错。002 只给 `oss_profile` 加 `deleted_at DATETIME NULL` 与索引 `idx_oss_profile_deleted_at`；字符串主键 `id` 不变（附件 driver `oss_<id>` 与当前配置仍按它引用），没有非主键唯一索引，因此不加 `deleted_token`。`oss_setting` 是单行设置表，不加软删除。002 可以重复执行；不改列类型、不加主键，按 MySQL 8.0 在线 DDL 执行，只在开始和结束时短暂持有元数据锁。1.1.0 未新增删除功能（1.2.0 起提供，见上文）。
