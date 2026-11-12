<?php

declare(strict_types=1);

namespace FunAdmin\Documentation;

use OpenApi\Annotations as OA;

/**
 * 后台与会员 API 接口文档公共模型；仅供文档扫描，不参与业务运行。
 *
 * @OA\Info(title="FunAdmin 后台接口", version="1.0.0", description="路由文档版本。普通后台、浏览器认证及令牌认证采用各自响应信封。")
 * @OA\Server(url="/", description="当前部署地址")
 * @OA\SecurityScheme(
 *     securityScheme="AdminSession",
 *     type="apiKey",
 *     in="cookie",
 *     name="WEBMAN_ADMIN_SESSION",
 *     description="通过 /admin/auth/csrf 建立预登录会话，再调用 /admin/auth/login；业务接口需已登录会话。"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="CsrfToken",
 *     type="apiKey",
 *     in="header",
 *     name="X-CSRF-TOKEN",
 *     description="由 /admin/auth/csrf 或响应头获取；与同一浏览器会话配套使用。"
 * )
 * @OA\SecurityScheme(securityScheme="AdminBearer", type="http", scheme="bearer", description="通过 /api/auth/login 获取的不透明管理员令牌，不是 JWT。")
 * @OA\SecurityScheme(securityScheme="MemberBearer", type="http", scheme="bearer", bearerFormat="JWT", description="通过 /api/v2/token 获取的会员 access token（JWT，携带会话标识 sid）；会话被登出或因 refresh 重放被吊销后立即失效。")
 * @OA\Tag(name="表单运行数据")
 * @OA\Tag(name="OIDC 签名密钥")
 * @OA\Tag(name="身份审计日志")
 * @OA\Tag(name="OAuth 客户端")
 * @OA\Tag(name="OIDC 会话")
 * @OA\Tag(name="单点登录配置")
 * @OA\Tag(name="统一身份用户")
 * @OA\Tag(name="OAuth Scope")
 * @OA\Tag(name="企业应用")
 * @OA\Tag(name="业务开发模块")
 * @OA\Tag(name="示例插件入口")
 * @OA\Tag(name="示例插件测试记录")
 * @OA\Tag(name="插件市场统计")
 * @OA\Tag(name="市场插件")
 * @OA\Tag(name="插件市场配置")
 * @OA\Tag(name="插件市场订单")
 * @OA\Tag(name="插件市场授权")
 * @OA\Tag(name="插件市场分类")
 * @OA\Tag(name="插件中心")
 * @OA\Tag(name="插件开发工具")
 * @OA\Tag(name="国际化示例记录")
 * @OA\Tag(name="会员等级")
 * @OA\Tag(name="会员")
 * @OA\Tag(name="仪表盘")
 * @OA\Tag(name="操作日志")
 * @OA\Tag(name="系统配置")
 * @OA\Tag(name="IP 黑名单")
 * @OA\Tag(name="附件")
 * @OA\Tag(name="存储驱动")
 * @OA\Tag(name="通用文件上传")
 * @OA\Tag(name="系统升级")
 * @OA\Tag(name="附件分组")
 * @OA\Tag(name="会员组")
 * @OA\Tag(name="数据字典")
 * @OA\Tag(name="语言")
 * @OA\Tag(name="AI 配置档案")
 * @OA\Tag(name="AI 开发")
 * @OA\Tag(name="角色")
 * @OA\Tag(name="权限资源")
 * @OA\Tag(name="管理员")
 * @OA\Tag(name="后台菜单")
 * @OA\Tag(name="部门")
 * @OA\Tag(name="管理员个人资料")
 * @OA\Tag(name="管理员令牌认证")
 * @OA\Tag(name="后台浏览器认证")
 * @OA\Tag(name="会员 API 令牌", description="会员 API v2 令牌：登录签发、一次性轮换刷新、登出。")
 * @OA\Tag(name="会员 API 资料", description="会员 API v2 当前会员资料。")
 * @OA\Tag(name="插件前端钩子", description="插件前端钩子描述，供前台、会员中心与后台加载插件扩展。")
 * @OA\Tag(name="安装向导", description="安装向导接口：不依赖数据库和后台认证，以安装 Cookie 与 X-CSRF-TOKEN 请求头配对校验。")
 * @OA\Tag(name="CMS 站点", description="CMS 插件后台：站点、语言、域名、站点配置与模型绑定。")
 * @OA\Tag(name="CMS 站点权限", description="CMS 插件后台：站点角色、成员及成员回收站。")
 * @OA\Tag(name="CMS 栏目", description="CMS 插件后台：栏目。")
 * @OA\Tag(name="CMS 内容", description="CMS 插件后台：内容、修订、附件与预览。")
 * @OA\Tag(name="CMS 投稿审核", description="CMS 插件后台：会员投稿审核与投稿策略。")
 * @OA\Tag(name="CMS 自定义表单", description="CMS 插件后台：自定义表单、提交跟进与回收站。")
 * @OA\Tag(name="CMS 导航", description="CMS 插件后台：导航及导航回收站。")
 * @OA\Tag(name="CMS 模型", description="CMS 插件后台：内容模型。")
 * @OA\Tag(name="CMS 模板标签", description="CMS 插件后台：模板标签。")
 * @OA\Tag(name="CMS 前台页面", description="CMS 插件前台：按请求域名识别站点的服务端渲染页面。")
 * @OA\Tag(name="CMS 公开表单", description="CMS 插件前台：自定义表单提交、令牌与验证码。")
 * @OA\Tag(name="CMS 会员投稿", description="CMS 插件会员接口：使用会员 API v2 access token 识别会员。")
 * @OA\Tag(name="文档目录", description="文档插件后台：目录及目录回收站。")
 * @OA\Tag(name="文档管理", description="文档插件后台：文档、草稿、发布、修订及回收站。")
 * @OA\Tag(name="文档反馈", description="文档插件后台：读者反馈。")
 * @OA\Tag(name="文档阅读", description="文档插件前台：公开阅读页、静态资源与读者反馈。")
 * @OA\Tag(name="云存储配置", description="OSS 插件后台：云存储配置、连接测试及配置回收站。")
 * @OA\Tag(name="问答问题管理", description="问答插件后台：问题列表、详情、附件、关闭、置顶、删除及回收站。")
 * @OA\Tag(name="问答回答管理", description="问答插件后台：回答隐藏、删除及回收站。")
 * @OA\Tag(name="问答审核", description="问答插件后台：问题与回答的投稿审核。")
 * @OA\Tag(name="问答前台页面", description="问答插件前台：服务端渲染的问题列表与详情（SEO 页面）。")
 * @OA\Tag(name="问答会员接口", description="问答插件前台接口：浏览公开问答；提问、回答、采纳及附件下载需要会员 API v2 access token。")
 *
 * @OA\Schema(schema="DynamicObject", type="object", description="业务字段随模型、插件或已发布 Schema 变化，以元数据和服务实际返回为准。", additionalProperties=true)
 *
 * @OA\Schema(schema="DynamicValue", description="结构由具体业务、插件或已发布 Schema 决定；不推断未声明的字段。")
 *
 * @OA\Schema(
 *     schema="AdminEnvelope",
 *     type="object",
 *     required={"code", "msg", "time", "data"},
 *     @OA\Property(property="code", type="integer", description="业务码：200 为成功；普通后台校验错误可能在 HTTP 200 响应中以 code=400/422 返回。", example=200),
 *     @OA\Property(property="msg", type="string", description="业务提示", example="操作成功"),
 *     @OA\Property(property="time", type="integer", description="Unix 时间戳，单位秒。", example=0),
 *     @OA\Property(property="data", description="结构由具体业务、插件或已发布 Schema 决定；不推断未声明的字段。")
 * )
 *
 * @OA\Schema(
 *     schema="BrowserAuthEnvelope",
 *     type="object",
 *     required={"code", "msg", "data"},
 *     @OA\Property(property="code", type="integer", description="与 HTTP 状态一致，成功为 200。", example=200),
 *     @OA\Property(property="msg", type="string", example="成功"),
 *     @OA\Property(property="data", description="结构由具体业务、插件或已发布 Schema 决定；不推断未声明的字段。")
 * )
 *
 * @OA\Schema(
 *     schema="TokenAuthEnvelope",
 *     type="object",
 *     required={"code", "message", "data"},
 *     @OA\Property(property="code", type="integer", description="成功为 0，失败为 HTTP 错误码。", example=0),
 *     @OA\Property(property="message", type="string", example="登录成功"),
 *     @OA\Property(property="data", description="结构由具体业务、插件或已发布 Schema 决定；不推断未声明的字段。")
 * )
 *
 * @OA\Schema(
 *     schema="PageData",
 *     type="object",
 *     required={"list", "total", "page", "pageSize"},
 *     @OA\Property(property="list", type="array", @OA\Items(ref="#/components/schemas/DynamicObject")),
 *     @OA\Property(property="total", type="integer", minimum=0),
 *     @OA\Property(property="page", type="integer", minimum=1),
 *     @OA\Property(property="pageSize", type="integer", minimum=1)
 * )
 *
 * @OA\Schema(
 *     schema="ListTotalData",
 *     type="object",
 *     required={"list", "total"},
 *     @OA\Property(property="list", type="array", @OA\Items(ref="#/components/schemas/DynamicObject")),
 *     @OA\Property(property="total", type="integer", minimum=0)
 * )
 *
 * @OA\Schema(
 *     schema="CursorPageData",
 *     type="object",
 *     required={"items", "has_more", "next_cursor"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/DynamicObject")),
 *     @OA\Property(property="has_more", type="boolean"),
 *     @OA\Property(property="next_cursor", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="LoginInput",
 *     type="object",
 *     required={"username", "password"},
 *     @OA\Property(property="username", type="string", description="管理员账号", minLength=3, maxLength=20, example="admin"),
 *     @OA\Property(property="password", type="string", description="管理员密码；不记录或返回明文。", format="password", writeOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="AdminCreateInput",
 *     type="object",
 *     required={"username", "nickname", "password"},
 *     @OA\Property(property="username", type="string", minLength=3, maxLength=20, pattern="^[A-Za-z][A-Za-z0-9_]{2,19}$", example="adminuser"),
 *     @OA\Property(property="nickname", type="string", minLength=1, maxLength=50),
 *     @OA\Property(property="email", type="string", description="允许空字符串；非空时必须为有效邮箱且最多 60 字节。", maxLength=60),
 *     @OA\Property(property="mobile", type="string"),
 *     @OA\Property(property="password", type="string", description="12 至 72 字节，不能包含 NUL。", format="password", writeOnly=true),
 *     @OA\Property(property="status", type="integer", enum={0, 1}),
 *     @OA\Property(property="deptId", type="integer", minimum=0),
 *     @OA\Property(property="departmentIds", type="array", description="记录 ID 集合；部分 CRUD 接口也兼容逗号分隔字符串。", @OA\Items(type="integer", minimum=1)),
 *     @OA\Property(property="roleIds", type="array", description="记录 ID 集合；部分 CRUD 接口也兼容逗号分隔字符串。", @OA\Items(type="integer", minimum=1))
 * )
 *
 * @OA\Schema(
 *     schema="AdminUpdateInput",
 *     type="object",
 *     description="缺省字段保留原值；超级管理员只更新 nickname/email/mobile。",
 *     @OA\Property(property="nickname", type="string", minLength=1, maxLength=50),
 *     @OA\Property(property="email", type="string", description="允许空字符串；非空时必须为有效邮箱且最多 60 字节。", maxLength=60),
 *     @OA\Property(property="mobile", type="string"),
 *     @OA\Property(property="status", type="integer", enum={0, 1}),
 *     @OA\Property(property="deptId", type="integer", minimum=0),
 *     @OA\Property(property="departmentIds", type="array", description="记录 ID 集合；部分 CRUD 接口也兼容逗号分隔字符串。", @OA\Items(type="integer", minimum=1)),
 *     @OA\Property(property="roleIds", type="array", description="记录 ID 集合；部分 CRUD 接口也兼容逗号分隔字符串。", @OA\Items(type="integer", minimum=1))
 * )
 *
 * @OA\Schema(
 *     schema="RoleInput",
 *     type="object",
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="level", type="integer"),
 *     @OA\Property(property="dataScope", type="string", description="由角色授权服务校验可用的数据范围。"),
 *     @OA\Property(property="remark", type="string"),
 *     @OA\Property(property="status", type="integer", enum={0, 1}),
 *     @OA\Property(property="parentId", type="integer", minimum=0),
 *     @OA\Property(property="parentRoleIds", type="array", description="记录 ID 集合；部分 CRUD 接口也兼容逗号分隔字符串。", @OA\Items(type="integer", minimum=1)),
 *     @OA\Property(property="departmentIds", type="array", description="记录 ID 集合；部分 CRUD 接口也兼容逗号分隔字符串。", @OA\Items(type="integer", minimum=1))
 * )
 *
 * @OA\Schema(
 *     schema="RoleAuthorizationInput",
 *     type="object",
 *     description="完整授权保存：缺省 permissionIds/fieldPermissions/departmentIds 按空集合处理。",
 *     @OA\Property(property="permissionIds", type="array", description="记录 ID 集合；部分 CRUD 接口也兼容逗号分隔字符串。", @OA\Items(type="integer", minimum=1)),
 *     @OA\Property(
 *         property="fieldPermissions",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="fieldId", type="integer"),
 *             @OA\Property(property="view", type="boolean"),
 *             @OA\Property(property="edit", type="boolean")
 *         )
 *     ),
 *     @OA\Property(property="dataScope", type="string"),
 *     @OA\Property(property="departmentIds", type="array", description="记录 ID 集合；部分 CRUD 接口也兼容逗号分隔字符串。", @OA\Items(type="integer", minimum=1))
 * )
 *
 * @OA\Schema(
 *     schema="DepartmentInput",
 *     type="object",
 *     @OA\Property(property="parentId", type="integer", minimum=0),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="leader", type="string"),
 *     @OA\Property(property="phone", type="string"),
 *     @OA\Property(property="email", type="string"),
 *     @OA\Property(property="sort", type="integer", minimum=0),
 *     @OA\Property(property="status", type="integer", enum={0, 1})
 * )
 *
 * @OA\Schema(
 *     schema="PermissionInput",
 *     type="object",
 *     @OA\Property(property="parentId", type="integer", minimum=0),
 *     @OA\Property(property="appName", type="string"),
 *     @OA\Property(property="resourceType", type="string", description="由权限模型校验资源类型。"),
 *     @OA\Property(property="object", type="string"),
 *     @OA\Property(property="action", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="status", type="integer", enum={0, 1}),
 *     @OA\Property(property="isPublic", type="integer", enum={0, 1}),
 *     @OA\Property(property="sort", type="integer", minimum=0)
 * )
 *
 * @OA\Schema(
 *     schema="MenuInput",
 *     type="object",
 *     description="自定义菜单配置；受管菜单由生成器或插件维护。",
 *     @OA\Property(property="parentId", type="integer", minimum=0),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="path", type="string"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="routeName", type="string"),
 *     @OA\Property(property="component", type="string"),
 *     @OA\Property(property="redirect", type="string"),
 *     @OA\Property(property="permissionId", type="integer", minimum=0),
 *     @OA\Property(property="icon", type="string"),
 *     @OA\Property(property="sort", type="integer", minimum=0),
 *     @OA\Property(property="hidden", type="boolean"),
 *     @OA\Property(property="keepAlive", type="boolean"),
 *     @OA\Property(property="affix", type="boolean")
 * )
 *
 * @OA\Schema(
 *     schema="MemberInput",
 *     type="object",
 *     @OA\Property(property="username", type="string", minLength=2, maxLength=80),
 *     @OA\Property(property="mobile", type="string", minLength=6, maxLength=20),
 *     @OA\Property(property="email", type="string", description="允许空字符串，非空时按邮箱格式及 60 字节上限校验。", maxLength=60),
 *     @OA\Property(property="sex", type="string", enum={"0", "1", "2"}),
 *     @OA\Property(property="groupIds", type="array", minItems=1, maxItems=32, @OA\Items(type="integer", minimum=1)),
 *     @OA\Property(property="tagIds", type="array", maxItems=32, @OA\Items(type="integer", minimum=1)),
 *     @OA\Property(property="levelId", type="integer", minimum=1),
 *     @OA\Property(property="avatar", type="string", maxLength=255),
 *     @OA\Property(property="status", type="integer", enum={0, 1})
 * )
 *
 * @OA\Schema(
 *     schema="MemberGroupInput",
 *     type="object",
 *     @OA\Property(property="name", type="string", minLength=1, maxLength=50),
 *     @OA\Property(property="icon", type="string", maxLength=50),
 *     @OA\Property(property="status", type="integer", enum={0, 1})
 * )
 *
 * @OA\Schema(
 *     schema="MemberLevelInput",
 *     type="object",
 *     @OA\Property(property="name", type="string", minLength=1, maxLength=30),
 *     @OA\Property(property="amount", type="string", description="金额字符串，最多两位小数。", pattern="^[0-9]{1,8}(?:[.][0-9]{1,2})?$"),
 *     @OA\Property(property="discount", type="integer", minimum=0, maximum=100),
 *     @OA\Property(property="thumb", type="string", maxLength=255),
 *     @OA\Property(property="status", type="integer", enum={0, 1}),
 *     @OA\Property(property="sort", type="integer", minimum=0),
 *     @OA\Property(property="description", type="string", maxLength=200)
 * )
 *
 * @OA\Schema(
 *     schema="ConfigInput",
 *     type="object",
 *     @OA\Property(property="code", type="string", maxLength=30),
 *     @OA\Property(property="group", type="string", maxLength=80),
 *     @OA\Property(property="type", type="string", maxLength=30),
 *     @OA\Property(property="verify", type="string", maxLength=30),
 *     @OA\Property(property="value", description="结构由具体业务、插件或已发布 Schema 决定；不推断未声明的字段。"),
 *     @OA\Property(property="extra", type="string", maxLength=255),
 *     @OA\Property(property="remark", type="string", maxLength=100),
 *     @OA\Property(property="status", type="integer", enum={0, 1})
 * )
 *
 * @OA\Schema(
 *     schema="ConfigGroupInput",
 *     type="object",
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="status", type="integer", enum={0, 1})
 * )
 *
 * @OA\Schema(
 *     schema="BlacklistInput",
 *     type="object",
 *     @OA\Property(property="ip", type="string", description="由黑名单模型校验来源地址。"),
 *     @OA\Property(property="remark", type="string"),
 *     @OA\Property(property="status", type="integer", enum={0, 1})
 * )
 *
 * @OA\Schema(
 *     schema="AttachmentGroupInput",
 *     type="object",
 *     @OA\Property(property="parentId", type="integer", minimum=0),
 *     @OA\Property(property="name", type="string", minLength=1, maxLength=100),
 *     @OA\Property(property="thumb", type="string", maxLength=255),
 *     @OA\Property(property="status", type="integer", enum={0, 1}),
 *     @OA\Property(property="sort", type="integer", minimum=0)
 * )
 *
 * @OA\Schema(
 *     schema="ScopeInput",
 *     type="object",
 *     description="创建时 name 必填；更新时缺省字段保留原值。",
 *     @OA\Property(property="name", type="string", maxLength=128, pattern="^[a-z][a-z0-9._:-]{0,127}$"),
 *     @OA\Property(property="description", type="string", maxLength=255),
 *     @OA\Property(property="claims", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="status", oneOf={@OA\Schema(type="boolean"), @OA\Schema(type="integer", enum={0, 1})})
 * )
 *
 * @OA\Schema(
 *     schema="DevPluginInput",
 *     type="object",
 *     required={"name", "title"},
 *     @OA\Property(property="name", type="string", description="插件 code。"),
 *     @OA\Property(property="title", type="string", description="插件显示名称。"),
 *     @OA\Property(property="application", type="boolean", default=true),
 *     @OA\Property(property="admin", type="boolean", default=true),
 *     @OA\Property(property="adminWeb", type="boolean", default=true)
 * )
 *
 * @OA\Schema(
 *     schema="FormSchema",
 *     type="object",
 *     description="表单规范文档；字段、布局和扩展属性由 FormSchemaValidator 校验。",
 *     additionalProperties=true,
 *     @OA\Property(property="schemaVersion", type="integer", description="表单协议版本。"),
 *     @OA\Property(property="key", type="string"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="nodes", type="array", @OA\Items(ref="#/components/schemas/DynamicObject")),
 *     @OA\Property(property="relations", type="array", description="模型级关联；只影响正式生成的模型代码，动态发布与运行时忽略。为空时不出现在规范文档中。", @OA\Items(ref="#/components/schemas/FormSchemaRelation"))
 * )
 *
 * @OA\Schema(
 *     schema="FormSchemaRelation",
 *     type="object",
 *     required={"type", "name", "table", "localField", "foreignField"},
 *     additionalProperties=false,
 *     @OA\Property(property="type", type="string", enum={"hasOne", "hasMany", "belongsToMany"}),
 *     @OA\Property(property="name", type="string", description="关联方法名，不得与字段名重复。"),
 *     @OA\Property(property="table", type="string", description="对方表名。"),
 *     @OA\Property(property="localField", type="string", description="本表字段（id 或已声明字段）。"),
 *     @OA\Property(property="foreignField", type="string", description="对方表字段。"),
 *     @OA\Property(
 *         property="pivot",
 *         type="object",
 *         description="仅 belongsToMany 必填，其他类型不得出现。",
 *         required={"table", "localKey", "foreignKey"},
 *         additionalProperties=false,
 *         @OA\Property(property="table", type="string"),
 *         @OA\Property(property="localKey", type="string"),
 *         @OA\Property(property="foreignKey", type="string")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PublishConfig",
 *     type="object",
 *     description="发布配置仅接受 PUBLISH_CONFIG_KEYS 声明的字段，值必须为标量或 null。",
 *     additionalProperties=true,
 *     @OA\Property(property="module", type="string"),
 *     @OA\Property(property="apiPrefix", type="string"),
 *     @OA\Property(property="routePath", type="string"),
 *     @OA\Property(property="parentSourceName", type="string"),
 *     @OA\Property(property="menuName", type="string"),
 *     @OA\Property(property="icon", type="string"),
 *     @OA\Property(property="formMode", type="string"),
 *     @OA\Property(property="dataScopeField", type="string"),
 *     @OA\Property(property="memberApiOwnerField", type="string"),
 *     @OA\Property(property="menuEnabled", type="boolean"),
 *     @OA\Property(property="softDeletes", type="boolean"),
 *     @OA\Property(property="batchDelete", type="boolean"),
 *     @OA\Property(property="import", type="boolean"),
 *     @OA\Property(property="export", type="boolean"),
 *     @OA\Property(property="dataScopeEnabled", type="boolean"),
 *     @OA\Property(property="memberApiEnabled", type="boolean"),
 *     @OA\Property(property="parentId", type="integer"),
 *     @OA\Property(property="sortOrder", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="FormWriteInput",
 *     type="object",
 *     description="data 的字段及必填条件来自当前表单运行元数据。",
 *     additionalProperties=true,
 *     @OA\Property(property="data", ref="#/components/schemas/DynamicObject"),
 *     @OA\Property(property="schemaHash", type="string", description="当前已发布 Schema 哈希。"),
 *     @OA\Property(property="include", type="array", @OA\Items(type="string"))
 * )
 *
 * @OA\Schema(
 *     schema="ModelCapability",
 *     type="object",
 *     required={"model"},
 *     @OA\Property(property="model", type="string"),
 *     @OA\Property(property="reasoning_efforts", type="array", @OA\Items(type="string", enum={"low", "medium", "high", "xhigh", "max", "ultra"})),
 *     @OA\Property(property="output_token_parameter", type="string", enum={"max_tokens", "max_completion_tokens"}),
 *     @OA\Property(property="context_window", type="integer", nullable=true),
 *     @OA\Property(property="max_output_tokens", type="integer", nullable=true),
 *     @OA\Property(property="image_input", type="boolean"),
 *     @OA\Property(property="image_tokens", type="integer", enum={32768}),
 *     @OA\Property(property="max_images", type="integer", minimum=1, maximum=4),
 *     @OA\Property(property="image_mime_types", type="array", @OA\Items(type="string", enum={"image/png", "image/jpeg", "image/webp"}))
 * )
 *
 * @OA\Schema(
 *     schema="AiProfileCreateInput",
 *     type="object",
 *     required={"name", "provider", "protocol", "base_url", "model"},
 *     additionalProperties=false,
 *     @OA\Property(property="name", type="string", maxLength=100),
 *     @OA\Property(property="provider", type="string", maxLength=64),
 *     @OA\Property(property="protocol", type="string", enum={"openai-chat", "openai-responses", "anthropic-messages"}),
 *     @OA\Property(property="base_url", type="string", format="uri", maxLength=2048),
 *     @OA\Property(property="model", type="string", maxLength=200),
 *     @OA\Property(property="api_key", type="string", description="省略保留密钥，空字符串清空，非空加密替换。", format="password", writeOnly=true, maxLength=8192),
 *     @OA\Property(property="enabled", type="boolean", default=true),
 *     @OA\Property(property="favorite_models", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="fallback_enabled", type="boolean", default=false),
 *     @OA\Property(
 *         property="fallback_models",
 *         type="array",
 *         maxItems=100,
 *         description="有序备用模型列表；启用 fallback_enabled 时必须为 1 至 3 个。",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(
 *         property="reasoning_effort",
 *         type="string",
 *         enum={"low", "medium", "high", "xhigh", "max", "ultra", "default"},
 *         nullable=true,
 *         description="兼容值 default 按 null 处理；显式档位需逐模型声明能力。"
 *     ),
 *     @OA\Property(property="context_window", type="integer", minimum=1, maximum=10000000, nullable=true),
 *     @OA\Property(property="max_input_tokens", type="integer", minimum=1, maximum=10000000, nullable=true),
 *     @OA\Property(property="max_output_tokens", type="integer", minimum=1, maximum=10000000, nullable=true),
 *     @OA\Property(property="max_iterations", type="integer", minimum=1, maximum=100, default=10),
 *     @OA\Property(property="stream_usage", type="boolean", default=false),
 *     @OA\Property(property="connect_timeout", type="integer", minimum=1, maximum=30, default=5),
 *     @OA\Property(property="request_timeout", type="integer", minimum=1, maximum=300, default=60),
 *     @OA\Property(property="max_retries", type="integer", minimum=0, maximum=3, default=2),
 *     @OA\Property(property="model_capabilities", type="array", maxItems=100, @OA\Items(ref="#/components/schemas/ModelCapability"))
 * )
 *
 * @OA\Schema(
 *     schema="AiProfileUpdateInput",
 *     type="object",
 *     description="PATCH：只替换提交字段，未提交字段保留；密钥遵循 api_key 的保留和清空规则。",
 *     additionalProperties=false,
 *     @OA\Property(property="name", type="string", maxLength=100),
 *     @OA\Property(property="provider", type="string", maxLength=64),
 *     @OA\Property(property="protocol", type="string", enum={"openai-chat", "openai-responses", "anthropic-messages"}),
 *     @OA\Property(property="base_url", type="string", format="uri", maxLength=2048),
 *     @OA\Property(property="model", type="string", maxLength=200),
 *     @OA\Property(property="api_key", type="string", description="省略保留密钥，空字符串清空，非空加密替换。", format="password", writeOnly=true, maxLength=8192),
 *     @OA\Property(property="enabled", type="boolean"),
 *     @OA\Property(property="favorite_models", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="fallback_enabled", type="boolean"),
 *     @OA\Property(
 *         property="fallback_models",
 *         type="array",
 *         maxItems=100,
 *         description="有序备用模型列表；启用 fallback_enabled 时必须为 1 至 3 个。",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(
 *         property="reasoning_effort",
 *         type="string",
 *         enum={"low", "medium", "high", "xhigh", "max", "ultra", "default"},
 *         nullable=true,
 *         description="兼容值 default 按 null 处理；显式档位需逐模型声明能力。"
 *     ),
 *     @OA\Property(property="context_window", type="integer", minimum=1, maximum=10000000, nullable=true),
 *     @OA\Property(property="max_input_tokens", type="integer", minimum=1, maximum=10000000, nullable=true),
 *     @OA\Property(property="max_output_tokens", type="integer", minimum=1, maximum=10000000, nullable=true),
 *     @OA\Property(property="max_iterations", type="integer", minimum=1, maximum=100),
 *     @OA\Property(property="stream_usage", type="boolean"),
 *     @OA\Property(property="connect_timeout", type="integer", minimum=1, maximum=30),
 *     @OA\Property(property="request_timeout", type="integer", minimum=1, maximum=300),
 *     @OA\Property(property="max_retries", type="integer", minimum=0, maximum=3),
 *     @OA\Property(property="model_capabilities", type="array", maxItems=100, @OA\Items(ref="#/components/schemas/ModelCapability"))
 * )
 *
 * @OA\Schema(
 *     schema="AiMessagePart",
 *     type="object",
 *     required={"type"},
 *     description="text 类型提供 text；attachment 类型提供当前会话所属的 attachment_id。",
 *     @OA\Property(property="type", type="string", enum={"text", "attachment"}),
 *     @OA\Property(property="text", type="string"),
 *     @OA\Property(property="attachment_id", type="integer", minimum=1)
 * )
 *
 * @OA\Schema(
 *     schema="AiMessageInput",
 *     type="object",
 *     required={"content", "idempotency_key"},
 *     @OA\Property(property="role", type="string", enum={"user"}),
 *     @OA\Property(property="content", type="array", @OA\Items(ref="#/components/schemas/AiMessagePart")),
 *     @OA\Property(property="idempotency_key", type="string", description="必填；每条逻辑消息提交稳定键，重试时复用以保证幂等。", pattern="^[A-Za-z0-9_-]{1,128}$")
 * )
 *
 * @OA\Schema(
 *     schema="AiApprovalInput",
 *     type="object",
 *     required={"action", "nonce", "digest", "casVersion", "mode"},
 *     @OA\Property(property="action", type="string", enum={"approve", "reject"}),
 *     @OA\Property(property="scope", type="string"),
 *     @OA\Property(property="nonce", type="string"),
 *     @OA\Property(property="digest", type="string"),
 *     @OA\Property(property="casVersion", type="integer"),
 *     @OA\Property(property="mode", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="AiPrivateAttachment",
 *     type="object",
 *     description="私有附件元数据，不公开 storage_path。",
 *     additionalProperties=true,
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="conversation_id", type="integer"),
 *     @OA\Property(property="kind", type="string", enum={"text", "image"}),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="mime", type="string"),
 *     @OA\Property(property="size", type="integer"),
 *     @OA\Property(property="width", type="integer", nullable=true),
 *     @OA\Property(property="height", type="integer", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PluginModule",
 *     type="object",
 *     description="按当前管理员权限裁剪的前端路由及钩子描述。",
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="version", type="string"),
 *     @OA\Property(property="generation", type="string"),
 *     @OA\Property(property="components", type="object", @OA\AdditionalProperties(type="string")),
 *     @OA\Property(property="routes", type="array", @OA\Items(ref="#/components/schemas/DynamicObject")),
 *     @OA\Property(property="clientHooks", type="array", @OA\Items(ref="#/components/schemas/DynamicObject"))
 * )
 *
 * @OA\Schema(
 *     schema="MarketCategoryInput",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", minLength=1, maxLength=50),
 *     @OA\Property(property="sort", type="integer"),
 *     @OA\Property(property="status", type="integer", enum={0, 1})
 * )
 *
 * @OA\Schema(
 *     schema="MarketGrantInput",
 *     type="object",
 *     @OA\Property(property="pluginId", type="integer", minimum=1),
 *     @OA\Property(property="account", type="string", description="会员用户名、邮箱或手机号。"),
 *     @OA\Property(property="expiresAt", type="string", description="允许空字符串表示永久授权。"),
 *     @OA\Property(property="status", type="integer", enum={0, 1}),
 *     @OA\Property(property="remark", type="string", maxLength=255)
 * )
 *
 * @OA\Schema(
 *     schema="MarketPluginInput",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", minLength=1, maxLength=100),
 *     @OA\Property(property="licenseType", type="string", enum={"free", "grant"}),
 *     @OA\Property(property="categoryId", type="integer", minimum=0),
 *     @OA\Property(property="pricePerpetual", type="string", description="金额以元为单位，最多两位小数。"),
 *     @OA\Property(property="priceYearly", type="string", description="金额以元为单位，最多两位小数。"),
 *     @OA\Property(property="cover", type="string", maxLength=500),
 *     @OA\Property(property="homepage", type="string", maxLength=500),
 *     @OA\Property(property="description", type="string", maxLength=1000),
 *     @OA\Property(property="author", type="string", maxLength=100),
 *     @OA\Property(property="status", type="integer", enum={0, 1}),
 *     @OA\Property(property="sort", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="AdminUser",
 *     type="object",
 *     description="公开响应字段来自 SystemAdmin::adminData；复杂扩展字段保持开放。",
 *     additionalProperties=true,
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="isSuper", type="boolean"),
 *     @OA\Property(property="username", type="string"),
 *     @OA\Property(property="nickname", type="string"),
 *     @OA\Property(property="email", type="string"),
 *     @OA\Property(property="mobile", type="string"),
 *     @OA\Property(property="status", type="integer"),
 *     @OA\Property(property="deptId", type="integer"),
 *     @OA\Property(property="departmentIds", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="roleIds", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="createdAt", type="string", description="数据库时间字符串；未设置时为空字符串。"),
 *     @OA\Property(property="updatedAt", type="string", description="数据库时间字符串；未设置时为空字符串。")
 * )
 *
 * @OA\Schema(
 *     schema="Member",
 *     type="object",
 *     description="公开响应字段来自 SystemMember::memberData；复杂扩展字段保持开放。",
 *     additionalProperties=true,
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="username", type="string"),
 *     @OA\Property(property="mobile", type="string"),
 *     @OA\Property(property="email", type="string"),
 *     @OA\Property(property="sex", type="string"),
 *     @OA\Property(property="groupIds", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="groupNames", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="tagIds", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="tagNames", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="levelId", type="integer"),
 *     @OA\Property(property="levelName", type="string"),
 *     @OA\Property(property="avatar", type="string"),
 *     @OA\Property(property="status", type="integer"),
 *     @OA\Property(property="loginCount", type="integer"),
 *     @OA\Property(property="lastLoginAt", type="string", description="数据库时间字符串；未设置时为空字符串。"),
 *     @OA\Property(property="lastLoginIp", type="string"),
 *     @OA\Property(property="createdAt", type="string", description="数据库时间字符串；未设置时为空字符串。"),
 *     @OA\Property(property="updatedAt", type="string", description="数据库时间字符串；未设置时为空字符串。"),
 *     @OA\Property(property="deletedAt", type="string", description="数据库时间字符串；未设置时为空字符串。")
 * )
 *
 * @OA\Schema(
 *     schema="SystemConfig",
 *     type="object",
 *     description="公开响应字段来自 SystemConfig::configData；复杂扩展字段保持开放。",
 *     additionalProperties=true,
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="group", type="string"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="verify", type="string"),
 *     @OA\Property(property="value"),
 *     @OA\Property(property="extra", type="string"),
 *     @OA\Property(property="remark", type="string"),
 *     @OA\Property(property="status", type="integer"),
 *     @OA\Property(property="isSystem", type="integer"),
 *     @OA\Property(property="createdAt", type="string", description="数据库时间字符串；未设置时为空字符串。"),
 *     @OA\Property(property="updatedAt", type="string", description="数据库时间字符串；未设置时为空字符串。")
 * )
 *
 * @OA\Schema(
 *     schema="ConfigGroup",
 *     type="object",
 *     description="公开响应字段来自 SystemConfig::groupData；复杂扩展字段保持开放。",
 *     additionalProperties=true,
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="status", type="integer"),
 *     @OA\Property(property="createdAt", type="string", description="数据库时间字符串；未设置时为空字符串。"),
 *     @OA\Property(property="updatedAt", type="string", description="数据库时间字符串；未设置时为空字符串。")
 * )
 *
 * @OA\Schema(
 *     schema="MemberLevel",
 *     type="object",
 *     description="公开响应字段来自 SystemMemberLevel::transformData；复杂扩展字段保持开放。",
 *     additionalProperties=true,
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="amount", type="string"),
 *     @OA\Property(property="discount", type="integer"),
 *     @OA\Property(property="thumb", type="string"),
 *     @OA\Property(property="status", type="integer"),
 *     @OA\Property(property="sort", type="integer"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="createdAt", type="string", description="数据库时间字符串；未设置时为空字符串。"),
 *     @OA\Property(property="updatedAt", type="string", description="数据库时间字符串；未设置时为空字符串。"),
 *     @OA\Property(property="deletedAt", type="string", description="数据库时间字符串；未设置时为空字符串。")
 * )
 *
 * @OA\Schema(
 *     schema="MemberGroup",
 *     type="object",
 *     description="公开响应字段来自 SystemMemberGroup::transformData；复杂扩展字段保持开放。",
 *     additionalProperties=true,
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="icon", type="string"),
 *     @OA\Property(property="status", type="integer"),
 *     @OA\Property(property="isDefault", type="integer"),
 *     @OA\Property(property="createdAt", type="string", description="数据库时间字符串；未设置时为空字符串。"),
 *     @OA\Property(property="updatedAt", type="string", description="数据库时间字符串；未设置时为空字符串。"),
 *     @OA\Property(property="deletedAt", type="string", description="数据库时间字符串；未设置时为空字符串。")
 * )
 *
 * @OA\Schema(
 *     schema="AttachmentGroup",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="parentId", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="thumb", type="string"),
 *     @OA\Property(property="status", type="integer"),
 *     @OA\Property(property="sort", type="integer"),
 *     @OA\Property(property="isDefault", type="integer"),
 *     @OA\Property(property="createdAt", type="string", description="数据库时间字符串；未设置时为空字符串。"),
 *     @OA\Property(property="updatedAt", type="string", description="数据库时间字符串；未设置时为空字符串。")
 * )
 *
 * @OA\Schema(
 *     schema="CsrfData",
 *     type="object",
 *     required={"csrfToken", "captchaEnabled"},
 *     @OA\Property(property="csrfToken", type="string", description="后续非安全方法需要放入 X-CSRF-TOKEN 请求头。"),
 *     @OA\Property(property="captchaEnabled", type="boolean", example=false)
 * )
 *
 * @OA\Schema(
 *     schema="TokenLoginData",
 *     type="object",
 *     required={"access_token", "token_type", "expires_in", "admin"},
 *     @OA\Property(property="access_token", type="string", description="不透明令牌，作为 Authorization: Bearer <token> 使用。"),
 *     @OA\Property(property="token_type", type="string", example="Bearer"),
 *     @OA\Property(property="expires_in", type="integer", description="有效期秒数。"),
 *     @OA\Property(property="admin", ref="#/components/schemas/DynamicObject")
 * )
 *
 * @OA\Schema(
 *     schema="AdminSuccess",
 *     type="object",
 *     required={"code", "msg", "time", "data"},
 *     @OA\Property(property="code", type="integer", description="业务码：200 为成功；普通后台校验错误可能在 HTTP 200 响应中以 code=400/422 返回。", example=200, enum={200}),
 *     @OA\Property(property="msg", type="string", description="业务提示", example="操作成功"),
 *     @OA\Property(property="time", type="integer", description="Unix 时间戳，单位秒。", example=0),
 *     @OA\Property(property="data", description="结构由具体业务、插件或已发布 Schema 决定；不推断未声明的字段。")
 * )
 *
 * @OA\Schema(
 *     schema="AdminValidationFailure",
 *     type="object",
 *     required={"code", "msg", "time", "data"},
 *     @OA\Property(
 *         property="code",
 *         type="integer",
 *         description="业务码：200 为成功；普通后台校验错误可能在 HTTP 200 响应中以 code=400/422 返回。",
 *         example=422,
 *         enum={400, 422}
 *     ),
 *     @OA\Property(property="msg", type="string", description="业务提示", example="参数校验失败"),
 *     @OA\Property(property="time", type="integer", description="Unix 时间戳，单位秒。", example=0),
 *     @OA\Property(property="data", description="结构由具体业务、插件或已发布 Schema 决定；不推断未声明的字段。")
 * )
 *
 * @OA\Schema(
 *     schema="AdminResult",
 *     oneOf={@OA\Schema(ref="#/components/schemas/AdminSuccess"), @OA\Schema(ref="#/components/schemas/AdminValidationFailure")}
 * )
 *
 * @OA\Schema(
 *     schema="ListButtonInput",
 *     type="object",
 *     required={"buttonId", "location", "schemaHash", "ids"},
 *     description="按钮 input 和确认要求由已发布按钮定义决定；分类位置需要 sourceSchemaHash。",
 *     additionalProperties=false,
 *     @OA\Property(property="buttonId", type="string"),
 *     @OA\Property(property="location", type="string", enum={"row", "toolbar", "categoryToolbar", "categoryNode"}),
 *     @OA\Property(property="schemaHash", type="string"),
 *     @OA\Property(property="ids", type="array", maxItems=200, @OA\Items(oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string")})),
 *     @OA\Property(property="input", type="object", additionalProperties=true),
 *     @OA\Property(property="idempotencyKey", type="string"),
 *     @OA\Property(property="confirmation", type="string"),
 *     @OA\Property(property="filter", type="object", additionalProperties=true),
 *     @OA\Property(property="category", type="object", @OA\Property(property="id", oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string")})),
 *     @OA\Property(property="sourceSchemaHash", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="AdminProfile",
 *     type="object",
 *     required={"id", "username", "nickname", "avatar", "email", "mobile", "lastLoginIp"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="username", type="string"),
 *     @OA\Property(property="nickname", type="string"),
 *     @OA\Property(property="avatar", type="string"),
 *     @OA\Property(property="email", type="string"),
 *     @OA\Property(property="mobile", type="string"),
 *     @OA\Property(property="lastLoginIp", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="IdentityAuditIndexResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/ListTotalData"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="IdentityUserIndexResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/ListTotalData"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="ScopeClaimIndexResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/ListTotalData"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemPluginEnabledModulesResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PluginModule")))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberLevelIndexResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(
 *                         property="data",
 *                         type="object",
 *                         required={"list", "total", "page", "pageSize"},
 *                         @OA\Property(property="list", type="array", @OA\Items(ref="#/components/schemas/MemberLevel")),
 *                         @OA\Property(property="total", type="integer"),
 *                         @OA\Property(property="page", type="integer"),
 *                         @OA\Property(property="pageSize", type="integer")
 *                     )
 *                 )
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberLevelDetailResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/MemberLevel"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberLevelCreateResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/MemberLevel"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberLevelUpdateResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/MemberLevel"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberLevelStatusResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/MemberLevel"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberIndexResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(
 *                         property="data",
 *                         type="object",
 *                         required={"list", "total", "page", "pageSize"},
 *                         @OA\Property(property="list", type="array", @OA\Items(ref="#/components/schemas/Member")),
 *                         @OA\Property(property="total", type="integer"),
 *                         @OA\Property(property="page", type="integer"),
 *                         @OA\Property(property="pageSize", type="integer")
 *                     )
 *                 )
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberDetailResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/Member"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberCreateResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/Member"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberUpdateResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/Member"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberStatusResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/Member"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemConfigIndexResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(
 *                         property="data",
 *                         type="object",
 *                         required={"list", "total", "page", "pageSize"},
 *                         @OA\Property(property="list", type="array", @OA\Items(ref="#/components/schemas/SystemConfig")),
 *                         @OA\Property(property="total", type="integer"),
 *                         @OA\Property(property="page", type="integer"),
 *                         @OA\Property(property="pageSize", type="integer")
 *                     )
 *                 )
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemConfigDetailResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/SystemConfig"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemConfigCreateResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/SystemConfig"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemConfigUpdateResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/SystemConfig"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemConfigStatusResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/SystemConfig"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberGroupIndexResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(
 *                         property="data",
 *                         type="object",
 *                         required={"list", "total", "page", "pageSize"},
 *                         @OA\Property(property="list", type="array", @OA\Items(ref="#/components/schemas/MemberGroup")),
 *                         @OA\Property(property="total", type="integer"),
 *                         @OA\Property(property="page", type="integer"),
 *                         @OA\Property(property="pageSize", type="integer")
 *                     )
 *                 )
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberGroupDetailResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/MemberGroup"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberGroupCreateResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/MemberGroup"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberGroupUpdateResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/MemberGroup"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemMemberGroupStatusResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/MemberGroup"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="AiConversationIndexResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/CursorPageData"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="AiMessageIndexResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/CursorPageData"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="AiAttachmentCreateResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/AiPrivateAttachment"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="AiEventTicketResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(property="data", type="object", required={"ticket"}, @OA\Property(property="ticket", type="string"))
 *                 )
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="AiToolCallLogResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(
 *                         property="data",
 *                         type="object",
 *                         required={"content", "hash"},
 *                         @OA\Property(property="content", type="string"),
 *                         @OA\Property(property="hash", type="string", nullable=true)
 *                     )
 *                 )
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemAdminIndexResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(
 *                         property="data",
 *                         type="object",
 *                         required={"list", "total", "page", "pageSize"},
 *                         @OA\Property(property="list", type="array", @OA\Items(ref="#/components/schemas/AdminUser")),
 *                         @OA\Property(property="total", type="integer"),
 *                         @OA\Property(property="page", type="integer"),
 *                         @OA\Property(property="pageSize", type="integer")
 *                     )
 *                 )
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemAdminDetailResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/AdminUser"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemAdminCreateResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/AdminUser"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemAdminUpdateResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/AdminUser"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SystemAdminStatusResult",
 *     oneOf={
 *         @OA\Schema(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/AdminSuccess"),
 *                 @OA\Schema(type="object", @OA\Property(property="data", nullable=true, description="更新成功时为 null。"))
 *             }
 *         ),
 *         @OA\Schema(ref="#/components/schemas/AdminValidationFailure")
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="AdminProfileIndexResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/BrowserAuthEnvelope"),
 *         @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/AdminProfile"))
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="AdminProfileUpdateResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/BrowserAuthEnvelope"),
 *         @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/AdminProfile"))
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="AdminProfilePasswordResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/BrowserAuthEnvelope"),
 *         @OA\Schema(type="object", @OA\Property(property="data", nullable=true, description="成功时为 null；所有旧会话被撤销。"))
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="ApiAuthLoginResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/TokenAuthEnvelope"),
 *         @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/TokenLoginData"))
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="ApiAuthLogoutResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/TokenAuthEnvelope"),
 *         @OA\Schema(type="object", @OA\Property(property="data", nullable=true, description="成功时 data 为 null。"))
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="AdminAuthCsrfResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/BrowserAuthEnvelope"),
 *         @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/CsrfData"))
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="AdminAuthLoginResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/BrowserAuthEnvelope"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 required={"authenticated"},
 *                 @OA\Property(property="authenticated", type="boolean", example=true)
 *             )
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="AdminAuthLogoutResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/BrowserAuthEnvelope"),
 *         @OA\Schema(type="object", @OA\Property(property="data", nullable=true, description="成功时 data 为 null。"))
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiEnvelope",
 *     type="object",
 *     required={"code", "msg", "time", "data"},
 *     @OA\Property(property="code", type="integer", description="业务码，与 HTTP 状态一致：成功 200，失败为 400/401/422/429。", example=200),
 *     @OA\Property(property="msg", type="string", description="提示信息，按当前语言翻译。", example="登录成功"),
 *     @OA\Property(property="time", type="integer", description="Unix 时间戳，单位秒。", example=0),
 *     @OA\Property(property="data", description="成功时为业务数据；失败时为 null。")
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiLoginInput",
 *     type="object",
 *     required={"username", "password"},
 *     @OA\Property(property="username", type="string", description="会员用户名、手机号或邮箱。", minLength=1, maxLength=100, example="member"),
 *     @OA\Property(property="password", type="string", description="会员密码；不记录或返回明文。", format="password", minLength=6, maxLength=255, writeOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiRefreshInput",
 *     type="object",
 *     required={"refresh_token"},
 *     @OA\Property(property="refresh_token", type="string", description="上一次登录或刷新返回的 refresh token；只能使用一次。", pattern="^[a-f0-9]{64}$", writeOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiTokenPair",
 *     type="object",
 *     required={"access_token", "refresh_token", "expires_in"},
 *     @OA\Property(property="access_token", type="string", description="会员 access token（JWT），作为 Authorization: Bearer <token> 使用。"),
 *     @OA\Property(property="refresh_token", type="string", description="新的 refresh token（64 位十六进制）。必须保存并替换旧值：旧值再次使用会被视为重放，整个会话立即吊销。", pattern="^[a-f0-9]{64}$"),
 *     @OA\Property(property="expires_in", type="integer", description="access token 有效期，单位秒。", example=7200)
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiProfile",
 *     type="object",
 *     required={"id", "username", "nickname"},
 *     description="当前会员公开资料；只输出白名单字段。",
 *     @OA\Property(property="id", type="integer", minimum=1, example=1),
 *     @OA\Property(property="username", type="string", example="member"),
 *     @OA\Property(property="nickname", type="string", example="会员昵称")
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiTokenResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/MemberApiEnvelope"),
 *         @OA\Schema(type="object", @OA\Property(property="data", ref="#/components/schemas/MemberApiTokenPair"))
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiProfileResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/MemberApiEnvelope"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(property="data", type="object", required={"user"}, @OA\Property(property="user", ref="#/components/schemas/MemberApiProfile"))
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiAccount",
 *     type="object",
 *     required={"id", "username", "nickname", "email", "mobile", "avatar", "motto"},
 *     description="当前会员本人可见的资料；只输出白名单字段。",
 *     @OA\Property(property="id", type="integer", minimum=1, example=1),
 *     @OA\Property(property="username", type="string", example="member"),
 *     @OA\Property(property="nickname", type="string", example="会员昵称"),
 *     @OA\Property(property="email", type="string", example="member@example.com"),
 *     @OA\Property(property="mobile", type="string", example=""),
 *     @OA\Property(property="avatar", type="string", example="https://example.com/avatar.png"),
 *     @OA\Property(property="motto", type="string", example=""),
 *     @OA\Property(property="created_at", type="string", nullable=true),
 *     @OA\Property(property="last_login", nullable=true, description="最后登录时间；MySQL 为 Unix 时间戳。")
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiAccountResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/MemberApiEnvelope"),
 *         @OA\Schema(type="object", @OA\Property(property="data", type="object", required={"user"}, @OA\Property(property="user", ref="#/components/schemas/MemberApiAccount")))
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiRegisterInput",
 *     type="object",
 *     required={"username", "email", "password", "password_confirm"},
 *     @OA\Property(property="username", type="string", description="字母开头，4 到 20 位字母、数字或下划线。", pattern="^[A-Za-z][A-Za-z0-9_]{3,19}$"),
 *     @OA\Property(property="email", type="string", format="email", maxLength=60),
 *     @OA\Property(property="nickname", type="string", maxLength=50, description="可选，默认为用户名。"),
 *     @OA\Property(property="password", type="string", format="password", minLength=8, maxLength=64, description="同时包含字母和数字。", writeOnly=true),
 *     @OA\Property(property="password_confirm", type="string", format="password", writeOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiRegisterResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/MemberApiEnvelope"),
 *         @OA\Schema(type="object", @OA\Property(property="data", allOf={
 *             @OA\Schema(ref="#/components/schemas/MemberApiTokenPair"),
 *             @OA\Schema(type="object", required={"user"}, @OA\Property(property="user", ref="#/components/schemas/MemberApiAccount"))
 *         }))
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiProfileInput",
 *     type="object",
 *     required={"nickname"},
 *     @OA\Property(property="nickname", type="string", minLength=1, maxLength=50),
 *     @OA\Property(property="motto", type="string", maxLength=255),
 *     @OA\Property(property="avatar", type="string", maxLength=255, description="https 开头的图片地址，或空字符串。")
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiPasswordInput",
 *     type="object",
 *     required={"current", "password", "password_confirm"},
 *     @OA\Property(property="current", type="string", format="password", writeOnly=true),
 *     @OA\Property(property="password", type="string", format="password", minLength=8, maxLength=64, writeOnly=true),
 *     @OA\Property(property="password_confirm", type="string", format="password", writeOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="MemberApiLogoutResult",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/MemberApiEnvelope"),
 *         @OA\Schema(type="object", @OA\Property(property="data", nullable=true, description="成功时 data 为 null。"))
 *     }
 * )
 *
 * @OA\Response(
 *     response="MemberApiError",
 *     description="错误响应；HTTP 状态与响应体 code 一致。",
 *     @OA\JsonContent(ref="#/components/schemas/MemberApiEnvelope")
 * )
 *
 * @OA\Response(
 *     response="MemberApiTooManyRequests",
 *     description="请求过于频繁：同一 IP 每分钟 10 次，登录另按账号每分钟 5 次。",
 *     @OA\JsonContent(ref="#/components/schemas/MemberApiEnvelope"),
 *     @OA\Header(header="Retry-After", description="建议重试等待秒数。", @OA\Schema(type="integer", example=60))
 * )
 *
 * @OA\Schema(
 *     schema="PluginEnvelope",
 *     type="object",
 *     required={"code", "msg", "data"},
 *     description="插件前台与会员接口信封（不含 time）。",
 *     @OA\Property(property="code", type="integer", description="成功为 200（CMS 会员投稿、问答会员接口）或 0（CMS 公开表单、文档读者反馈）；失败时与 HTTP 状态一致。", example=200),
 *     @OA\Property(property="msg", type="string", example="success"),
 *     @OA\Property(property="data", description="结构由具体插件接口决定；不推断未声明的字段。")
 * )
 *
 * @OA\Response(
 *     response="PluginError",
 *     description="插件前台或会员接口错误；HTTP 状态与响应体 code 一致。",
 *     @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")
 * )
 *
 * @OA\Response(
 *     response="AdminError",
 *     description="异常响应。HTTP 状态按认证、权限、资源、冲突或服务故障返回；具体业务码和提示见响应体。",
 *     @OA\JsonContent(ref="#/components/schemas/AdminEnvelope")
 * )
 *
 * @OA\Response(
 *     response="BrowserAuthError",
 *     description="异常响应。HTTP 状态按认证、权限、资源、冲突或服务故障返回；具体业务码和提示见响应体。",
 *     @OA\JsonContent(ref="#/components/schemas/BrowserAuthEnvelope")
 * )
 *
 * @OA\Response(
 *     response="TokenAuthError",
 *     description="异常响应。HTTP 状态按认证、权限、资源、冲突或服务故障返回；具体业务码和提示见响应体。",
 *     @OA\JsonContent(ref="#/components/schemas/TokenAuthEnvelope")
 * )
 */
final class ApiDocumentation
{
}
