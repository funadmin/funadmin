import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { describe, expect, it } from 'vitest';
import zhCN from '@/locales/zh-CN';
import ApprovalCard from './ApprovalCard.vue';
import ApprovalModeSelector from './ApprovalModeSelector.vue';
import ChangeSetDrawer from './ChangeSetDrawer.vue';
import FileDiffViewer from './FileDiffViewer.vue';
import MessageTimeline from './MessageTimeline.vue';
import ProviderSettingsDrawer from './ProviderSettingsDrawer.vue';
import ToolCallTimeline from './ToolCallTimeline.vue';

const approval = {
  id: 9,
  conversation_id: 1,
  task_id: 8,
  tool_call_id: 2,
  scope: 'once' as const,
  risk_reason: '将修改工作区文件',
  impact: { files: 2 },
  cas_version: 0,
  nonce: 'n',
  digest: 'd',
  operation: 'apply_workspace',
  mode_snapshot: 'request_approval' as const,
  status: 'pending' as const,
  request: {}
};

const stubs = {
  ElButton: { inheritAttrs: false, template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>', props: ['disabled'] },
  ElCard: { template: '<section><header v-if="$slots.header"><slot name="header" /></header><slot /></section>' },
  ElInput: { template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />', props: ['modelValue'] },
  ElRadioGroup: { template: '<div><slot /></div>' },
  ElRadioButton: { template: '<button :disabled="disabled"><slot /></button>', props: ['disabled', 'value'] },
  ElDrawer: { template: '<section><slot /><slot name="footer" /></section>' },
  ElCheckbox: { template: '<input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />', props: ['modelValue'] },
  ElTag: { template: '<span><slot /></span>' },
  ElAlert: { template: '<aside>{{ title }}</aside>', props: ['title'] },
  ElTimeline: { template: '<div><slot /></div>' },
  ElTimelineItem: { template: '<div><slot /></div>' },
  ElForm: { template: '<form><slot /></form>' },
  ElFormItem: { template: '<label>{{ label }}<slot /></label>', props: ['label'] },
  ElSelect: { name: 'ElSelect', template: '<select><slot /></select>' },
  ElOption: { template: '<option />' },
  ElEmpty: { template: '<div />' }
};

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
const mountWithStubs = (component: Parameters<typeof mount>[0], props: Record<string, unknown>) => mount(component, { props, global: { plugins: [i18n], stubs } });

describe('AI Development components', () => {
  it('消息仅以文本和 code 节点渲染，不解释恶意 HTML', () => {
    const wrapper = mountWithStubs(MessageTimeline, { messages: [{ id: 1, conversation_id: 1, parent_id: null, sequence: 1, role: 'assistant', content: [{ type: 'text', text: '<img src=x onerror=alert(1)>' }, { type: 'code', language: 'ts', text: 'const safe = true;' }], metadata: {} }] });
    expect(wrapper.find('img').exists()).toBe(false);
    expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
    expect(wrapper.find('code').text()).toContain('const safe = true;');
    expect(MessageTimeline.__file).toBeTruthy();
  });

  it('审批卡支持 once、session_operation 与带反馈拒绝', async () => {
    const wrapper = mountWithStubs(ApprovalCard, { approval });
    const buttons = wrapper.findAll('button');
    await buttons.filter((button) => button.text().includes('仅本次'))[0].trigger('click');
    await buttons.filter((button) => button.text().includes('会话操作'))[0].trigger('click');
    await wrapper.find('textarea').setValue('风险不可接受');
    await buttons.filter((button) => button.text().includes('拒绝'))[0].trigger('click');
    expect(wrapper.emitted('decision')).toEqual([
      ['approve', 'once', ''],
      ['approve', 'session_operation', ''],
      ['reject', 'once', '风险不可接受']
    ]);
    expect(wrapper.text()).toContain('将修改工作区文件');
  });

  it('无 capability 时禁用 full_access 并显示 403 边界', () => {
    const wrapper = mountWithStubs(ApprovalModeSelector, { modelValue: 'request_approval', canAgentApprove: true, canFullAccess: false });
    expect(wrapper.text()).toContain('403');
    expect(wrapper.findAll('button').some((button) => button.attributes('disabled') !== undefined && button.text().includes('完全访问权限'))).toBe(true);
  });

  it('ChangeSet 逐文件选择、冲突阻断且 apply 前必须 preview 和二次确认', async () => {
    const files = [{ path: 'safe.ts', status: 'update', contentKind: 'text' }, { path: 'conflict.ts', status: 'conflict', contentKind: 'text' }];
    const wrapper = mountWithStubs(ChangeSetDrawer, { modelValue: true, files, preview: null, testStatus: 'passed', securityStatus: 'passed' });
    await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);
    await wrapper.findAll('button').find((button) => button.text().includes('预览'))?.trigger('click');
    expect(wrapper.emitted('preview')?.[0]).toEqual([['safe.ts']]);
    expect(wrapper.text()).toContain('预览后才能应用');
    await wrapper.setProps({ preview: { changeSetId: 3, blocked: true, files, selection: ['safe.ts'], planDigest: 'digest', confirmToken: 'token' } } as never);
    expect(wrapper.text()).toContain('冲突阻断');
    expect(wrapper.emitted('apply')).toBeUndefined();
  });

  it('Diff 对二进制和截断内容安全降级', () => {
    const binary = mountWithStubs(FileDiffViewer, { file: { path: 'logo.png', status: 'binary-conflict', contentKind: 'binary', contentOmitted: true } });
    expect(binary.text()).toContain('二进制');
    expect(binary.text()).toContain('内容已省略');
  });

  it('AI 后端枚举和固定字段使用翻译，未知值回退原始值', () => {
    const approvalWrapper = mountWithStubs(ApprovalCard, { approval: { ...approval, operation: 'apply_workspace' } });
    expect(approvalWrapper.text()).toContain('应用工作区');
    expect(approvalWrapper.text()).not.toContain('apply_workspace');

    const toolWrapper = mountWithStubs(ToolCallTimeline, { toolCalls: [{ id: 1, conversation_id: 1, task_id: 1, message_id: null, approval_id: null, tool_name: 'write_file', operation: 'write_workspace', risk_level: 'high', status: 'awaiting_approval', approval_decision: 'pending', redacted_arguments: {}, stdout_summary: '准备写入文件' }, { id: 2, conversation_id: 1, task_id: 1, message_id: null, approval_id: null, tool_name: 'unknown_tool', operation: 'unknown_operation', risk_level: 'unknown', status: 'unknown', approval_decision: 'pending', redacted_arguments: {} }] });
    expect(toolWrapper.text()).toContain('高风险');
    expect(toolWrapper.text()).toContain('写入工作区');
    expect(toolWrapper.text()).toContain('unknown_operation');
    expect(toolWrapper.text()).not.toContain('stdout：');
    expect(toolWrapper.text()).toContain('标准输出：准备写入文件');

    const changeSetWrapper = mountWithStubs(ChangeSetDrawer, { modelValue: true, files: [{ path: 'safe.ts', status: 'update', contentKind: 'text' }], preview: null, testStatus: 'passed', securityStatus: 'passed' });
    expect(changeSetWrapper.text()).toContain('更新');
    expect(changeSetWrapper.text()).toContain('已通过');

    const diffWrapper = mountWithStubs(FileDiffViewer, { file: { path: 'safe.ts', status: 'update', contentKind: 'text', baseHash: 'base', localHash: 'local', remoteHash: 'remote' } });
    expect(diffWrapper.text()).toContain('基线');
    expect(diffWrapper.text()).toContain('本地');
    expect(diffWrapper.text()).toContain('远端');
  });

  it('Provider 字段使用翻译标签', () => {
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, settings: { provider: { name: 'openai-compatible', base_url: '', model: '', connect_timeout: 5, request_timeout: 60, max_retries: 2 }, limits: {} } });
    expect(wrapper.text()).toContain('Provider');
    expect(wrapper.text()).toContain('Base URL');
    expect(wrapper.text()).toContain('Model');
    expect(wrapper.text()).toContain('API key');
  });

  it('预算超出上下文或模型为空时阻止提交并显示校验提示', async () => {
    const profile = { id: 3, name: '生产', provider: 'openai-compatible', protocol: 'openai-chat', base_url: 'https://example.com/v1', model: 'm', has_api_key: true, is_default: false, enabled: true, favorite_models: [], context_window: 100, max_input_tokens: 80, max_output_tokens: 30, max_iterations: 10, connect_timeout: 5, request_timeout: 60, max_retries: 2 };
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, profiles: [profile] });
    await wrapper.findAll('nav button')[1].trigger('click');
    await wrapper.find('form').trigger('submit');
    expect(wrapper.emitted('save')).toBeUndefined();
    expect(wrapper.find('[role="alert"]').text()).toContain('预算');
  });

  it('档案保存保留密钥，测试显式空密钥，未支持功能不可启用', async () => {
    const settings = { provider: { name: 'openai-compatible', base_url: '', model: '', connect_timeout: 5, request_timeout: 60, max_retries: 2 }, limits: {} };
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, settings, profiles: [], busy: false });
    expect(wrapper.find('[data-testid="profile-save"]').exists()).toBe(true);
    await wrapper.find('input[maxlength="100"]').setValue('测试');
    await wrapper.find('input[type="url"]').setValue('https://example.com/v1');
    wrapper.findAllComponents({ name: 'ElSelect' })[0]?.vm.$emit('update:modelValue', 'm');
    await wrapper.find('form').trigger('submit');
    expect(wrapper.emitted('save')?.[0]?.[0]).not.toHaveProperty('api_key');
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({ fallback_enabled: false, reasoning_effort: null });
    await wrapper.find('[data-testid="profile-test"]').trigger('click');
    expect(wrapper.emitted('test')?.[0]?.[0]).toMatchObject({ api_key: '', protocol: 'openai-chat' });
    expect(wrapper.find('[data-testid="fallback-disabled"]').attributes('disabled')).toBeDefined();
    await wrapper.find('[data-testid="provider-api-key"]').setValue('sk-secret');
    await wrapper.find('form').trigger('submit');
    expect(wrapper.emitted('save')?.[1]?.[0]).toHaveProperty('api_key', 'sk-secret');
    await wrapper.setProps({ modelValue: false } as never);
    expect((wrapper.find('[data-testid="provider-api-key"]').element as HTMLInputElement).value).toBe('');
  });

  it('已保存档案编辑不提交只读字段，切换档案清空密钥及模型目录', async () => {
    const profile = { id: 3, name: '生产', provider: 'openai-compatible', protocol: 'openai-chat', base_url: 'https://example.com/v1', model: 'm', has_api_key: true, is_default: true, favorite_models: ['m'], fallback_enabled: true, reasoning_effort: 'high' };
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, profiles: [profile] });
    await wrapper.findAll('nav button')[1].trigger('click');
    expect(wrapper.text()).toContain('已保存密钥');
    await wrapper.find('form').trigger('submit');
    const payload = wrapper.emitted('save')?.[0]?.[0];
    expect(payload).toMatchObject({ name: '生产', fallback_enabled: false, reasoning_effort: null });
    expect(payload).not.toHaveProperty('id');
    expect(payload).not.toHaveProperty('has_api_key');
    expect(payload).not.toHaveProperty('api_key');
    await wrapper.find('[data-testid="provider-api-key"]').setValue('sk-private');
    await wrapper.findAll('nav button')[0].trigger('click');
    expect((wrapper.find('[data-testid="provider-api-key"]').element as HTMLInputElement).value).toBe('');
    expect(wrapper.emitted('select')).toHaveLength(2);
  });
});
