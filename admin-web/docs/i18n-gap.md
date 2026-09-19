# i18n 缺失清单

> 自动生成自 `scripts/scan-i18n.mjs`，命中文件 **103** 个，去重短语共 **1023** 条。
> 已跳过：`src/locales/**`、`src/mock/**`、`*.d.ts`、`*.spec.ts`、`__tests__/**` 与所有注释。
> 仅作"应当 i18n 但还没接入 vue-i18n"的盘点参考，不代表所有命中都必须改造。

## 现状

- 已有 i18n key：仅 `menu / layout / login` 三个命名空间（见 `src/locales/zh-CN.ts`）。
- 业务页面、通用组件、`ElMessage` / `ElMessageBox` 文案、表单校验提示几乎全部为硬编码中文。
- 推荐渐进式策略：
  1. **第 1 步（建议立刻做）**：把高频通用文案抽到 `common` 命名空间（确定/取消/操作/提示/成功/失败/请输入/请选择/重置/查询/新增/编辑/删除/批量删除/导入/导出/搜索/状态/启用/禁用）。
  2. **第 2 步**：按"系统管理"模块逐页改造（`system.user.*` / `system.role.*` / `system.menu.*` …）。
  3. **第 3 步**：把 `useCrud` / `DataTableShell` 等基础设施内部的 `ElMessage` 文案接入 `common`。

---

## 命中明细（按一级目录分组）

### `src/views/` — 89 个文件 / 953 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/views/form/registry.ts` | 82 | `基础控件`，`选择控件`，`日期时间`，`上传控件`，`业务控件`，`布局控件`，`单行输入`，`密码输入` … |
| `src/views/form/designer/index.vue` | 67 | `离线草稿`，`预览数据`，`使用已保存草稿生成源码，不动态发布；安装／更新后生效。`，`生成正式模块`，`保存已暂停。`，`本地草稿已保留，刷新不会解除暂停。请核对版本并明确选择恢复方式。`，`核对版本`，`创建` … |
| `src/views/dashboard/index.vue` | 63 | `周日`，`周一`，`周二`，`周三`，`周四`，`周五`，`周六`，`夜深了，` … |
| `src/views/development/ai/components/ProviderSettingsDrawer.vue` | 45 | `连接与模型`，`应用到连接`，`Ollama 官方本地地址为 http://localhost:11434/v1；本系统禁止本机、私网及 HTTP，请填写经授权的公网 HTTPS 网关地址。`，`获取模型`，`备用模型`，`上移`，`下移`，`移除` … |
| `src/views/applications/identity-management.vue` | 34 | `域名管理`，`Scope 与 Claim`，`身份用户`，`会话与授权`，`签名密钥`，`登录审计`，`用户 ID`，`状态` … |
| `src/views/form/designer/components/ListConfigurationPanel.vue` | 33 | `单选`，`复选`，`选项筛选`，`分类绑定字段`，`选择已有静态选项或字典字段`，`可管理分类`，`来源`，`当前业务（same）` … |
| `src/views/system/config/components/ConfigFormDialog.vue` | 33 | `单行文本`，`多行文本`，`整数`，`浮点数`，`小数`，`开关`，`单选按钮`，`复选框` … |
| `src/views/system/plugin/index.vue` | 33 | `插件正在发布、恢复或当前状态不可开发`，`Ed25519 签名`，`{alg} 签名`，`未签名`，`数据库 {v}`，`数据库无迁移要求`，`${t('plugin.capManifest', { v: version.manifestSchema }, { default: '清单协议 v{v}' })} · ${version.packageFormat === 'funadmin-native-app-v1' ? t('plugin.capNative', '原生应用包') : t('plugin.capOther', '其他格式包')} · ${apps \|\| t('plugin.capNone', '无应用能力')} · ${signature} · ${database}${compatibility}`，`插件正在执行{label}（{progress}%）` … |
| `src/views/system/plugin/pluginDisplay.ts` | 30 | `待安装`，`安装中`，`已禁用`，`更新中`，`启用中`，`已启用`，`禁用中`，`卸载中` … |
| `src/views/form/designer/components/ListButtonEditor.vue` | 29 | `刷新`，`当前业务编辑表单`，`复制字段文本`，`授权导出下载`，`站内导航 · {key}`，`受控外链 · {key}`，`注册动作 · {key}`，`显示条件` … |
| `src/views/system/member/index.vue` | 27 | `用户名`，`手机号`，`邮箱`，`性别`，`会员组ID`，`会员标签ID`，`会员等级ID`，`头像` … |
| `src/views/development/business/visual.vue` | 21 | `无创建新表权限。`，`无结构检查权限，不能检查或采纳已有表。`，`当前为只读检查，无采纳权限，不能提交。`，`请输入业务名称`，`以小写字母开头，只能包含小写字母、数字和下划线`，`数据表标识不合法`，`连接标识不合法`，`请选择已有数据表` … |
| `src/views/form/runtime/listButtonHost.ts` | 21 | `新增`，`编辑`，`详情`，`删除`，`批量删除`，`导入`，`导出`，`回收站` … |
| `src/views/form/validation/formSchemaDataValidator.ts` | 20 | `字段不能为空`，`字段类型不正确`，`字段值过小`，`字段值过大`，`字段长度不足`，`字段长度过长`，`字段长度不正确`，`字段值不在允许范围内` … |
| `src/views/system/member-level/index.vue` | 17 | `等级名称`，`等级金额`，`等级折扣`，`缩略图`，`状态`，`排序`，`描述`，`确认将选中的 {n} 个会员等级移入回收站吗？` … |
| `src/views/form/data.vue` | 16 | `请先勾选需要删除的记录`，`确认删除选中的 {n} 条记录吗？`，`批量删除`，`永久删除后不可恢复，确认继续吗？`，`永久删除`，`创建时间`，`操作`，`多个值用英文逗号分隔` … |
| `src/views/system/attachment/index.vue` | 16 | `默认分组`，`原存储驱动不可用，已回退到本地存储`，`确认删除附件分组“{name}”吗？组内附件将移至未分组。`，`删除确认`，`成功 {uploaded} 个，失败 {failed} 个：{names}`，`{n} 个重复文件复用了其他分组中的已有记录`，`成功上传 {n} 个文件`，`请输入新的文件名称` … |
| `src/views/system/blacklist/index.vue` | 15 | `IP/规则`，`备注`，`状态`，`启用`，`开启`，`确认将选中的 {n} 条记录移入回收站吗？`，`操作确认`，`确认永久删除选中的 {n} 条记录吗？此操作不可恢复。` … |
| `src/views/system/log/operation.vue` | 14 | `账号`，`应用`，`来源`，`操作`，`方法`，`状态`，`状态码`，`耗时` … |
| `src/views/system/user/index.vue` | 14 | `账号`，`昵称`，`邮箱`，`手机`，`状态`，`创建时间`，`操作`，`确认删除选中的 {n} 个账号？此操作不可恢复` … |
| `src/views/system/member-group/index.vue` | 13 | `会员组名称`，`图标`，`状态`，`确认将选中的 {n} 个会员组移入回收站吗？`，`操作确认`，`确认永久删除选中的 {n} 个会员组吗？此操作不可恢复。`，`永久删除确认`，`永久删除` … |
| `src/views/system/role/index.vue` | 13 | `请输入名称`，`请输入标识`，`需以英文开头，仅支持字母、数字和下划线`，`请输入角色等级`，`请选择数据范围`，`请选择自定义部门`，`授权详情加载失败`，`确认删除角色 {name}？此操作不可恢复` … |
| `src/views/development/business/mine.vue` | 12 | `没有符合筛选条件的业务模块`，`还没有业务模块，可通过创建业务选择新表或已有表`，`正在加载生成预览`，`生成预览失败：{msg}`，`生成预览已加载`，`确认删除业务“{name}”？对应的生成菜单和权限将同时清理，已生成源码与数据表不会自动删除。`，`删除业务`，`删除` … |
| `src/views/form/published.vue` | 12 | `请先勾选需要删除的记录`，`确认删除选中的 {n} 条记录吗？`，`批量删除`，`永久删除后不可恢复，确认继续吗？`，`永久删除`，`操作`，`操作已成功，但列表刷新失败，请手动刷新，不要重复提交`，`保存成功` … |
| `src/views/system/menu/index.vue` | 11 | `排序已保存`，`保存排序失败`，`目录`，`页面`，`未知`，`确认清理孤儿资源 {name}？同一生成来源的菜单、权限与授权规则将一并删除。`，`确认删除 {name} ?`，`清理孤儿资源` … |
| `src/views/development/business/records.vue` | 10 | `{name}（{code}）的 managed 生成、冲突与恢复状态`，`查看全部业务模块的 managed 生成、冲突与恢复状态`，`模块 ID 必须为正整数`，`生成记录加载失败`，`生成记录详情加载失败`，`恢复将回滚本次生成产生的变更，是否继续？`，`确认恢复`，`恢复成功` … |
| `src/views/form/designer/components/SchemaStructurePanel.vue` | 10 | `布局节点不能测试数据源`，`测试成功，共 {n} 条数据`，`请求异常`，`测试失败：{msg}`，`原始 JSON 已应用`，`请输入合法 JSON`，`原始 JSON（高级）· {title}`，`应用原始 JSON` … |
| `src/views/profile/index.vue` | 10 | `请输入昵称`，`邮箱格式不正确`，`手机号格式不正确`，`请输入原密码`，`请输入新密码`，`新密码至少 8 位`，`请再次输入新密码`，`两次密码不一致` … |
| `src/views/system/plugin/components/PluginAccountDrawer.vue` | 10 | `操作失败，请稍后重试`，`请输入云市场账号`，`密码至少 6 位`，`云市场账号登录成功`，`令牌刷新成功`，`退出后将无法同步授权、版本信息和下载插件，确认退出吗？`，`退出云市场账号`，`确认退出` … |
| `src/views/system/user/components/UserFormDialog.vue` | 9 | `请输入账号`，`账号需以字母开头，长度 3 到 20 位`，`请输入昵称`，`密码至少 8 位`，`邮箱格式不正确`，`邮箱不能超过 60 个字符`，`手机号格式不正确`，`选择部门` … |
| `src/views/development/business/components/GenerationFileDiff.vue` | 8 | `本地修改（Base → Local）`，`本次生成改动（Base → Remote）`，`本地与待生成（Local → Remote）`，`二进制文件：未计算文本差异，请使用专用工具核对。`，`服务端未提供所选版本内容，未计算差异；缺失内容不等于空文件。`，`文本过大或差异计算超出预算，未计算行级差异；不表示内容相同。请在本地使用 diff 工具核对。`，`内容相同，无差异。`，`只读行级差异；旧行与新行对应同一组上下文。LF 为默认行尾，其他行尾单独标注。` |
| `src/views/form/designer/pluginCatalog.ts` | 8 | `业务控件`，`基础控件`，`选择控件`，`日期时间`，`上传控件`，`布局控件`，`组件目录版本不匹配：期望 2，收到 ${String(catalog.schemaVersion)}`，`插件组件缺失或未通过白名单加载：${type}` |
| `src/views/system/permission/index.vue` | 8 | `目录`，`能力`，`路由`，`确认删除权限资源“{name}”吗？`，`删除确认`，`确认删除已选中的 {n} 个权限资源吗？`，`批量删除`，`批量删除成功` |
| `src/views/system/plugin/components/PluginDevelopmentDialog.vue` | 8 | `新建`，`未变化`，`覆盖`，`冲突`，`待处理`，`插件已创建，审计编号：{id}`，`插件校验通过`，`插件打包完成` |
| `src/views/form/components/ListButtonBar.vue` | 7 | `动作目录加载失败，请重试`，`动作目录、权限、版本或选择不满足执行条件`，`确认执行此动作？`，`预览仅模拟，不执行动作`，`动作尚未接通`，`动作已成功，但刷新失败，请手动刷新，不要重复提交`，`动作执行失败` |
| `src/views/install/install.ts` | 7 | `数据库配置不完整`，`管理员账号不能为空`，`管理员密码必须为6-16位`，`管理员密码必须同时包含字母和数字`，`两次输入密码不一致`，`管理员邮箱不能超过60个字符`，`请输入正确的邮箱` |
| `src/views/system/dict/index.vue` | 7 | `确认删除分类「{name}」？该操作会一并删除其下所有字典项`，`提示`，`请至少选择一项`，`确认删除选中的 {n} 个分类？将一并删除其下字典项，此操作不可恢复`，`请先在左侧选择一个分类`，`确认删除字典项「{name}」?`，`确认删除选中的 {n} 个字典项？此操作不可恢复` |
| `src/views/applications/sso/index.vue` | 6 | `关闭 SSO`，`保留本地登录，不提供统一身份入口`，`身份提供方`，`FunAdmin 作为 OIDC Provider`，`外部身份接入`，`连接企业外部 IdP` |
| `src/views/development/business/composables/useBusinessTarget.ts` | 6 | `正在加载业务目标`，`所选插件不可用：${candidate.value.reason.message}`，`所选插件不可用或无权限，请重新选择目标。`，`${name} — ${item.reason?.message \|\| '目标不可用'}`，`无业务目标查看权限，不能创建或采纳；已授权的结构检查仍可使用。`，`业务目标加载失败，请重试` |
| `src/views/error/components/ErrorPage.vue` | 6 | `无权访问`，`抱歉，你没有访问该页面的权限`，`页面不存在`，`你访问的资源已被移除或暂时不可用`，`服务异常`，`服务器开了点小差，稍后再试` |
| `src/views/system/menu/components/MenuFormDialog.vue` | 6 | `请输入名称`，`请选择类型`，`请输入 path`，`请输入组件`，`请选择权限资源`，`无上级` |
| `src/views/system/permission/components/PermissionFormDialog.vue` | 6 | `请输入资源名称`，`请输入应用标识`，`仅支持小写字母、数字和下划线`，`请输入资源对象`，`请输入动作`，`无上级` |
| `src/views/system/role/components/RoleFormDialog.vue` | 6 | `请输入名称`，`请输入标识`，`需以英文开头，仅支持字母、数字和下划线`，`请输入角色等级`，`请选择数据范围`，`请选择自定义部门` |
| `src/views/applications/index.vue` | 5 | `没有符合搜索条件的应用`，`暂无应用，可通过“新建应用”创建第一个企业应用`，`当前账号无权进入该应用`，`确定删除应用“{name}”吗？`，`删除确认` |
| `src/views/applications/oauth/index.vue` | 5 | `Redirect URI 必须为无用户信息、fragment、通配符的 HTTPS 绝对地址`，`确定删除 {name} 吗？`，`确认`，`轮换后旧 Key 将在发布窗口内继续出现在 JWKS 中，确定继续？`，`签名 Key 轮换` |
| `src/views/form/composables/useDesigner.ts` | 5 | `目标节点不是容器`，`${original.label}(副本)`，`${source.label}(副本)`，`仅支持 FormSchema v2 文档`，`nodeId 不能为空且必须唯一` |
| `src/views/form/runtime/actionExecutor.ts` | 5 | `动作链最大步数必须为正整数`，`动作链超过最大步数：${maxSteps}`，`动作未注册：${String(action?.type ?? '')}`，`request key 未注册：${String(request.key ?? '')}`，`request 并发策略不合法：${String(request.concurrency ?? '')}` |
| `src/views/system/member-level/components/MemberLevelFormDialog.vue` | 5 | `请输入等级名称`，`最多 30 个字符`，`请输入等级金额`，`请输入等级折扣`，`最多 200 个字符` |
| `src/views/system/upgrade/index.vue` | 5 | `确认升级到 {version}？系统将先备份。`，`升级确认`，`确认上传 {name} 并执行升级？ZIP 内签名 manifest 将由服务器校验。`，`确认从任务 {id} 的备份恢复文件？数据库 migration 不会回滚。`，`恢复确认` |
| `src/views/form/components/ListButtonInteraction.vue` | 4 | `此项必填`，`数值超出允许范围`，`输入过长`，`请选择有效选项` |
| `src/views/form/components/ListSourceTree.vue` | 4 | `弹窗已失效`，`来源发布版本已变化，请重新打开弹窗`，`确认删除来源节点？有子节点或业务引用时禁止删除。`，`删除确认` |
| `src/views/form/designer/components/PropsPanel.vue` | 4 | `{title} 需要合法 JSON`，`: ''}${column.primary ? t('formDesigner.primaryKeyTag', ' [主键]') : ''}`，`props JSON 不合法`，`选项来源 JSON 不合法` |
| `src/views/form/designer/components/VersionHistoryDrawer.vue` | 4 | `确认回滚到 v{version}？系统会创建一个新的不可变版本。`，`回滚确认`，`回滚版本已创建`，`Schema 已更新，请刷新后重试` |
| `src/views/system/config/components/ConfigGroupDialog.vue` | 4 | `请输入分组编码`，`以字母开头，只能包含字母、数字、横线和下划线`，`请输入分组标题`，`最多 60 个字符` |
| `src/views/system/config/index.vue` | 4 | `确认删除配置“{code}”吗？`，`删除确认`，`确认删除选中的 {n} 个配置吗？`，`确认删除配置分组“{title}”吗？仅空分组可删除。` |
| `src/views/system/language/index.vue` | 4 | `确认删除语言“{name}”吗？`，`删除确认`，`确认删除选中的 {n} 个语言吗？`，`批量删除确认` |
| `src/views/development/ai/index.vue` | 3 | `档案已停用`，`没有默认档案`，`连接失败` |
| `src/views/form/components/FormControlRenderer.vue` | 3 | `选项加载失败`，`标签一`，`标签二` |
| `src/views/form/components/FormDataImportDialog.vue` | 3 | `CSV 内容为空或无法解析`，`超过 1000 行上限，请拆分后重试`，`没有可映射的列：表头需使用字段名或字段标签` |
| `src/views/form/components/SchemaRenderer.vue` | 3 | `表单存在 {n} 个错误，请检查并修正。`，`{label}不能为空`，`字段校验失败` |
| `src/views/form/runtime/listButtonExecutor.ts` | 3 | `缺少当前页选择上下文`，`至少选择 ${button.selection.min} 条，当前已选择 ${count} 条`，`最多选择 ${button.selection.max ?? 200} 条，当前已选择 ${count} 条` |
| `src/views/system/attachment/components/AttachmentGroupDialog.vue` | 3 | `无上级`，`请输入分组名称`，`最多 100 个字符` |
| `src/views/system/blacklist/components/BlacklistFormDialog.vue` | 3 | `请输入 IP/规则`，`最多 50 个字符`，`最多 200 个字符` |
| `src/views/system/dict/components/DictTypeFormDialog.vue` | 3 | `请输入字典名称`，`请输入字典编码`，`只能包含字母数字下划线，且以字母开头` |
| `src/views/system/member/components/MemberFormDialog.vue` | 3 | `会员表单定义不可用`，`${names[index] \|\| '#' + id}${t('systemMember.optionUnavailable', '（不可用，请重新选择）')}`，`会员表单加载失败，请重试` |
| `src/views/system/role/components/RoleAuthorizationPanel.vue` | 3 | `继承自：{names}`，`确认复制所选角色的整套授权吗？`，`复制授权` |
| `src/views/applications/portal.vue` | 2 | `启动地址包含不安全 token`，`当前身份无法进入该应用` |
| `src/views/form/components/SchemaForm.vue` | 2 | `{label}不能为空`，`{label}格式或长度不正确` |
| `src/views/form/designer/schemaEditor.ts` | 2 | `JSON 语法错误：${error instanceof Error ? error.message : '无法解析'}`，`仅支持结构完整的 FormSchema v2 文档` |
| `src/views/form/runtime/fieldPresentation.ts` | 2 | `选项加载失败，请稍后重试`，`选项加载中…` |
| `src/views/form/schema/componentRegistry.ts` | 2 | `表单组件已注册：${definition.type}`，`插件组件必须使用插件命名空间` |
| `src/views/form/schema/pluginComponentLoader.ts` | 2 | `插件组件命名空间不一致：${definition.type}`，`插件表单组件未包含在当前构建中：${definition.type}` |
| `src/views/form/schema/runtimeGuard.ts` | 2 | `未注册的表单组件：${unknown.join('、')}`，`未注册的表单组件：${unknown.map((item) => item.type).join('、')}` |
| `src/views/install/index.vue` | 2 | `环境检测失败`，`安装失败` |
| `src/views/system/dict/components/DictItemFormDialog.vue` | 2 | `请输入字典标签`，`请输入字典键值` |
| `src/views/system/language/components/LanguageFormDialog.vue` | 2 | `请输入语言名称`，`最多 20 个字符` |
| `src/views/system/language/components/LanguageLinesDrawer.vue` | 2 | `确认删除译文 {key}？删除后该 key 回落静态语言包。`，`删除确认` |
| `src/views/system/member-group/components/MemberGroupFormDialog.vue` | 2 | `请输入会员组名称`，`最多 50 个字符` |
| `src/views/system/plugin/components/PluginHistoryDrawer.vue` | 2 | `确认将插件 {code} 重部署为历史版本 {version} 吗？数据库不会自动降级。`，`历史版本重部署` |
| `src/views/development/ai/i18n.ts` | 1 | `未分组` |
| `src/views/development/business/composables/useDirtyGuard.ts` | 1 | `当前内容尚未保存，确认离开吗？` |
| `src/views/form/designer/components/ListButtonConditionEditor.vue` | 1 | `条件` |
| `src/views/form/designer/components/SchemaNodeTree.vue` | 1 | `未知控件` |
| `src/views/form/runtime/conditionEvaluator.ts` | 1 | `条件操作符未注册：${String((condition as ComparisonCondition).op)}` |
| `src/views/form/validation/asyncValidatorRegistry.ts` | 1 | `字段校验失败` |
| `src/views/system/config/components/ConfigValueEditor.vue` | 1 | `例如 {"key":"value"}` |
| `src/views/system/dept/components/DeptFormDialog.vue` | 1 | `无上级` |
| `src/views/system/plugin/pluginActions.ts` | 1 | `彻底清理数据时必须输入插件标识 ${pluginCode}` |
| `src/views/system/role/components/RolePermDrawer.vue` | 1 | `继承自：{names}` |

### `src/components/` — 4 个文件 / 7 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/components/InlineEdit/index.vue` | 2 | `请输入`，`保存失败` |
| `src/components/MenuSearch/index.vue` | 2 | `仪表盘`，`个人中心` |
| `src/components/Upload/index.vue` | 2 | `文件 {name} 超过 {size}MB`，`上传失败` |
| `src/components/IconSelect/index.vue` | 1 | `选择图标` |

### `src/composables/` — 1 个文件 / 4 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/composables/useCrud.ts` | 4 | `确认删除该记录？此操作不可恢复`，`提示`，`请至少选择一项`，`确认删除选中的 {n} 条？此操作不可恢复` |

### `src/router/` — 4 个文件 / 17 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/router/routes.ts` | 10 | `安装向导`，`登录`，`个人中心`，`AI 助手`，`表单数据`，`业务设计器`，`已发布表单`，`无权访问` … |
| `src/router/pluginModules.ts` | 4 | `插件组件未包含在当前构建中：${name}`，`${descriptor.code} 插件错误`，`插件路由 DTO 越界`，`插件组件未注册：${dto.component}` |
| `src/router/dynamic.ts` | 2 | `[router] 生成源码尚未进入当前构建，使用运行时发布宿主: ${normalized}`，`[router] 未找到组件: ${candidates.join(' \| ')}，请检查后端菜单 component 字段` |
| `src/router/guard.ts` | 1 | `页面不存在` |

### `src/store/` — 2 个文件 / 17 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/store/modules/app.ts` | 14 | `雅致`，`浅色 + 清新蓝`，`清新`，`浅色 + 青蓝`，`经典`，`深色侧栏 + 蓝`，`极简`，`浅色 + 石墨` … |
| `src/store/modules/aiDevelopment.ts` | 3 | `模型正在保存，请稍后重试`，`消息分页游标未前进`，`当前 ChangeSet 的最终 apply 审批不存在或尚未批准` |

### `src/utils/` — 1 个文件 / 14 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/utils/http/index.ts` | 14 | `登录已失效，请重新登录`，`请求失败`，`网关错误`，`网关超时`，`服务器内部错误`，`网络异常`，`没有访问权限`，`请求资源不存在` … |

### `src/api/` — 2 个文件 / 11 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/api/development/ai.ts` | 10 | `模型能力声明无效、重复或预算超出范围`，`图片能力声明无效，图片预算固定为 32768`，`备用模型必须有序、去重、排除主模型，开启时须有 1 至 3 个候选`，`${model} 未声明支持所选推理档位，请选择默认或合法档位`，`备用要求主模型及全部候选声明上下文、输出能力，并设置明确输出预算`，`${model} 的 Token 预算超过模型能力上限`，`AI 接口未返回有效实体`，`AI 接口 ${field} 无效或超出安全整数范围` … |
| `src/api/install.ts` | 1 | `安装请求失败` |

---

## 建议补齐的 `common` 命名空间（草案）

```ts
// src/locales/zh-CN.ts
common: {
  ok: '确定', cancel: '取消', confirm: '确认', tip: '提示',
  success: '成功', failed: '失败', loading: '加载中',
  search: '查询', reset: '重置', refresh: '刷新',
  add: '新增', edit: '编辑', remove: '删除', batchRemove: '批量删除',
  import: '导入', export: '导出', upload: '上传', download: '下载',
  status: '状态', enable: '启用', disable: '禁用',
  pleaseInput: '请输入', pleaseSelect: '请选择',
  operation: '操作', detail: '详情', clear: '清空',
  saveSuccess: '保存成功', deleteSuccess: '删除成功',
  deleteConfirm: '确定删除该记录吗？', batchDeleteConfirm: '确定批量删除选中记录吗？'
}
```

改造时可优先把 `useCrud.ts` 内部的成功/失败 toast、`SearchForm` 的"查询/重置"按钮、`DataTableToolbar` 的工具栏标签接入 `common`，单点改动即可惠及所有列表页。
