export default {
    title: 'AI Development Assistant',
    subtitle: 'Conversations, tool approvals, and workspace changes',
    reasoning: {
      profile: 'Reasoning mode', conversation: 'Conversation reasoning effort', levels: 'Reasoning efforts',
      default: 'Default', defaultOption: 'Default (omit reasoning_effort)', inherit: 'Inherit profile', override: 'Conversation override', noProfile: 'No profile selected',
      capabilityHint: 'Capabilities are declared per model by an administrator, not officially verified or inferred from names or catalogs. Allowed declarations: low, medium, high, xhigh, max, ultra. Only openai-chat currently passes reasoning_effort unchanged; no level conversion or upstream support is guaranteed.'
    },
    modelSelection: {
      current: 'Current conversation model', unset: 'Not set', id: 'Model ID', save: 'Save model',
      hint: 'Only the currently configured provider is supported. Enter a model ID it supports. Changes affect only tasks created after saving; the current task keeps its original model.',
      failed: 'Could not save the model. Check the model ID or retry',
      helpAria: 'Model and reasoning settings help', helpTrigger: 'Settings help', taskSnapshot: 'Only affects newly created tasks; running tasks keep their snapshot.',
      profileSummary: 'Profile default model: {model}; tokens input {input} / output {output} / context {context}', unspecified: 'Unspecified'
    },
    provider: 'Provider',
    fields: { base: 'Base', local: 'Local', remote: 'Remote', baseUrl: 'Base URL', model: 'Model', apiKey: 'API key' },
    task: 'Task',
    workspace: 'Workspace',
    conversations: 'Conversations',
    management: {
      more: 'More conversation actions', viewArchived: 'View archived', createGroup: 'New group', deleteGroup: 'Delete group', groupName: 'Group name', ungrouped: 'Ungrouped',
      rename: 'Rename', move: 'Move to group', archive: 'Archive', restore: 'Restore', delete: 'Delete',
      archived: 'Archived', active: 'Back to conversations', unread: 'Unread', markRead: 'Mark as read', markUnread: 'Mark as unread', actions: 'Conversation actions',
      title: 'Conversation title', confirm: 'Confirm', cancel: 'Cancel', required: 'Name cannot be empty',
      deleteGroupConfirm: 'Deleting this group will archive all its conversations. Continue?', deleteConfirm: 'Delete this conversation? This cannot be undone.',
      failed: 'Conversation operation failed. Please retry.', syncFailed: 'Messages or read status could not be synchronized. Reopen the conversation.'
    },
    pagination: { filter: 'Filter', more: 'Load more', older: 'Load older messages', search: 'Search conversations', unreadFilter: 'Read status', allReadStates: 'All read states', groupFilter: 'Filter by group', allGroups: 'All groups' },
    newConversation: 'New',
    unnamedConversation: 'Untitled conversation',
    noConversations: 'No conversations',
    selectConversation: 'Select or create a conversation',
    stop: 'Stop',
    send: 'Send',
    promptPlaceholder: 'Describe what to analyze or change. High-risk operations pause according to the approval mode.',
    newConversationTitle: 'New AI conversation',
    taskContext: 'Task context',
    settings: { title: 'Conversation settings', open: 'Open conversation settings', reasoningShort: 'Reasoning {effort}', model: 'Model', reasoning: 'Reasoning', approval: 'Approval mode', done: 'Done' },
    taskDetails: 'Task details',
    pendingApprovalCount: '{count} pending approvals',
    taskAndPermissions: 'Task and permissions',
    taskOverview: 'Task overview',
    riskAndApproval: 'Risks and approvals',
    stage: 'Stage',
    status: 'Status',
    test: 'Tests',
    risk: 'Risk',
    noPendingRisk: 'No risks awaiting approval',
    noActiveTask: 'No active task',
    toolLog: 'Tool log',
    messages: { empty: 'Start a new AI conversation', copy: 'Copy', copied: 'Copied', roles: { system: 'System', user: 'You', assistant: 'AI Assistant', tool: 'Tool' } },
    approvalModes: {
      request: 'Request approval', agent: 'Approve for me', fullAccess: 'Full access',
      requestBoundary: 'Ask for approval before each dangerous tool execution.',
      agentBoundary: 'Allow the agent to approve only within granted capabilities.',
      fullAccessBoundary: 'Allow high-risk operations, while final ChangeSet application still requires confirmation.',
      missingCapability: '403: missing development:ai:full-access capability',
      unavailable: '403: full access is unavailable'
    },
    approval: {
      required: 'Approval required', defaultRisk: 'This operation requires manual confirmation', details: 'Risk details and impact',
      feedbackPlaceholder: 'Optional feedback when rejecting', allowOnce: 'Allow once', allowSession: 'Allow for session operation', reject: 'Reject'
    },
    changeSet: {
      selectedCount: '{selected} / {total} files selected',
      entity: 'ChangeSet', title: 'ChangeSet / Diff', test: 'Tests: {status}', security: 'Security: {status}', fileSelectionOnly: 'Select whole files; hunk selection is unavailable',
      conflictBlocked: 'Blocked by conflicts. Deselect conflicting files or regenerate the ChangeSet.', previewRequired: 'Preview before applying. Final application always requires confirmation.',
      close: 'Close', preview: 'Preview', confirmApply: 'Confirm and apply', applyConfirm: 'The selected files will be applied to the workspace. Confirm again.',
      applyConfirmTitle: 'Final application confirmation', applyConfirmButton: 'Apply', applied: 'ChangeSet applied'
    },
    diff: { binary: 'Binary files cannot display a text diff', omitted: 'Content omitted because the file is binary or exceeds the display limit', missing: 'The backend did not return diff content; only file status and hashes are shown.' },
    profiles: {
      basicTitle: 'Basics', unsaved: 'Unsaved', defaultTag: 'Default', listEmpty: 'No profiles yet. Create one above.', keySaved: 'Key saved', keyMissing: 'No key',
      title: 'Configuration profiles', create: 'Create profile', general: 'Profile settings', name: 'Name (save to rename)', enabled: 'Enabled', copy: 'Copy', default: 'Set default', copyHint: 'Copies exclude credentials and default status. Deleting the default does not select another profile.',
      protocolHint: 'Only openai-chat (OpenAI Chat Completions compatible) is supported. Provider is an identifier, not support for other protocols.', keyHint: 'Leave blank to keep the saved key', hasKey: 'Key saved (never displayed)', noKey: 'No saved key', clearKey: 'Clear key on save',
      models: 'Models', fetchModels: 'Fetch saved profile models', modelsHint: 'Uses saved URL and credentials, not unsaved edits. Search, select or enter an ID; capabilities are not inferred.', favorites: 'Favorite models', limits: 'Context and runtime limits', context_window: 'Context window (blank: unspecified)', max_input_tokens: 'Max input tokens (blank: unspecified)', max_output_tokens: 'Max output tokens (blank: unspecified)', max_iterations: 'Max iterations', connect_timeout: 'Connect timeout (seconds)', request_timeout: 'Request timeout (seconds)', max_retries: 'Max retries', stream_usage: 'Stream usage',
      unsupported: 'Unsupported capabilities', disabled: 'Disabled', unsupportedHint: 'Fallback is not implemented; reasoning capabilities are unverified. Non-default execution is rejected. Saving disables fallback and clears reasoning.', save: 'Save profile', saved: 'Profile saved', testHint: 'Temporary connection test only: saved credentials and token/stream parameters are not tested. Re-enter a test key; blank explicitly tests without a key, never with the global key. Keys are not stored in the browser.', inherit: 'Use current default profile', selectionHint: 'New conversations inherit the default at creation. Changing the default does not modify existing conversations or frozen tasks.', invalid: 'Check model, URL, budgets and timeout ranges', emptyModels: 'Empty model catalog; model IDs can still be entered manually',
      selectProfile: 'Profile', newProfile: 'New profile', defaultBadge: ' (default)', nameLabel: 'Profile name', connectionTitle: 'Connection & model', preset: 'Provider preset', applyPreset: 'Apply to connection', protocol: 'API protocol', ollamaHint: 'The official Ollama local URL is http://localhost:11434/v1; this system forbids local, private-network and HTTP targets. Use an authorized public HTTPS gateway address.', providerId: 'Provider ID', providerIdPlaceholder: 'e.g. openai-compatible', keyStatus: 'Key status', defaultModel: 'Default model', fetchModelsShort: 'Fetch models', fallbackTitle: 'Fallback models', fallbackEnable: 'Enable fallback', fallbackList: 'Ordered fallbacks', fallbackAdd: 'Add fallback (max 3)', moveUp: 'Up', moveDown: 'Down', removeFallback: 'Remove', tokenLimits: 'Token limits', unspecified: 'Unspecified', savedEffortIncompatible: 'Saved effort {effort} is incompatible with the current selection; choose Default or a legal effort.', advancedTitle: 'Advanced parameters & model capabilities', capabilitiesTitle: 'Model capabilities (admin declared)', capModel: 'Model ID', capOutput: 'Output parameter', capContext: 'Context limit', capMaxOutput: 'Output limit', unknown: 'Unknown', imageFormats: 'Image formats', removeCapability: 'Remove declaration', addCapability: 'Add capability declaration',
      presetZhipu: 'Zhipu GLM', presetQwen: 'Qwen (Beijing)', presetDoubao: 'Doubao (Beijing)', presetSiliconFlow: 'SiliconFlow', presetGemini: 'Google Gemini (OpenAI compatible)', presetOllama: 'Ollama (bring your own secure gateway)', presetCustom: 'Custom',
      presetConfirm: 'Click Apply again to replace the connection target of this profile and clear the temporary key; saving then requires a new key or explicitly clearing the old one.', targetChangedNeedKey: 'Connection target changed; provide a new key or explicitly clear the old key.', reasoningProtocolUnsupported: 'Responses / Messages do not support explicit reasoning yet; choose Default and use a non-thinking model.', messagesOutputRequired: 'Messages requires an output token budget.', protocolReasoningUnsupported: 'The current protocol does not support explicit reasoning.', defaultShort: ' (default)'
    },
    providerSettings: {
      title: 'Provider settings', securityNotice: 'The API key is sent only for this connection test. It is never displayed or stored in the browser.', apiKeyPlaceholder: 'Send only; never display',
      configured: 'Configured: {masked}', connectTimeout: 'Connection timeout', requestTimeout: 'Request timeout', testConnection: 'Test connection', testSuccess: 'Provider connection test succeeded'
    },
    welcome: { description: 'Describe what you need in plain language. The assistant analyzes code, uses tools to prepare changes, and asks for your approval before risky operations.', analyze: 'Understand requirements and code', tools: 'Use tools to prepare changes', approval: 'Approve risky operations first', action: 'New conversation' },
    toolCalls: { title: 'Tool calls', viewStdout: 'View stdout', viewStderr: 'View stderr', stdout: 'stdout', stderr: 'stderr' },
    statuses: {
      running: 'Running', paused: 'Paused', succeeded: 'Succeeded', failed: 'Failed', cancelled: 'Cancelled', pending: 'Pending', reviewing: 'Reviewing', applying: 'Applying', proposed: 'Proposed', completed: 'Completed', recovery_required: 'Recovery required',
      approved: 'Approved', rejected: 'Rejected', awaiting_approval: 'Awaiting approval', denied: 'Denied', unknown: 'Unknown', passed: 'Passed'
    },
    enums: {
      statuses: { active: 'Active', running: 'Running', paused: 'Paused', succeeded: 'Succeeded', failed: 'Failed', cancelled: 'Cancelled', pending: 'Pending', reviewing: 'Reviewing', applying: 'Applying', proposed: 'Proposed', completed: 'Completed', recovery_required: 'Recovery required', approved: 'Approved', rejected: 'Rejected', awaiting_approval: 'Awaiting approval', denied: 'Denied', unknown: 'Unknown', passed: 'Passed' },
      riskLevels: { low: 'Low risk', medium: 'Medium risk', high: 'High risk', critical: 'Critical risk' },
      operations: { apply_workspace: 'Apply to workspace', write_workspace: 'Write to workspace', read_workspace: 'Read workspace', run_command: 'Run command' },
      fileStatuses: { create: 'Create', update: 'Update', 'auto-merged': 'Auto-merged', delete: 'Delete', 'keep-local': 'Keep local', conflict: 'Text conflict', 'binary-conflict': 'Binary conflict', 'conflict-no-base': 'Conflict without base' },
      taskStages: { planning: 'Planning', execution: 'Execution', review: 'Review', applying: 'Applying', completed: 'Completed' },
      taskTypes: { chat: 'Chat', crud: 'CRUD', code_change: 'Code change', fix: 'Fix', test: 'Test', migration: 'Migration' },
      changeSetStatuses: { proposed: 'Proposed', applying: 'Applying', completed: 'Completed', failed: 'Failed', recovery_required: 'Recovery required' }
    },
    errors: { approvalModeUpdate: 'Could not update the approval mode. Check your permissions and try again.', applyUnavailable: 'Final application approval is missing or has not been granted', generic: 'The operation failed. Please try again later.', modelSaving: 'The model is being saved. Please retry shortly.', cursorNotAdvanced: 'The message pagination cursor did not advance', finalApprovalUnavailable: 'The final apply approval for the current ChangeSet is missing or has not been granted', profileDisabled: 'The profile is disabled', noDefaultProfile: 'No default profile', connectionFailed: 'Connection failed' },
    validation: {
      budgetExceeds: 'The token budget of {model} exceeds the model capability limit',
      capabilitiesInvalid: 'Model capability declaration is invalid, duplicated, or the budget is out of range',
      fallbackInvalid: 'Fallback models must be ordered, deduplicated, exclude the primary model, and contain 1 to 3 candidates when enabled',
      fallbackRequirements: 'Fallback requires the primary model and all candidates to declare context and output capabilities with an explicit output budget',
      imageInvalid: 'Image capability declaration is invalid; the image budget is fixed at 32768',
      invalidAttachment: 'Invalid private attachment response',
      invalidEntity: 'The AI API did not return a valid entity',
      invalidField: 'The AI API field {field} is invalid or out of safe integer range',
      invalidList: 'The AI API did not return a valid list',
      reasoningNotDeclared: '{model} does not declare support for the selected reasoning level; choose default or a valid level',
    },
  };
