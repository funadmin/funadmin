export default {
    title: 'AI 开发助手',
    subtitle: '会话、工具审批与工作区变更',
    reasoning: {
      profile: '思考模式', conversation: '会话思考档位', levels: '思考档位',
      default: '默认', defaultOption: '默认（不发送 reasoning_effort）', inherit: '继承档案', override: '会话覆盖', noProfile: '未选择档案',
      capabilityHint: '能力由管理员逐模型声明，不代表官方验证，不从模型名称或目录推断。可声明 low、medium、high、xhigh、max、ultra；当前仅 openai-chat 按 reasoning_effort 原样透传，不转换档位，也不保证上游支持。'
    },
    modelSelection: {
      current: '当前会话模型', unset: '未设置', id: '模型 ID', save: '保存模型',
      hint: '仅支持当前配置的供应商，请输入其支持的模型 ID。切换仅影响保存后创建的任务，当前任务保持原模型。',
      failed: '模型保存失败，请检查模型 ID 或重试',
      helpAria: '模型与思考设置说明', helpTrigger: '设置说明', taskSnapshot: '仅影响新建任务，运行任务保留快照。',
      profileSummary: '档案默认模型：{model}；Token 输入 {input} / 输出 {output} / 上下文 {context}', unspecified: '未指定'
    },
    provider: 'Provider',
    fields: { base: '基线', local: '本地', remote: '远端', baseUrl: 'Base URL', model: 'Model', apiKey: 'API key' },
    task: '任务',
    workspace: '工作区',
    conversations: '会话',
    management: {
      more: '更多会话操作', viewArchived: '查看已归档', createGroup: '新建分组', deleteGroup: '删除分组', groupName: '分组名称', ungrouped: '未分组',
      rename: '改名', move: '移动分组', archive: '归档', restore: '恢复', delete: '删除',
      archived: '已归档', active: '返回会话', unread: '未读', markRead: '标为已读', markUnread: '标为未读', actions: '会话操作',
      title: '会话标题', confirm: '确定', cancel: '取消', required: '名称不能为空',
      deleteGroupConfirm: '删除分组后，其中全部会话将归档，是否继续？', deleteConfirm: '确定删除此会话？此操作不可撤销。',
      failed: '会话操作失败，请重试', syncFailed: '消息或已读状态同步失败，请重新打开会话'
    },
    pagination: { filter: '筛选', more: '加载更多', older: '加载更早消息', search: '搜索会话', unreadFilter: '阅读状态', allReadStates: '全部阅读状态', groupFilter: '分组筛选', allGroups: '全部分组' },
    newConversation: '新建',
    unnamedConversation: '未命名会话',
    noConversations: '暂无会话',
    selectConversation: '选择或新建会话',
    stop: '停止',
    send: '发送',
    promptPlaceholder: '描述你要分析或修改的内容。高风险操作会按审批模式暂停。',
    newConversationTitle: '新 AI 会话',
    taskContext: '任务上下文',
    settings: { title: '会话设置', open: '打开会话设置', reasoningShort: '思考 {effort}', model: '模型', reasoning: '思考强度', approval: '审批模式', done: '完成' },
    taskDetails: '任务详情',
    pendingApprovalCount: '{count} 项待审批',
    taskAndPermissions: '任务与权限',
    taskOverview: '任务概况',
    riskAndApproval: '风险及审批操作',
    stage: '阶段',
    status: '状态',
    test: '测试',
    risk: '风险',
    noPendingRisk: '暂无待审批风险',
    noActiveTask: '暂无活动任务',
    toolLog: '工具日志',
    messages: { empty: '开始一个新的 AI 会话', copy: '复制', copied: '已复制', roles: { system: '系统', user: '你', assistant: 'AI 助手', tool: '工具' } },
    approvalModes: {
      request: '请求批准', agent: '替我审批', fullAccess: '完全访问权限',
      requestBoundary: '危险工具执行前逐次请求批准。',
      agentBoundary: '仅在已授权 capability 范围内由代理审批。',
      fullAccessBoundary: '允许高风险操作，但最终应用 ChangeSet 仍须二次确认。',
      missingCapability: '403：缺少 development:ai:full-access capability',
      unavailable: '403：完全访问权限不可用'
    },
    approval: {
      required: '需要审批', defaultRisk: '该操作需要人工确认', details: '危险详情与影响范围',
      feedbackPlaceholder: '拒绝时可填写反馈', allowOnce: '仅本次允许', allowSession: '允许会话操作', reject: '拒绝'
    },
    changeSet: {
      selectedCount: '已选择 {selected} / {total} 个文件',
      entity: 'ChangeSet', title: 'ChangeSet / Diff', test: '测试：{status}', security: '安全：{status}', fileSelectionOnly: '按文件选择，不支持逐块选择',
      conflictBlocked: '冲突阻断：请取消冲突文件或重新生成 ChangeSet', previewRequired: '预览后才能应用；最终应用总是需要二次确认',
      close: '关闭', preview: '预览', confirmApply: '二次确认并应用', applyConfirm: '将把选中的文件应用到工作区。请再次确认。',
      applyConfirmTitle: '最终应用二次确认', applyConfirmButton: '确认应用', applied: 'ChangeSet 已应用'
    },
    diff: { binary: '二进制文件无法显示文本 Diff', omitted: '内容已省略：文件为二进制或超过展示上限', missing: 'Diff 正文未由后端返回，仅展示文件状态与哈希。' },
    profiles: {
      basicTitle: '基本信息', unsaved: '未保存', defaultTag: '默认', listEmpty: '还没有档案，点击上方按钮新建', keySaved: '已存密钥', keyMissing: '未设密钥',
      title: '配置档案', create: '创建档案', general: '档案设置', name: '档案名称（修改后保存即改名）', enabled: '启用', copy: '复制', default: '设为默认', copyHint: '复制不包含 API key，也不继承默认状态。删除默认档案不会自动选择其他档案。',
      protocolHint: '支持 Chat Completions、Responses 与 Anthropic Messages 三种协议。Provider 仅为标识，不代表协议。', keyHint: '留空保留已保存密钥', hasKey: '已保存密钥（不回显）', noKey: '尚未保存密钥', clearKey: '保存时清空密钥',
      models: '模型设置', fetchModels: '获取模型', modelsHint: '可直接使用当前表单查询模型；已保存档案会复用已保存密钥。模型可搜索、选择或手动输入，不推断能力。', favorites: '常用模型', limits: '上下文与运行限制', context_window: '上下文窗口（留空不指定）', max_input_tokens: '最大输入 token（留空不指定）', max_output_tokens: '最大输出 token（留空不指定）', max_iterations: '最大迭代次数', connect_timeout: '连接超时（秒）', request_timeout: '请求超时（秒）', max_retries: '最大重试次数', stream_usage: '流式 usage',
      unsupported: '尚未支持的能力', disabled: '未启用', unsupportedHint: '备用模型仅在主模型出现网络错误、超时、5xx、429 或上游明确表示模型不可用时按顺序切换；400/401/403 等业务错误不切换，流式输出开始后不再切换。思考强度只放行模型能力中已声明的档位：Chat 传 reasoning_effort，Responses 传 reasoning.effort，Messages 按档位映射为 thinking.budget_tokens（须小于输出 Token 预算）。',
      save: '保存档案', saved: '档案已保存', testHint: '测试仅调用临时连接接口，不测试已保存的密钥，也不验证 token / stream 等运行参数。请重新输入本次测试密钥；留空按无密钥测试，不使用全局密钥。密钥不写入浏览器存储。', inherit: '使用当前默认档案', selectionHint: '新会话继承创建时的默认档案；更改默认不会修改已有会话和已冻结任务。', invalid: '请检查模型、URL、预算和超时范围', emptyModels: '模型目录为空，仍可手动输入模型 ID',
      selectProfile: '配置档案', newProfile: '新建档案', defaultBadge: '（默认档案）', nameLabel: '档案名称', connectionTitle: '连接与模型', preset: '供应商预设', applyPreset: '应用到连接', protocol: 'API 协议', ollamaHint: 'Ollama 官方本地地址为 http://localhost:11434/v1；本系统禁止本机、私网及 HTTP，请填写经授权的公网 HTTPS 网关地址。', providerId: '供应商标识', providerIdPlaceholder: '例如 openai-compatible', keyStatus: '密钥状态', defaultModel: '默认 Model', fetchModelsShort: '获取模型', fallbackTitle: '备用模型', fallbackEnable: '启用备用', fallbackList: '有序备用模型', fallbackAdd: '添加备用模型（最多 3 个）', moveUp: '上移', moveDown: '下移', removeFallback: '移除', tokenLimits: 'Token 限制', unspecified: '不指定', savedEffortIncompatible: '已保存档位 {effort} 不兼容当前选择，请明确选择默认或合法档位。', advancedTitle: '高级参数与模型能力', capabilitiesTitle: '模型能力（管理员声明）', capModel: '模型标识', capOutput: '输出参数', capContext: '模型上下文上限', capMaxOutput: '模型输出上限', unknown: '未知', imageFormats: '图片格式', removeCapability: '删除声明', addCapability: '添加模型能力声明',
      presetZhipu: '智谱 GLM', presetQwen: '通义千问（北京）', presetDoubao: '豆包（北京）', presetSiliconFlow: '硅基流动', presetGemini: 'Google Gemini（OpenAI 兼容）', presetOllama: 'Ollama（自备安全网关）', presetCustom: '自定义',
      presetConfirm: '再次点击应用将替换当前档案连接目标，并清空临时密钥；保存时必须提供新密钥或明确清空旧密钥。', targetChangedNeedKey: '连接目标已变更，请提供新密钥或明确清空旧密钥。', messagesOutputRequired: 'Messages 必须设置输出 Token 预算。', defaultShort: '（默认）'
    },
    providerSettings: {
      title: 'Provider 设置', securityNotice: 'API key 仅随本次连接测试发送，不回显、不写入浏览器存储。', apiKeyPlaceholder: '只发送，不回显',
      configured: '已配置：{masked}', connectTimeout: '连接超时', requestTimeout: '请求超时', testConnection: '测试连接', testSuccess: 'Provider 连接测试成功'
    },
    welcome: { description: '用自然语言描述需求，助手会分析代码、调用工具生成变更，并在高风险操作前请求你的审批。', analyze: '理解需求与代码', tools: '调用工具生成变更', approval: '高风险操作先审批', action: '新建会话' },
    toolCalls: { title: '工具调用', viewStdout: '查看 stdout', viewStderr: '查看 stderr', stdout: '标准输出', stderr: '标准错误' },
    statuses: {
      running: '运行中', paused: '已暂停', succeeded: '已成功', failed: '失败', cancelled: '已取消', pending: '待处理', reviewing: '审核中', applying: '应用中', proposed: '待应用', completed: '已完成', recovery_required: '需要恢复',
      approved: '已批准', rejected: '已拒绝', awaiting_approval: '等待审批', denied: '已拒绝', unknown: '未知', passed: '已通过'
    },
    enums: {
      statuses: { active: '活跃', running: '运行中', paused: '已暂停', succeeded: '已成功', failed: '失败', cancelled: '已取消', pending: '待处理', reviewing: '审核中', applying: '应用中', proposed: '待应用', completed: '已完成', recovery_required: '需要恢复', approved: '已批准', rejected: '已拒绝', awaiting_approval: '等待审批', denied: '已拒绝', unknown: '未知', passed: '已通过' },
      riskLevels: { low: '低风险', medium: '中风险', high: '高风险', critical: '严重风险' },
      operations: { apply_workspace: '应用工作区', write_workspace: '写入工作区', read_workspace: '读取工作区', run_command: '运行命令' },
      fileStatuses: { create: '新建', update: '更新', 'auto-merged': '自动合并', delete: '删除', 'keep-local': '保留本地', conflict: '文本冲突', 'binary-conflict': '二进制冲突', 'conflict-no-base': '无基线冲突' },
      taskStages: { planning: '规划', execution: '执行', review: '审核', applying: '应用', completed: '已完成' },
      taskTypes: { chat: '对话', crud: 'CRUD', code_change: '代码变更', fix: '修复', test: '测试', migration: '迁移' },
      changeSetStatuses: { proposed: '待应用', applying: '应用中', completed: '已完成', failed: '失败', recovery_required: '需要恢复' }
    },
    errors: { approvalModeUpdate: '审批模式更新失败，请检查权限后重试', applyUnavailable: '最终应用审批不存在或尚未批准', generic: '操作失败，请稍后重试', modelSaving: '模型正在保存，请稍后重试', cursorNotAdvanced: '消息分页游标未前进', finalApprovalUnavailable: '当前 ChangeSet 的最终 apply 审批不存在或尚未批准', profileDisabled: '档案已停用', noDefaultProfile: '没有默认档案', connectionFailed: '连接失败' },
    validation: {
      budgetExceeds: '{model} 的 Token 预算超过模型能力上限',
      capabilitiesInvalid: '模型能力声明无效、重复或预算超出范围',
      fallbackInvalid: '备用模型必须有序、去重、排除主模型，开启时须有 1 至 3 个候选',
      fallbackRequirements: '备用要求主模型及全部候选声明上下文、输出能力，并设置明确输出预算',
      imageInvalid: '图片能力声明无效，图片预算固定为 32768',
      invalidAttachment: '私有附件响应无效',
      invalidEntity: 'AI 接口未返回有效实体',
      invalidField: 'AI 接口 {field} 无效或超出安全整数范围',
      invalidList: 'AI 接口未返回有效列表',
      reasoningNotDeclared: '{model} 未声明支持所选推理档位，请选择默认或合法档位',
      thinkingBudgetExceeds: 'Messages 思考档位 {effort} 的思考预算 {budget} 必须小于输出 Token 预算，请调大输出预算或降低档位',
    },
  };
