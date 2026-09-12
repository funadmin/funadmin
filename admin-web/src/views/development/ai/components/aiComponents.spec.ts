import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ApprovalCard from './ApprovalCard.vue';
import ApprovalModeSelector from './ApprovalModeSelector.vue';
import ChangeSetDrawer from './ChangeSetDrawer.vue';
import FileDiffViewer from './FileDiffViewer.vue';
import MessageTimeline from './MessageTimeline.vue';
import ProviderSettingsDrawer from './ProviderSettingsDrawer.vue';

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
  ElForm: { template: '<form><slot /></form>' },
  ElFormItem: { template: '<label><slot /></label>' },
  ElSelect: { template: '<select><slot /></select>' },
  ElOption: { template: '<option />' },
  ElEmpty: { template: '<div />' }
};

const mountWithStubs = (component: Parameters<typeof mount>[0], props: Record<string, unknown>) => mount(component, { props, global: { stubs } });

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

  it('Provider API key 只在提交事件中发送且关闭即清空', async () => {
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, settings: { provider: { name: 'openai-compatible', base_url: '', model: '', connect_timeout: 5, request_timeout: 60, max_retries: 2, configured: true, masked: 'sk-••••' }, limits: {} } });
    const keyInput = wrapper.find('input[data-testid="provider-api-key"]');
    await keyInput.setValue('sk-secret');
    await wrapper.find('form').trigger('submit');
    expect(wrapper.emitted('test')?.[0]?.[0]).toMatchObject({ api_key: 'sk-secret' });
    await wrapper.setProps({ modelValue: false } as never);
    expect((keyInput.element as HTMLInputElement).value).toBe('');
    expect(JSON.stringify(wrapper.vm.$data)).not.toContain('localStorage');
  });
});
