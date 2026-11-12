# 问答社区 1.3.0

依赖 CMS >=1.6.0（会员投稿表的软删除结构与回收站公共规则）。安装、启用和迁移通过宿主插件中心执行。复用 CMS 的会员投稿、审核和站点权限，会员使用现有前台会员令牌。

前台 `/#/web/questions`，后台“问答社区 → 问答审核”与“问答社区 → 问题管理”。在 CMS 站点设置的“会员互动”中开启本站写入后，已登录会员可提问和回答，审核后公开。问题作者可采纳回答。详见仓库 `docs/cms-community.md` 的入口、权限、安装和首版限制。

## 1.1.0 软删除表结构

升级：在插件中心更新问答社区（执行迁移 `002_soft_delete.sql`）后重新启用。已安装的问答插件必须执行 002：1.1.0 的代码按 `deleted_at` 过滤问题，只替换代码而不执行迁移时问答读取会报错。002 只给 `qa_question` 加 `deleted_at DATETIME NULL` 与索引 `idx_qa_question_deleted_at`（主键不变，没有非主键唯一索引，因此不加 `deleted_token`），可以重复执行；不改列类型、不加主键，按 MySQL 8.0 在线 DDL 执行，只在开始和结束时短暂持有元数据锁。本版不新增删除功能。

## 1.2.0 问题管理、删除与回收站

升级：在插件中心更新问答社区（执行迁移 `003_question_admin.sql`）后重新启用。003 给 `qa_question` 加 `pinned`（置顶）与索引 `idx_qa_question_pinned`，新建回答状态表 `qa_answer`（主键复用 CMS 回答投稿 id，`hidden` 为后台隐藏，`deleted_at` 软删除，没有唯一索引所以不加 `deleted_token`），并为已有的 CMS 回答（问题有问答行的）各补一行状态。补行只在本次执行前 `qa_answer` 还不存在时执行（建表前记入会话变量 `@qa_answer_created`），重复执行 003（包括回答全部被彻底删除、表为空时）不会补回已彻底删除的回答。若 003 在建表之后、补行之前中断，重新执行不会再补行，需要人工补齐。003 可以重复执行，只读 CMS 数据。

后台新增“问答社区 → 问题管理”页（菜单与路由按 `qa:questions:index` 显示，与“问答审核”页 `qa:moderation:index` 分开）：

- 问题列表（置顶在前，按审核状态、标题筛选）与详情：标题、内容、提问会员、审核状态、附件、全部回答（最多显示 100 条并给出总数）。
- 详情里可以关闭/重新开放问题、置顶/取消置顶（按问题版本乐观锁，冲突返回 409），删除问题；对回答可以隐藏/取消隐藏、删除。隐藏或删除已采纳的回答会取消采纳并重新开放问题；管理员重新开放问题时同样取消采纳（关闭保留采纳），问题作者可以重新采纳。列表与回收站的回答数与详情一致：不含 CMS 投稿已删除的回答。
- 回收站分“问题”“回答”两个页签：列表、恢复、彻底删除（彻底删除需二次确认，不可撤销）。

删除规则：

- 只处理问答侧的状态行。CMS 没有提供删除会员投稿的接口，问答不删除、不恢复 CMS 投稿；彻底删除后 CMS 投稿原样保留（问答页面不再显示它）。
- 删除问题只软删除 `qa_question`，回答不级联，按问题是否存在过滤：前台与审核列表都不再显示该问题及其回答，回答回收站也不列出它们；恢复问题后回答原样回来。
- 恢复问题时对应的 CMS 投稿已删除、不存在或已不是问题投稿，返回 409；恢复回答时问题已删除（先恢复问题）或 CMS 回答投稿已删除、不存在，返回 409。409 时整批不恢复。
- 彻底删除问题同时删除它的全部回答状态行（含已删除的）；彻底删除回答只删除该回答状态行。
- 前台只显示审核通过、问答侧未删除的问题，以及审核通过、未隐藏、未删除的回答；新提交的回答同时建立状态行。

权限：“问题管理”页需要 `qa:questions:index`（读取站点列表还需要 CMS 的 `cms:sites:context`，与“问答审核”页相同）；页内各按钮按自己的权限码显示。每个接口一个权限码（`qa:questions:index|detail|status|pin|delete|recycle|restore|destroy`，`qa:answers:visibility|delete|recycle|restore|destroy`），登记在 `plugin.json`，需要分配给后台角色。站点内复用 CMS 的投稿站点权限：读取（列表、详情、回收站列表）需要 `cms:submission:list`，其余写操作需要 `cms:submission:review`。

## 1.3.0 前台 SEO 页面与会员附件

升级：在插件中心更新问答社区（执行迁移 `004_attachments.sql`）后重新启用，并为后台角色分配新权限 `qa:questions:attachment`（下载问答附件）。004 只新建会员附件表 `qa_attachment`（自增主键；`submission_id` 是附件所属的问题或回答投稿，`question_id` 是所在问题；`deleted_at` 软删除及索引，没有唯一键所以不加 `deleted_token`），不改已有表，可以重复执行。

### 前台 SEO 页面

- `GET /qa/questions`：问题列表（置顶在前，每页 20 条，`?page=N` 分页，超出范围 404；非默认语言用 `?locale=xx`，未启用的语言 404）。
- `GET /qa/questions/{id}`：问题详情（问题、公开回答、附件名称）。
- 服务端渲染（Blade：`app/qa/view/public/*.blade.php`），关闭 JS 可读。页面含 title、description、canonical、Open Graph；列表页带 `rel=prev/next`；详情页输出 schema.org `QAPage` 结构化数据：`mainEntity` 为 `Question`（`answerCount` 为公开回答数），已采纳的回答为 `acceptedAnswer`，其余公开回答为 `suggestedAnswer`。详情最多列出 100 条回答（与接口一致）；已采纳的回答排在 100 条之后时 JSON-LD 不含 `acceptedAnswer`，`answerCount` 仍为总数。
- 站点按请求域名识别，与 CMS 前台相同：未知或停用的域名 404，别名域名 301 到主域名；canonical 用站点主域名（https），不用请求的 Host。
- 只显示审核通过、问答侧与 CMS 侧都未删除的问题，以及审核通过、未隐藏、未删除的回答；问题不存在、未审核通过或已删除时 404。
- 站点域名的 Nginx 需要在 `location /`（转发到 `/cms/`）之前把问答页面转发到后端同一路径，例如：

```nginx
location ^~ /qa/questions {
    proxy_pass http://127.0.0.1:8787;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto https;
}
```

### 会员附件

- 提问（`POST /qa/api/questions`）和回答（`POST /qa/api/questions/{id}/answers`）可用 multipart 表单在 `attachments[]` 中上传图片或文件，其余字段不变。
- 复用 CMS 会员表单附件的存储：扩展名白名单（图片、文档、压缩包）与危险扩展名黑名单、MIME 嗅探、拒绝脚本内容，私有目录 `runtime/plugins-data/qa/attachments/{站点}/{年月}/`（目录 0700、文件 0600，不经过公开 URL）。
- 限制：每次最多 5 个，单个不超过 5MB，合计不超过 8MB（低于 webman 默认的 10MB 请求上限）。任何一个不合格整次提交 422，不写入任何数据；同一 `request_key` 重放不会重复保存附件；写入失败回滚时删除已落盘的文件。
- 下载：会员 `GET /qa/api/attachments/{id}?site_id=`（需要登录）。附件所属问题（及回答）在问答侧与 CMS 侧都未删除；已公开（问题审核通过，回答审核通过且未隐藏）时任何登录会员可下载，否则只有上传者本人；其他情况 404。后台 `GET /admin/plugins/qa/questions/attachment?site_id=&id=` 需要 `qa:questions:attachment` 与本站 `cms:submission:list`。响应一律 `attachment`、`nosniff`、沙箱 CSP、不缓存。
- 后台问题详情显示问题与各回答的附件，可下载。前台 SEO 页面只列出附件名称与大小，不提供下载链接。
- 软删除：删除问题或回答时，它的附件以同一删除时间软删除，恢复时一起恢复；删除问题不影响回答的附件（回答本身也不级联）。只有回收站彻底删除才删除附件记录和磁盘文件：彻底删除问题删除问题及其全部回答的附件，彻底删除回答删除该回答的附件。
