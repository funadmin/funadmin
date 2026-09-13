import { mount, type VueWrapper } from '@vue/test-utils';
import ElementPlus, { ElSwitch, ElInput, ElInputNumber, ElForm } from 'element-plus';
import { readFileSync } from 'node:fs';
import { compileStyle, parse } from '@vue/compiler-sfc';
import { createI18n } from 'vue-i18n';
import { describe, expect, it, vi } from 'vitest';
vi.mock('@/api/development/ai', async importOriginal => ({ ...await importOriginal<object>(), aiDevelopmentApi: { attachmentContent: vi.fn().mockResolvedValue(new Blob(['safe'], { type: 'text/plain' })) } }));
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
const mountWithStubs = (component: Parameters<typeof mount>[0], props: Record<string, unknown>) => mount(component, { props, global: component === ProviderSettingsDrawer ? { plugins: [i18n, ElementPlus], stubs: { ElDrawer: stubs.ElDrawer } } : { plugins: [i18n], stubs } });
const control = (wrapper: ReturnType<typeof mount>, selector: string) => wrapper.getComponent(selector) as VueWrapper;
const chooseProfile = async (wrapper: ReturnType<typeof mount>) => {
  control(wrapper, '[data-testid="profile-select"]').vm.$emit('update:modelValue', 3);
  await wrapper.vm.$nextTick();
};

describe('AI Development components', () => {
  it('矮窗口使用真实 Drawer DOM 和编译后 CSS，正文可整体滚动而非被固定栏夹断', async () => {
    const wrapper = mount(ProviderSettingsDrawer, { props: { modelValue: true }, attachTo: document.body, global: { plugins: [i18n, ElementPlus] } });
    const style = document.createElement('style');
    try {
      await wrapper.vm.$nextTick();
      const source = readFileSync('src/views/development/ai/components/ProviderSettingsDrawer.vue', 'utf8');
      const { descriptor } = parse(source);
      const scopeId = (ProviderSettingsDrawer as unknown as { __scopeId: string }).__scopeId;
      const compiled = compileStyle({ source: descriptor.styles[0]!.content, filename: 'ProviderSettingsDrawer.vue', id: scopeId, scoped: true });
      expect(compiled.errors).toEqual([]);
      style.textContent = compiled.code;
      document.head.append(style);
      // jsdom 不执行媒体查询和几何布局；按桌面宽、400px 高选择真实 CSS 规则。
      const rules = Array.from(style.sheet!.cssRules);
      style.textContent = rules.map(rule => {
        if (rule instanceof CSSMediaRule) {
          const height = rule.conditionText.match(/max-height:\s*(\d+)px/);
          return height && 400 <= Number(height[1]) ? Array.from(rule.cssRules).map(item => item.cssText).join('\n') : '';
        }
        return rule.cssText;
      }).join('\n');
      const body = document.querySelector('.provider-drawer .el-drawer__body')!;
      const form = body.querySelector('.provider-form')!;
      const content = body.querySelector('.profile-content')!;
      expect(body).not.toBeNull();
      expect(getComputedStyle(body).overflowY || getComputedStyle(body).overflow).toBe('auto');
      expect(getComputedStyle(form).height).toBe('auto');
      expect(getComputedStyle(content).flexGrow).toBe('0');
      expect(getComputedStyle(content).flexShrink).toBe('0');
      expect(getComputedStyle(content).overflowY).toBe('visible');
      expect(body.querySelector('[data-testid="profile-save"]')).not.toBeNull();
    } finally {
      style.remove();
      wrapper.unmount();
    }
  });
  it('档案使用单列限宽布局、真实 EP 表单与独立底栏，能力默认折叠', () => {
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true });
    expect(wrapper.find('nav').exists()).toBe(false);
    expect(wrapper.find('fieldset').exists()).toBe(false);
    expect(wrapper.findComponent(ElForm).exists()).toBe(true);
    expect(wrapper.findComponent(ElSwitch).exists()).toBe(true);
    expect(wrapper.findComponent(ElInputNumber).exists()).toBe(true);
    expect(wrapper.get('[data-testid="provider-api-key"]').attributes('type')).toBe('password');
    expect(wrapper.get('[data-testid="profile-footer"]').find('[data-testid="profile-save"]').exists()).toBe(true);
    expect(wrapper.get('[data-testid="profile-content"]').find('[data-testid="profile-save"]').exists()).toBe(false);
    expect(wrapper.get('[data-testid="profile-advanced"]').attributes('open')).toBeUndefined();
    const source = readFileSync('src/views/development/ai/components/ProviderSettingsDrawer.vue', 'utf8');
    expect(source).toMatch(/max-width:\s*1000px/);
    expect(source).toMatch(/grid-template-columns:\s*repeat\(3,/);
    expect(source).toContain('overflow-y: auto');
    expect(source).not.toMatch(/(?:^|\n)\s*(?:input|label|fieldset|select)\s*[:{,]/);
  });
  it('真实开关清空密钥，档案操作和模型获取仍使用已保存 ID，忙碌时禁用', async () => {
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, profiles: [{ id: 3, name: '生产', model: 'm', base_url: 'https://example.com/v1', has_api_key: true }] });
    await chooseProfile(wrapper);
    await wrapper.get('[data-testid="provider-clear-key"] input').setValue(true);
    await wrapper.get('form').trigger('submit');
    expect(wrapper.emitted('save')?.[0]?.[0]).toHaveProperty('api_key', '');
    for (const [text, event] of [['复制', 'copy'], ['删除', 'remove'], ['设为默认', 'default'], ['获取模型', 'models']]) {
      await wrapper.findAll('button').find(button => button.text() === text)!.trigger('click');
      expect(wrapper.emitted(event)?.[0]).toEqual([3]);
    }
    await wrapper.setProps({ busy: true } as never);
    expect(wrapper.get('[data-testid="profile-test"]').attributes('disabled')).toBeDefined();
    expect(wrapper.get('[data-testid="fallback-enabled"] input').attributes('disabled')).toBeDefined();
    expect(wrapper.get('input[data-testid="provider-api-key"]').attributes('disabled')).toBeDefined();
  });
  it('附件引用提供私有下载入口，不静默渲染为空白', () => {
    const wrapper = mountWithStubs(MessageTimeline, { messages: [{ id: 1, conversation_id: 1, role: 'user', content: [{ type: 'attachment', attachment_id: 7 }], metadata: {} }] });
    expect(wrapper.find('[data-testid="private-attachment"]').exists()).toBe(true);
    expect(wrapper.find('a[href^="http"]').exists()).toBe(false);
  });
  it('新增模型能力声明包含默认关闭的图片能力', async () => {
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true });
    const add = wrapper.findAll('button').find(b => b.text() === '添加模型能力声明')!;
    await add.trigger('click');
    expect(wrapper.find('[data-testid="cap-image-0"]').exists()).toBe(true);
    expect(wrapper.get('[data-testid="cap-image-0"] input').attributes('aria-checked')).toBe('false');
  });
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
    expect(wrapper.text()).toContain('供应商标识');
    expect(wrapper.text()).toContain('Base URL');
    expect(wrapper.text()).toContain('Model');
    expect(wrapper.text()).toContain('API key');
  });

  it('预算超出上下文或模型为空时阻止提交并显示校验提示', async () => {
    const profile = { id: 3, name: '生产', provider: 'openai-compatible', protocol: 'openai-chat', base_url: 'https://example.com/v1', model: 'm', has_api_key: true, is_default: false, enabled: true, favorite_models: [], context_window: 100, max_input_tokens: 80, max_output_tokens: 30, max_iterations: 10, connect_timeout: 5, request_timeout: 60, max_retries: 2 };
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, profiles: [profile] });
    await chooseProfile(wrapper);
    await wrapper.find('form').trigger('submit');
    expect(wrapper.emitted('save')).toBeUndefined();
    expect(wrapper.find('[role="alert"]').text()).toContain('预算');
  });

  it('档案保存保留密钥，测试显式空密钥，备用可配置', async () => {
    const settings = { provider: { name: 'openai-compatible', base_url: '', model: '', connect_timeout: 5, request_timeout: 60, max_retries: 2 }, limits: {} };
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, settings, profiles: [], busy: false });
    expect(wrapper.find('[data-testid="profile-save"]').exists()).toBe(true);
    await wrapper.find('input[maxlength="100"]').setValue('测试');
    await wrapper.find('input[type="url"]').setValue('https://example.com/v1');
    control(wrapper, '[data-testid="profile-model"]').vm.$emit('update:modelValue', 'm');
    await wrapper.find('form').trigger('submit');
    expect(wrapper.emitted('save')?.[0]?.[0]).not.toHaveProperty('api_key');
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({ fallback_enabled: false, reasoning_effort: null });
    await wrapper.find('[data-testid="profile-test"]').trigger('click');
    expect(wrapper.emitted('test')?.[0]?.[0]).toMatchObject({ api_key: '', protocol: 'openai-chat' });
    expect(wrapper.find('[data-testid="fallback-enabled"]').attributes('disabled')).toBeUndefined();
    await wrapper.getComponent(ElInput).vm.$nextTick();
    await wrapper.get('input[data-testid="provider-api-key"]').setValue('sk-secret');
    await wrapper.find('form').trigger('submit');
    expect(wrapper.emitted('save')?.[1]?.[0]).toHaveProperty('api_key', 'sk-secret');
    await wrapper.setProps({ modelValue: false } as never);
    expect((wrapper.get('input[data-testid="provider-api-key"]').element as HTMLInputElement).value).toBe('');
  });

  it.each([
    { fallback_enabled: true, fallback_models: [] },
    { fallback_enabled: true, fallback_models: ['a', 'b', 'c', 'd'] },
    { fallback_models: ['m'] },
    { fallback_models: ['b', 'b'] },
    { reasoning_effort: 'high' },
    { max_output_tokens: 201 },
    { max_input_tokens: 950, max_output_tokens: 100 },
    { fallback_enabled: true, fallback_models: ['unknown'] }
  ])('能力或备用不合法时阻止保存：%j', async (invalid) => {
    const profile = { id: 3, name: '生产', provider: 'openai-compatible', protocol: 'openai-chat', base_url: 'https://example.com/v1', model: 'm', model_capabilities: [{ model: 'm', reasoning_efforts: ['low'], output_token_parameter: 'max_tokens', context_window: 1000, max_output_tokens: 200 }], ...invalid };
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, profiles: [profile] });
    await chooseProfile(wrapper);
    await wrapper.find('form').trigger('submit');
    expect(wrapper.emitted('save')).toBeUndefined();
    expect(wrapper.find('[role="alert"]').exists()).toBe(true);
  });

  it('六档仅按主备声明交集可选，默认提交 null', async () => {
    const efforts = ['low', 'medium', 'high', 'xhigh', 'max', 'ultra'];
    const profile = { id: 3, name: '六档', provider: 'openai-compatible', protocol: 'openai-chat', base_url: 'https://example.com/v1', model: 'm', reasoning_effort: null, fallback_enabled: true, fallback_models: ['b'], max_output_tokens: 100, model_capabilities: ['m', 'b'].map(model => ({ model, reasoning_efforts: efforts, output_token_parameter: 'max_tokens', context_window: 1000, max_output_tokens: 200 })) };
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, profiles: [profile] });
    await chooseProfile(wrapper);
    const select = control(wrapper, '[data-testid="reasoning-effort"]');
    expect(select.findAllComponents({ name: 'ElOption' }).map(o => o.props('value'))).toEqual(['', ...efforts]);
    for (const effort of ['xhigh', 'max', 'ultra']) {
      select.vm.$emit('update:modelValue', effort);
      await wrapper.find('form').trigger('submit');
      expect(wrapper.emitted('save')?.at(-1)?.[0]).toMatchObject({ reasoning_effort: effort });
    }
    select.vm.$emit('update:modelValue', '');
    await wrapper.find('form').trigger('submit');
    expect(wrapper.emitted('save')?.at(-1)?.[0]).toMatchObject({ reasoning_effort: null });
  });

  it('管理员编辑能力、选择合法档位并调整备用顺序，不修改原档案', async () => {
    const profile = { id: 3, name: '生产', provider: 'openai-compatible', protocol: 'openai-chat', base_url: 'https://example.com/v1', model: 'm', fallback_models: ['b', 'c'], model_capabilities: [{ model: 'm', reasoning_efforts: ['low'], output_token_parameter: 'max_tokens', context_window: 1000, max_output_tokens: 200 }] };
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, profiles: [profile] });
    await chooseProfile(wrapper);
    expect(wrapper.text()).toContain('管理员声明');
    expect(control(wrapper, '[data-testid="reasoning-effort"]').findAllComponents({ name: 'ElOption' }).map(o => o.props('value'))).toEqual(['', 'low']);
    control(wrapper, '[data-testid="reasoning-effort"]').vm.$emit('update:modelValue', 'low');
    await wrapper.find('[data-testid="fallback-up-1"]').trigger('click');
    control(wrapper, '[data-testid="cap-output-0"]').vm.$emit('update:modelValue', 'max_completion_tokens');
    await wrapper.find('form').trigger('submit');
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({ reasoning_effort: 'low', fallback_models: ['c', 'b'], model_capabilities: [{ output_token_parameter: 'max_completion_tokens' }] });
    expect(profile.model_capabilities[0].output_token_parameter).toBe('max_tokens');
  });

  it('已保存档案编辑不提交只读字段，切换档案清空密钥及模型目录', async () => {
    const profile = { id: 3, name: '生产', provider: 'openai-compatible', protocol: 'openai-chat', base_url: 'https://example.com/v1', model: 'm', has_api_key: true, is_default: true, favorite_models: ['m'], fallback_enabled: true, fallback_models: ['b'], reasoning_effort: 'high', max_output_tokens: 100, model_capabilities: ['m', 'b'].map(model => ({ model, reasoning_efforts: ['high'], output_token_parameter: 'max_completion_tokens', context_window: 1000, max_output_tokens: 200 })) };
    const wrapper = mountWithStubs(ProviderSettingsDrawer, { modelValue: true, profiles: [profile] });
    await chooseProfile(wrapper);
    expect(wrapper.text()).toContain('已保存密钥');
    await wrapper.find('form').trigger('submit');
    const payload = wrapper.emitted('save')?.[0]?.[0];
    expect(payload).toMatchObject({ name: '生产', fallback_enabled: true, fallback_models: ['b'], reasoning_effort: 'high', model_capabilities: profile.model_capabilities });
    expect(payload).not.toHaveProperty('id');
    expect(payload).not.toHaveProperty('has_api_key');
    expect(payload).not.toHaveProperty('api_key');
    await wrapper.getComponent(ElInput).vm.$nextTick();
    await wrapper.get('input[data-testid="provider-api-key"]').setValue('sk-private');
    await wrapper.get('[data-testid="profile-create"]').trigger('click');
    expect((wrapper.get('input[data-testid="provider-api-key"]').element as HTMLInputElement).value).toBe('');
    expect(wrapper.emitted('select')).toHaveLength(2);
  });
});
