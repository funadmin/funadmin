# 会员投稿与问答首版

CMS 1.3.0 提供会员投稿、本人记录、撤回和后台审核。QA 1.0.0 是依赖 CMS 的独立插件，提供公开问题列表、详情、会员提问/回答及问题作者采纳。投稿、问题和回答首次提交均为待审核状态。

## 入口

`web` 中的 CMS 投稿前台已移除，不再提供 `/#/web/community`。CMS 官网继续使用插件内的 PHP 模板；会员投稿后端与后台审核能力保留，可由自定义前台接入。

独立问答前台保留 `/#/web/questions`（列表）与 `/#/web/questions/{id}`（详情），页面为 `web/src/web/views/QuestionsPortal.vue`，接口客户端为 `web/src/web/api/questions.ts`。个人中心保留问答入口。页面可选择站点和语言；链接可带 `?site_id=7&locale=zh-cn`。

后台 CMS → 站点管理 → 会员互动控制本站是否接受投稿与问答；CMS → 会员投稿审核文章；问答社区 → 问答审核处理问题与回答。会员互动默认关闭，管理员需要为每个站点明确开启。

CMS 导航管理配置的是网站前台菜单。可以添加外部链接指向前台门户的完整 URL，例如 `https://portal.example.test/#/web/questions?site_id=7&locale=zh-cn`。CMS 的独立服务端主题域名与 Vue 门户入口分别部署，不会自动在每个 CMS 域名上提供门户页面。

## 安装及升级

1. 核心系统需已完成安装，再安装 CMS 1.3.0。CMS 只有一个迁移 `001_cms.sql`（2026-11-07 由原 001–003 合并，包含站点角色与会员投稿表），只支持全新安装，不再提供从旧版 CMS 追加迁移的升级路径。
2. 在本地插件列表安装并启用 `qa` 1.0.0。它要求 CMS >=1.3.0，执行自己的 `001_qa.sql`。使用正式插件生命周期，让宿主同时发布 PHP、管理端源码、权限菜单和可信激活快照。
3. 在 `web/` 执行 `npm run build:webman`，产物直接写入 `admin/public/`。按部署方式重载 Webman worker，再刷新后台菜单。
4. 为审核员分配宿主能力及对应站点角色的 `cms:submission:list`、`cms:submission:review`；发布文章还需 `cms:content:create` 与 `cms:content:publish`。问答审核额外需要宿主的 `qa:moderation:list`、`qa:moderation:review`。现有角色不会自动获得新增权限。
5. 在目标站点的“会员互动”标签页启用功能，选择允许投稿的模型。停用写入不影响公开读取已经审核发布的问答。

独立前端域名部署可运行 `npm run build:standalone`。后台 `/admin` 接口依赖登录 Cookie，**永不返回 CORS 头**，因此独立前端必须用反向代理把 `/admin`、`/api`、`/cms/member`、`/qa/api` 转发到后端，使浏览器看到同一 origin，`VITE_APP_BASE_API` 保持 `/admin`。只有会员/开放 API（`/api/*`，Bearer 令牌）支持跨域：在 `admin/.env` 的 `CORS_ALLOWED_ORIGINS` 中列出允许的精确 origin（逗号分隔；留空即不允许跨域；不支持通配符；不发送 `Access-Control-Allow-Credentials`）。问答与 CMS 会员接口如也需要跨域，在 `admin/.env` 的 `CORS_PATHS` 中加入 `/qa/api/`、`/cms/member/` 前缀（默认只有 `/api/`；`/admin` 前缀始终被忽略）。同域部署使用默认 `/admin`。问答仍通过 CMS 获取站点、会员身份和本人记录，因此开发服务继续代理 `/cms/member` 和 `/qa/api` 到 `VITE_APP_PROXY_TARGET`。

## 数据与状态

`cms_member_submission` 保存投稿、问题、回答的共同记录，会员身份与管理员审核身份分开存储。`qa_question` 保存问题的采纳答案、关闭状态和版本。成员使用会员 API v2（`/api/v2/token`）签发的 bearer；后台登录 Cookie 不作为会员身份。字符串 ID 用于所有新接口，避免前端大整数丢失精度。

待审核内容可发布或退回；作者可撤回自己的待审核/退回记录；已发布内容由管理员下架。文章发布到 CMS 内容系统，地址为 `/community/articles/{submissionId}`。采纳仅限问题作者，回答须已审核且属于同站点、同语言、同问题；采纳后关闭新回答。下架已采纳答案会清除采纳并重新开放问题。

审核和采纳使用版本比较，过期页面返回冲突并提示刷新。提交必须带请求编号，相同编号和内容重试返回既有记录，内容变化返回冲突。每会员每站点的提交量受限。公开接口只返回审核发布后的安全字段。

## 首版范围

投稿支持文本、长文本、数字、布尔、选择字段。包含图片、文件、图库字段的模型暂不开放给会员，包括字段可选的模型；产品/下载模型如含这些字段也会被排除。原因是现有全局附件编号不能证明会员拥有附件。会员附件上传、编辑已投稿内容、积分、投票、悬赏、通知及问答服务端 SEO 页面后续提供。问题详情最多展示 100 个回答，并返回回答总数。

## 验证

PHP 专项：`cd admin && php vendor/bin/phpunit tests/Cms tests/Qa`。前端专项：`cd web && npm test -- tests/cms`，并运行 `node scripts/check-cms.mjs`、`node scripts/build-cms.mjs`。

`MySqlCommunityUpgradeTest` 仅接受名称匹配 `funadmin_cms_test_[a-f0-9]+` 的临时库，使用 `CMS_MYSQL_DATABASE` / `CMS_MYSQL_SOCKET` 指定。测试覆盖追加升级、已有数据/授权保留、重复迁移、大小写敏感请求编号和自定义表前缀；不要指向业务数据库。
