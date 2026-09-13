import { computed, defineComponent, inject, nextTick, provide, reactive, ref, type InjectionKey, type Ref } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import ElementPlus, { ElMessageBox, ElSelect, ElOption, ElInput, ElButton } from 'element-plus';
import { aiDevelopmentApi } from '@/api/development/ai';
import ConversationList from './components/ConversationList.vue';
import ProviderSettingsDrawer from './components/ProviderSettingsDrawer.vue';
import { createPinia, setActivePinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import zhCN from '@/locales/zh-CN';
import enUS from '@/locales/en-US';

const aiStore = reactive({
  conversations: [] as Array<Record<string, unknown>>,
  conversationGroups: [] as Array<Record<string, unknown>>,
  selectedConversationId: null as number | null,
  messages: [] as Array<Record<string, unknown>>,
  activeTask: null as Record<string, unknown> | null,
  approvals: [] as Array<Record<string, unknown>>,
  toolCalls: [] as Array<Record<string, unknown>>,
  changeSet: null as Record<string, unknown> | null,
  restoreRouteState: vi.fn().mockResolvedValue(undefined),
  refreshTaskContext: vi.fn().mockResolvedValue(undefined),
  connectEvents: vi.fn().mockResolvedValue(undefined),
  closeEvents: vi.fn(),
  clearWorkspace: vi.fn(),
  selectionGeneration: 0,
  updateConversationState: vi.fn().mockResolvedValue(undefined),
  updateConversationModel: vi.fn().mockResolvedValue(undefined),
  updateConversationReasoning: vi.fn().mockResolvedValue(undefined),
  deleteConversation: vi.fn().mockResolvedValue(undefined),
  deleteConversationGroup: vi.fn().mockResolvedValue(undefined),
  selectConversation: vi.fn().mockResolvedValue(undefined),
  cancelActiveTask: vi.fn().mockResolvedValue(undefined),
  decideApproval: vi.fn().mockResolvedValue(undefined),
  activateTask: vi.fn(),
  saveRouteState: vi.fn(),
  applyChangeSet: vi.fn().mockResolvedValue(undefined)
});

vi.mock('@/store/modules/aiDevelopment', () => ({ useAiDevelopmentStore: () => aiStore }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ permissions: [] }) }));
vi.mock('@/api/development/ai', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/development/ai')>();
  return {
    ...actual,
    aiDevelopmentApi: {
      profiles: vi.fn().mockResolvedValue([]),
      defaultProfile: vi.fn().mockResolvedValue(null),
      createProfile: vi.fn(), updateProfile: vi.fn(), copyProfile: vi.fn(), deleteProfile: vi.fn(), makeDefaultProfile: vi.fn(), profileModels: vi.fn(),
      conversations: vi.fn().mockResolvedValue([]),
      conversationGroups: vi.fn().mockResolvedValue([]),
      createConversationGroup: vi.fn(),
      updateConversationGroup: vi.fn(),
      deleteConversationGroup: vi.fn(),
      createConversation: vi.fn(),
      updateConversation: vi.fn(),
      createMessage: vi.fn(),
      executeTask: vi.fn(),
      previewChangeSet: vi.fn(),
      toolLog: vi.fn(),
      settings: vi.fn(),
      testSettings: vi.fn()
    }
  };
});

import AiDevelopment from './index.vue';

const pageSource = readFileSync(resolve(process.cwd(), 'src/views/development/ai/index.vue'), 'utf8');
const timelineSource = readFileSync(resolve(process.cwd(), 'src/views/development/ai/components/MessageTimeline.vue'), 'utf8');

type TabsContext = { active: Ref<string>; select: (name: string) => void };
const tabsKey: InjectionKey<TabsContext> = Symbol('ai-tabs');
const ElTabsStub = defineComponent({
  props: { modelValue: { type: String, required: true } },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const active = computed(() => props.modelValue);
    provide(tabsKey, { active, select: (name) => emit('update:modelValue', name) });
    return {};
  },
  template: '<nav data-testid="mobile-tabs"><slot /></nav>'
});
const ElTabPaneStub = defineComponent({
  props: { label: { type: String, required: true }, name: { type: String, required: true } },
  setup() { return { tabs: inject(tabsKey)! }; },
  template: '<button type="button" :data-tab="name" @click="tabs.select(name)">{{ label }}</button>'
});
const passthrough = defineComponent({ template: '<section><slot name="header" /><slot name="extra" /><slot /></section>' });
const buttonStub = defineComponent({
  props: ['disabled', 'type', 'nativeType'],
  emits: ['click'],
  template: '<button :disabled="disabled" :type="nativeType || \'button\'" @click="$emit(\'click\')"><slot /></button>'
});
const inputStub = defineComponent({
  props: ['modelValue', 'placeholder'],
  emits: ['update:modelValue'],
  template: '<textarea :placeholder="placeholder" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />'
});
const emptyStub = defineComponent({ props: ['description'], template: '<p class="el-empty">{{ description }}</p>' });
const drawerStub = defineComponent({ props: ['modelValue', 'title'], template: '<aside v-if="modelValue"><h2>{{ title }}</h2><slot /><slot name="footer" /></aside>' });
const radioGroupStub = defineComponent({ template: '<div><slot /></div>' });
const radioButtonStub = defineComponent({ props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' });
const descriptionsStub = defineComponent({ template: '<dl><slot /></dl>' });
const descriptionsItemStub = defineComponent({ props: ['label'], template: '<dt>{{ label }}</dt><dd><slot /></dd>' });

const stubs = {
  ElBadge: passthrough,
  ElForm: passthrough,
  ElFormItem: passthrough,
  ElTooltip: defineComponent({ template: '<div><slot /><slot name="content" /></div>' }),
  ElPopover: defineComponent({ props: ['visible'], emits: ['update:visible'], template: '<div><div @click="$emit(\'update:visible\', !visible)"><slot name="reference" /></div><slot /></div>' }),
  ElSwitch: true,
  ElInputNumber: true,
  ElCheckboxGroup: passthrough,
  PageWrapper: passthrough,
  ElButton: buttonStub,
  ElInput: inputStub,
  ElEmpty: emptyStub,
  ElDropdown: defineComponent({ emits: ['command'], template: '<div><slot /><slot name="dropdown" /></div>' }),
  ElDropdownMenu: passthrough,
  ElDropdownItem: passthrough,
  ElSelect: true,
  ElOption: true,
  ElCheckbox: defineComponent({ template: '<input type="checkbox" />' }),
  ElDrawer: drawerStub,
  ElDialog: drawerStub,
  ElTabs: ElTabsStub,
  ElTabPane: ElTabPaneStub,
  ElRadioGroup: radioGroupStub,
  ElRadioButton: radioButtonStub,
  ElDescriptions: descriptionsStub,
  ElDescriptionsItem: descriptionsItemStub,
  ElTimeline: passthrough,
  ElTimelineItem: passthrough,
  ElTag: passthrough,
  ElAlert: defineComponent({ props: ['title'], template: '<p>{{ title }}</p>' })
};

const setMobile = (mobile: boolean) => {
  Object.defineProperty(window, 'matchMedia', {
    configurable: true,
    value: vi.fn().mockImplementation(() => ({
      matches: mobile,
      media: '(max-width: 1024px)',
      onchange: null,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn(),
      dispatchEvent: vi.fn()
    }))
  });
};

const mountPage = async (locale: 'zh-CN' | 'en-US', mobile = false) => {
  setMobile(mobile);
  const i18n = createI18n({ legacy: false, locale, messages: { 'zh-CN': zhCN, 'en-US': enUS } });
  const wrapper = mount(AiDevelopment, { global: { plugins: [i18n], stubs } });
  await wrapper.get('[data-testid="model-menu-trigger"]').trigger('click');
  return { wrapper, locale: i18n.global.locale };
};

const visibleRegions = (wrapper: ReturnType<typeof mount>) => wrapper
  .findAll('[data-ai-region]')
  .filter((region) => region.attributes('style') !== 'display: none;')
  .map((region) => region.attributes('data-ai-region'));

const keys = (value: unknown, prefix = ''): string[] => Object.entries(value as Record<string, unknown>).flatMap(([key, child]) => {
  const path = prefix ? `${prefix}.${key}` : key;
  return child && typeof child === 'object' ? keys(child, path) : [path];
});

describe('AI Development 真实 i18n 与响应式区域', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    aiStore.conversations = [];
    aiStore.conversationGroups = [];
    aiStore.selectedConversationId = null;
    aiStore.messages = [];
    aiStore.activeTask = null;
    aiStore.approvals = [];
    aiStore.toolCalls = [];
    aiStore.changeSet = null;
    vi.clearAllMocks();
  });

  it.each([false, true])('任务权限弹窗保持实例、审批去重且关闭不触发业务调用，移动端=%s', async (mobile) => {
    setMobile(mobile);
    aiStore.conversations = [{ id: 1, approval_mode: 'request_approval' }];
    aiStore.selectedConversationId = 1;
    aiStore.activeTask = { id: 7, conversation_id: 1, type: 'code_change', stage: 'review', status: 'paused' };
    const approval = { id: 11, task_id: 7, conversation_id: 1, status: 'pending', operation: 'write_workspace', impact: {}, risk_reason: '写入文件' };
    aiStore.approvals = [approval, { ...approval }, { ...approval, id: 12, status: 'approved' }, { ...approval, id: 13, status: 'denied' }];
    aiStore.toolCalls = [{ id: 21, approval_id: 11, status: 'awaiting_approval' }];
    const wrapper = mount(AiDevelopment, { attachTo: document.body, global: {
      plugins: [ElementPlus, createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } })],
      stubs: { ...stubs, transition: false, ElDialog: false, ElBadge: false, ElCard: passthrough, ProviderSettingsDrawer: true }
    } });
    try {
      await flushPromises();
      expect(wrapper.get('[data-testid="toggle-inspector"]').text()).toBe('任务与权限');
      expect(wrapper.get('[data-testid="pending-approvals-badge"]').text()).toContain('1');
      expect(wrapper.find('[data-ai-region="context"]').exists()).toBe(false);
      await wrapper.get('[data-testid="toggle-inspector"]').trigger('click');
      await flushPromises();
      const dialog = wrapper.findAllComponents({ name: 'ElDialog' }).find(item => item.props('title') === '任务与权限')!;
      expect(dialog.props('alignCenter')).toBe(true);
      expect(dialog.props('destroyOnClose')).toBe(false);
      expect(dialog.props('width')).toBe('min(760px, 94vw)');
      const panel = wrapper.getComponent({ name: 'AiContextPanel' });
      const instance = panel.vm;
      expect(panel.text()).toContain('#7 代码变更');
      const card = wrapper.get('.task-permissions-scroll .approval-card');
      await card.get('textarea').setValue('保留审批反馈');
      expect(wrapper.findAll('.task-permissions-scroll .approval-card')).toHaveLength(1);
      const generation = aiStore.selectionGeneration;
      vi.clearAllMocks();
      await dialog.get('.el-dialog__headerbtn').trigger('click');
      await vi.waitFor(() => expect(dialog.props('modelValue')).toBe(false));
      await flushPromises();
      expect(dialog.props('modelValue')).toBe(false);
      expect(wrapper.getComponent({ name: 'AiContextPanel' }).vm).toBe(instance);
      expect(aiStore.selectionGeneration).toBe(generation);
      expect(aiStore.activeTask.status).toBe('paused');
      expect(aiStore.conversations[0].approval_mode).toBe('request_approval');
      for (const action of [aiStore.closeEvents, aiStore.connectEvents, aiStore.refreshTaskContext, aiStore.cancelActiveTask, aiStore.decideApproval, aiStore.activateTask, aiDevelopmentApi.updateConversation, aiDevelopmentApi.executeTask]) expect(action).not.toHaveBeenCalled();
      aiStore.approvals.push({ ...approval, id: 14 });
      await nextTick();
      expect(wrapper.get('[data-testid="pending-approvals-badge"]').text()).toContain('2');
      await wrapper.get('[data-testid="toggle-inspector"]').trigger('click');
      await flushPromises();
      expect(wrapper.getComponent({ name: 'AiContextPanel' }).vm).toBe(instance);
      expect((card.get('textarea').element as HTMLTextAreaElement).value).toBe('保留审批反馈');
      await card.findAll('button').find(button => button.text() === '拒绝')!.trigger('click');
      expect(aiStore.decideApproval).toHaveBeenCalledWith(approval, 'reject', 'once', '保留审批反馈');
      aiStore.approvals = [];
      await nextTick();
      await vi.waitFor(() => {
        const badge = wrapper.find('[data-testid="pending-approvals-badge"] .el-badge__content');
        expect(badge.exists() && badge.isVisible()).toBe(false);
      });
    } finally { wrapper.unmount(); }
  });

  it('真实 Element Plus 模型弹层统一控件并保留 nullable 思考事件', async () => {
    setMobile(false);
    aiStore.conversations = [{ id: 1, model: 'm', profile_id: 7 }];
    aiStore.selectedConversationId = 1;
    vi.mocked(aiDevelopmentApi.profiles).mockResolvedValueOnce([{ id: 7, name: '档案', enabled: true, model: 'm', favorite_models: ['m'], reasoning_effort: null, model_capabilities: [{ model: 'm', reasoning_efforts: ['high'], output_token_parameter: 'max_tokens', context_window: null, max_output_tokens: null }] }] as never);
    const wrapper = mount(AiDevelopment, { attachTo: document.body, global: {
      plugins: [ElementPlus, createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } })],
      stubs: { ...stubs, ElSelect: false, ElOption: false, ElInput: false, ElButton: false, ElTooltip: false, ElPopover: false, ProviderSettingsDrawer: true }
    } });
    try {
      await flushPromises();
      const composer = wrapper.get('.ai-composer');
      expect(composer.find('details').exists()).toBe(false);
      expect(composer.findComponent(ElInput).exists()).toBe(true);
      expect(composer.get('button[aria-label="' + zhCN.aiComposer.attach + '"]').find('svg').exists()).toBe(true);
      expect(document.querySelector('[data-testid="model-form"]')).toBeNull();
      await composer.get('[data-testid="model-menu-trigger"]').trigger('click');
      await flushPromises();
      await vi.waitFor(() => expect(document.querySelector('[data-testid="model-form"]')).not.toBeNull());
      const form = document.querySelector('[data-testid="model-form"]')!;
      expect(form.querySelector('select, option, button:not(.el-button)')).toBeNull();
      const reasoning = wrapper.findAllComponents(ElSelect).find(c => c.attributes('data-testid') === 'conversation-reasoning')!;
      expect(reasoning.findAllComponents(ElOption).map(c => c.props('value'))).toEqual(['', 'high']);
      await reasoning.get('[role="combobox"]').trigger('click');
      await flushPromises();
      const high = [...document.querySelectorAll<HTMLElement>('.el-select-dropdown__item')].find(el => el.textContent === 'high')!;
      high.click();
      await flushPromises();
      expect(aiStore.updateConversationReasoning).toHaveBeenLastCalledWith(1, 'high');
      aiStore.conversations[0].reasoning_effort = 'high';
      await nextTick();
      await reasoning.get('[role="combobox"]').trigger('click');
      await flushPromises();
      [...document.querySelectorAll<HTMLElement>('.el-select-dropdown__item')].find(el => el.textContent?.includes('继承档案'))!.click();
      await flushPromises();
      expect(aiStore.updateConversationReasoning).toHaveBeenLastCalledWith(1, null);
      aiStore.conversations[0].reasoning_effort = null;
      await nextTick();
      expect(wrapper.findAllComponents(ElButton).length).toBeGreaterThan(0);
      const modelInput = wrapper.findAllComponents(ElInput).find(c => c.find('input#ai-model-id').exists())!;
      await modelInput.get('input').setValue(' next-model ');
      let finish!: () => void;
      aiStore.updateConversationModel.mockImplementationOnce(() => new Promise<void>(resolve => { finish = resolve; }));
      form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      await nextTick();
      expect(aiStore.updateConversationModel).toHaveBeenLastCalledWith(1, 'next-model');
      expect(reasoning.props('disabled')).toBe(true);
      expect(modelInput.get('input').attributes('disabled')).toBeDefined();
      form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      expect(aiStore.updateConversationModel).toHaveBeenCalledTimes(1);
      finish();
      await flushPromises();
      modelInput.get('input').element.focus();
      await modelInput.get('input').trigger('keydown', { key: 'Escape', code: 'Escape' });
      await vi.waitFor(() => expect(document.querySelector('[data-testid="model-form"]')).toBeNull());
      expect(document.activeElement).toBe(composer.get('[data-testid="model-menu-trigger"]').element);
      await composer.get('[data-testid="model-menu-trigger"]').trigger('keydown', { code: 'Enter', key: 'Enter' });
      await vi.waitFor(() => expect(document.querySelector('[data-testid="model-form"]')).not.toBeNull());
      document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
      document.body.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
      document.body.click();
      await vi.waitFor(() => expect(document.querySelector('[data-testid="model-form"]')).toBeNull());
    } finally { wrapper.unmount(); }
  });

  it.each([false, true])('Provider 位于会话栏新建工具区并保持打开行为，移动端=%s', async (mobile) => {
    const { wrapper } = await mountPage('zh-CN', mobile);
    await flushPromises();
    if (mobile) await wrapper.get('[data-testid="mobile-conversations"]').trigger('click');
    const actions = wrapper.get('.conversation-list__header > div');
    expect(actions.findAll('button').map((button) => button.text())).toEqual(['新建分组', '新建', 'Provider']);
    const provider = actions.findAll('button').find((button) => button.text() === 'Provider')!;
    expect(provider.attributes('disabled')).toBeUndefined();
    expect(wrapper.findComponent(ProviderSettingsDrawer).props('modelValue')).toBe(false);
    vi.mocked(aiDevelopmentApi.profiles).mockClear();
    await provider.trigger('click');
    await flushPromises();
    expect(aiDevelopmentApi.profiles).toHaveBeenCalledTimes(1);
    expect(aiDevelopmentApi.settings).not.toHaveBeenCalled();
    expect(wrapper.findComponent(ProviderSettingsDrawer).props('modelValue')).toBe(true);
    wrapper.unmount();
  });

  it('会话栏标题和工具区允许换行，按钮间距不叠加', () => {
    const source = readFileSync(resolve(process.cwd(), 'src/views/development/ai/components/ConversationList.vue'), 'utf8');
    expect(source).toMatch(/\.conversation-list__header\s*\{[^}]*flex-wrap:\s*wrap/s);
    expect(source).toMatch(/\.conversation-list__header > div\s*\{[^}]*flex-wrap:\s*wrap/s);
    expect(source).toMatch(/\.conversation-list__header :deep\(\.el-button\)\s*\{[^}]*margin-left:\s*0/s);
  });

  it('保存档案途中关闭再打开，不把旧响应切回当前编辑档案', async () => {
    const { wrapper } = await mountPage('zh-CN');
    await wrapper.findAll('button').find((button) => button.text().includes('Provider'))!.trigger('click');
    await flushPromises();
    const drawer = wrapper.findComponent(ProviderSettingsDrawer);
    let resolveSave!: (value: unknown) => void;
    vi.mocked(aiDevelopmentApi.createProfile).mockReturnValue(new Promise((resolve) => { resolveSave = resolve; }) as never);
    drawer.vm.$emit('save', { name: '旧草稿' }, null);
    await nextTick();
    drawer.vm.$emit('update:modelValue', false);
    await nextTick();
    drawer.vm.$emit('update:modelValue', true);
    await nextTick();
    resolveSave({ id: 9, name: '旧草稿' });
    await flushPromises();
    expect(drawer.props('savedProfile')).toBeNull();
  });

  it('新会话显式继承服务端默认档案，不复制配置或密钥到请求', async () => {
    vi.mocked(aiDevelopmentApi.defaultProfile).mockResolvedValueOnce({ id: 7 } as never);
    vi.mocked(aiDevelopmentApi.createConversation).mockResolvedValueOnce({ id: 8 } as never);
    const { wrapper } = await mountPage('zh-CN');
    wrapper.findComponent(ConversationList).vm.$emit('create');
    await flushPromises();
    expect(aiDevelopmentApi.createConversation).toHaveBeenCalledWith({ title: '新 AI 会话', approval_mode: 'request_approval', profile_id: 7 });
  });

  it('显示档案选择与默认继承入口，档案设置不再读取临时全局设置', async () => {
    const { wrapper } = await mountPage('zh-CN');
    expect(wrapper.find('[data-testid="conversation-profile"]').exists()).toBe(true);
    await wrapper.findAll('button').find((button) => button.text().includes('Provider'))!.trigger('click');
    await flushPromises();
    expect(aiDevelopmentApi.profiles).toHaveBeenCalled();
    expect(aiDevelopmentApi.settings).not.toHaveBeenCalled();
    expect(wrapper.find('[data-testid="profile-save"]').exists()).toBe(true);
  });

  it('composer 六档按声明展示，英文继承与默认不混淆', async () => {
    const efforts = ['low', 'medium', 'high', 'xhigh', 'max', 'ultra'];
    aiStore.conversations = [{ id: 1, model: 'm', profile_id: 7, reasoning_effort: null }];
    aiStore.selectedConversationId = 1;
    vi.mocked(aiDevelopmentApi.profiles).mockResolvedValueOnce([{ id: 7, name: '六档', enabled: true, model: 'm', reasoning_effort: null, model_capabilities: [{ model: 'm', reasoning_efforts: efforts, output_token_parameter: 'max_tokens', context_window: null, max_output_tokens: null }] }] as never);
    setMobile(false);
    const wrapper = mount(AiDevelopment, { global: { plugins: [createI18n({ legacy: false, locale: 'en-US', messages: { 'zh-CN': zhCN, 'en-US': enUS } })], stubs, renderStubDefaultSlot: true } });
    await wrapper.get('[data-testid="model-menu-trigger"]').trigger('click');
    await flushPromises();
    const select = wrapper.findAllComponents({ name: 'ElSelect' }).find(c => c.attributes('data-testid') === 'conversation-reasoning')!;
    expect(select.findAllComponents({ name: 'ElOption' }).map(o => o.attributes('value'))).toEqual(['', ...efforts]);
    expect(wrapper.get('[data-testid="inherited-reasoning"]').text()).toContain('Inherit profile');
    expect(wrapper.get('[data-testid="inherited-reasoning"]').text()).toContain('Default');
    for (const effort of ['xhigh', 'max', 'ultra']) {
      select.vm.$emit('change', effort);
      await flushPromises();
      expect(aiStore.updateConversationReasoning).toHaveBeenLastCalledWith(1, effort);
    }
    select.vm.$emit('change', '');
    await flushPromises();
    expect(aiStore.updateConversationReasoning).toHaveBeenLastCalledWith(1, null);
  });

  it('思考模式继承档案，展示默认模型和常用模型，阻止不兼容模型切换', async () => {
    aiStore.conversations = [{ id: 1, model: 'm', profile_id: 7 }];
    aiStore.selectedConversationId = 1;
    vi.mocked(aiDevelopmentApi.profiles).mockResolvedValueOnce([{ id: 7, name: '能力档案', enabled: true, is_default: true, model: 'm', favorite_models: ['m', 'b'], reasoning_effort: 'high', model_capabilities: [{ model: 'm', reasoning_efforts: ['high'], output_token_parameter: 'max_tokens', context_window: 1000, max_output_tokens: 200 }], max_output_tokens: 100 }] as never);
    const { wrapper } = await mountPage('zh-CN');
    await flushPromises();
    expect(wrapper.find('[data-testid="inherited-reasoning"]').text()).toContain('high');
    expect(wrapper.find('[data-testid="inherited-reasoning"]').text()).toContain('继承档案');
    const reasoning = wrapper.findAllComponents({ name: 'ElSelect' }).find(c => c.attributes('data-testid') === 'conversation-reasoning')!;
    reasoning.vm.$emit('change', 'high');
    await nextTick();
    expect(aiStore.updateConversationReasoning).toHaveBeenLastCalledWith(1, 'high');
    await flushPromises();
    aiStore.conversations[0].reasoning_effort = 'high';
    await nextTick();
    reasoning.vm.$emit('change', '');
    await nextTick();
    expect(aiStore.updateConversationReasoning).toHaveBeenLastCalledWith(1, null);
    await flushPromises();
    expect(wrapper.find('[data-testid="profile-model-summary"]').text()).toContain('m');
    expect(wrapper.find('[data-testid="favorite-models"]').text()).toContain('b');
    await wrapper.get('[data-testid="model-id"]').setValue('unknown');
    await wrapper.get('[data-testid="model-form"]').trigger('submit');
    expect(aiStore.updateConversationModel).not.toHaveBeenCalled();
    expect(wrapper.find('[data-testid="model-error"]').text()).toContain('推理档位');
    expect(pageSource).not.toContain('!profile.enabled || profile.fallback_enabled');
  });

  it('工作区显示模型 ID 输入、当前模型和真实边界，空值及无会话禁用', async () => {
    const { wrapper, locale } = await mountPage('zh-CN');
    expect(wrapper.find('[data-testid="save-model"]').exists()).toBe(true);
    expect(wrapper.get('[data-testid="save-model"]').attributes('disabled')).toBeDefined();
    aiStore.conversations = [{ id: 1, model: 'old-model' }];
    aiStore.selectedConversationId = 1;
    await nextTick();
    await wrapper.get('[data-testid="model-menu-trigger"]').trigger('click');
    expect(wrapper.get('[data-testid="current-model"]').text()).toContain('old-model');
    expect(wrapper.text()).toContain('仅支持当前配置的供应商');
    expect(wrapper.text()).toContain('仅影响保存后创建的任务');
    await wrapper.get('[data-testid="model-id"]').setValue('   ');
    expect(wrapper.get('[data-testid="save-model"]').attributes('disabled')).toBeDefined();
    await wrapper.get('[data-testid="model-form"]').trigger('submit');
    expect(aiStore.updateConversationModel).not.toHaveBeenCalled();
    locale.value = 'en-US';
    await nextTick();
    expect(wrapper.get('[data-testid="save-model"]').text()).toBe('Save model');
    expect(wrapper.text()).toContain('Model ID');
    wrapper.unmount();
  });

  it('运行任务时可以保存，保存中禁用且不重建任务或 SSE', async () => {
    aiStore.conversations = [{ id: 1, model: 'old' }];
    aiStore.selectedConversationId = 1;
    aiStore.activeTask = { id: 8, model: 'frozen', status: 'running' };
    const { wrapper } = await mountPage('zh-CN');
    await flushPromises();
    aiStore.connectEvents.mockClear();
    let finish!: () => void;
    aiStore.updateConversationModel.mockImplementationOnce(() => new Promise<void>((resolve) => { finish = resolve; }));
    await wrapper.get('[data-testid="model-id"]').setValue('  new  ');
    await wrapper.get('[data-testid="model-form"]').trigger('submit');
    expect(aiStore.updateConversationModel).toHaveBeenCalledWith(1, 'new');
    expect(wrapper.get('[data-testid="save-model"]').attributes('disabled')).toBeDefined();
    await wrapper.get('[data-testid="model-form"]').trigger('submit');
    expect(aiStore.updateConversationModel).toHaveBeenCalledTimes(1);
    aiStore.conversations[0].model = 'new';
    finish();
    await flushPromises();
    expect(wrapper.get('[data-testid="current-model"]').text()).toContain('new');
    expect(aiStore.activeTask.model).toBe('frozen');
    expect(aiStore.activateTask).not.toHaveBeenCalled();
    expect(aiStore.closeEvents).not.toHaveBeenCalled();
    expect(aiStore.connectEvents).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('思考保存期间禁用模型与档案入口，切走后忽略旧错误且不重建任务 SSE', async () => {
    aiStore.conversations = [{ id: 1, model: 'm', profile_id: 7 }, { id: 2, model: 'm', profile_id: 7 }];
    aiStore.selectedConversationId = 1;
    aiStore.activeTask = { id: 8, status: 'running', model: 'frozen' };
    vi.mocked(aiDevelopmentApi.profiles).mockResolvedValueOnce([{ id: 7, name: '档案', enabled: true, model: 'm', reasoning_effort: null, model_capabilities: [{ model: 'm', reasoning_efforts: ['low'], output_token_parameter: 'max_tokens', context_window: 1000, max_output_tokens: 200 }] }] as never);
    const { wrapper } = await mountPage('zh-CN');
    await flushPromises();
    aiStore.connectEvents.mockClear();
    let reject!: (error: Error) => void;
    aiStore.updateConversationReasoning.mockImplementationOnce(() => new Promise((_, fail) => { reject = fail; }));
    wrapper.findAllComponents({ name: 'ElSelect' }).find(c => c.attributes('data-testid') === 'conversation-reasoning')!.vm.$emit('change', 'low');
    await nextTick();
    expect(wrapper.get('[data-testid="conversation-reasoning"]').attributes('disabled')).toBeDefined();
    expect(wrapper.get('[data-testid="save-model"]').attributes('disabled')).toBeDefined();
    await wrapper.get('[data-testid="model-form"]').trigger('submit');
    expect(aiStore.updateConversationModel).not.toHaveBeenCalled();
    aiStore.selectedConversationId = 2;
    aiStore.selectionGeneration++;
    await nextTick();
    reject(new Error('旧思考保存失败'));
    await flushPromises();
    await wrapper.get('[data-testid="model-menu-trigger"]').trigger('click');
    expect(wrapper.find('[data-testid="model-error"]').exists()).toBe(false);
    expect(wrapper.get('[data-testid="conversation-reasoning"]').attributes('disabled')).toBe('false');
    expect(wrapper.get('[data-testid="conversation-reasoning"]').attributes('model-value')).toBe('');
    expect(aiStore.activeTask.model).toBe('frozen');
    expect(aiStore.activateTask).not.toHaveBeenCalled();
    expect(aiStore.closeEvents).not.toHaveBeenCalled();
    expect(aiStore.connectEvents).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('保存失败显示错误并保留输入，可重试', async () => {
    aiStore.conversations = [{ id: 1, model: 'old' }];
    aiStore.selectedConversationId = 1;
    const { wrapper } = await mountPage('zh-CN');
    aiStore.updateConversationModel.mockRejectedValueOnce(new Error('不支持该模型'));
    await wrapper.get('[data-testid="model-id"]').setValue('new');
    await wrapper.get('[data-testid="model-form"]').trigger('submit');
    await flushPromises();
    expect(wrapper.get('[data-testid="model-error"]').text()).toContain('模型保存失败');
    expect(wrapper.get('[data-testid="model-error"]').text()).toContain('不支持该模型');
    expect((wrapper.get('[data-testid="model-id"]').element as HTMLInputElement).value).toBe('new');
    expect(wrapper.get('[data-testid="current-model"]').text()).toContain('old');
    expect(wrapper.get('[data-testid="save-model"]').attributes('disabled')).toBeUndefined();
    wrapper.unmount();
  });

  it.each([false, true])('切走会话隔离旧保存结果，失败=%s', async (failed) => {
    aiStore.conversations = [{ id: 1, model: 'old' }, { id: 2, model: 'second' }];
    aiStore.selectedConversationId = 1;
    const { wrapper } = await mountPage('zh-CN');
    let finish!: () => void;
    aiStore.updateConversationModel.mockImplementationOnce(() => new Promise<void>((resolve, reject) => {
      finish = () => failed ? reject(new Error('旧会话错误')) : resolve();
    }));
    await wrapper.get('[data-testid="model-id"]').setValue('new');
    await wrapper.get('[data-testid="model-form"]').trigger('submit');
    aiStore.selectedConversationId = 2;
    aiStore.selectionGeneration += 1;
    await nextTick();
    finish();
    await flushPromises();
    await wrapper.get('[data-testid="model-menu-trigger"]').trigger('click');
    expect((wrapper.get('[data-testid="model-id"]').element as HTMLInputElement).value).toBe('second');
    expect(wrapper.get('[data-testid="current-model"]').text()).toContain('second');
    expect(wrapper.find('[data-testid="model-error"]').exists()).toBe(false);
    expect(wrapper.get('[data-testid="save-model"]').attributes('disabled')).toBeUndefined();
    wrapper.unmount();
  });

  it('显示空组、每会话日期，并通过已归档入口切换', async () => {
    aiStore.conversationGroups = [{ id: 10, name: '空项目' }];
    aiStore.conversations = [
      { id: 1, title: '正常会话', group_id: null, status: 'running', is_archived: false, is_unread: true, updated_at: '2026-09-12 10:30:00' },
      { id: 2, title: '归档会话', group_id: null, status: 'running', is_archived: true, is_unread: false }
    ];
    const { wrapper } = await mountPage('zh-CN');
    expect(wrapper.text()).toContain('空项目');
    expect(wrapper.text()).toContain('09-12 10:30');
    expect(wrapper.text()).not.toContain('归档会话');
    await wrapper.find('[data-testid="archived-conversations"]').trigger('click');
    expect(wrapper.text()).toContain('归档会话');
    expect(wrapper.text()).not.toContain('正常会话');
    wrapper.unmount();
  });

  it('分组使用 Element Plus 弹窗，取消不请求，确认后保留空组', async () => {
    const dialog = vi.spyOn(ElMessageBox, 'prompt').mockRejectedValueOnce('cancel');
    const { wrapper } = await mountPage('zh-CN');
    const list = wrapper.findComponent(ConversationList);
    list.vm.$emit('create-group');
    await flushPromises();
    expect(aiDevelopmentApi.createConversationGroup).not.toHaveBeenCalled();
    dialog.mockResolvedValueOnce({ value: ' 新项目 ' } as never);
    vi.mocked(aiDevelopmentApi.createConversationGroup).mockResolvedValueOnce({ id: 10, name: '新项目' });
    list.vm.$emit('create-group');
    await flushPromises();
    expect(aiDevelopmentApi.createConversationGroup).toHaveBeenCalledWith('新项目');
    expect(wrapper.text()).toContain('新项目');
    dialog.mockRestore();
    wrapper.unmount();
  });

  it('会话菜单接入改名、归档、恢复、删除和手动未读', async () => {
    aiStore.conversations = [{ id: 1, title: '旧标题', group_id: null, is_archived: false, is_unread: false }];
    const { wrapper } = await mountPage('zh-CN');
    const list = wrapper.findComponent(ConversationList);
    const dialog = vi.spyOn(ElMessageBox, 'prompt').mockResolvedValue({ value: '新标题' } as never);
    vi.mocked(aiDevelopmentApi.updateConversation).mockResolvedValueOnce({ id: 1, title: '新标题' } as never);
    list.vm.$emit('action', 'rename', 1);
    await flushPromises();
    expect(aiDevelopmentApi.updateConversation).toHaveBeenCalledWith(1, { title: '新标题' });
    list.vm.$emit('action', 'unread', 1);
    await flushPromises();
    expect(aiStore.updateConversationState).toHaveBeenCalledWith(1, { is_unread: true });
    list.vm.$emit('action', 'archive', 1);
    await flushPromises();
    expect(aiStore.updateConversationState).toHaveBeenCalledWith(1, { is_archived: true });
    list.vm.$emit('action', 'restore', 1);
    await flushPromises();
    expect(aiStore.updateConversationState).toHaveBeenCalledWith(1, { is_archived: false });
    const confirm = vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue({ action: 'confirm' } as never);
    list.vm.$emit('action', 'delete', 1);
    await flushPromises();
    expect(aiStore.deleteConversation).toHaveBeenCalledWith(1);
    confirm.mockRestore();
    dialog.mockRestore();
    wrapper.unmount();
  });

  it('移动组只提交 group_id，未分组提交 null；失败保留弹窗', async () => {
    aiStore.conversations = [{ id: 1, title: '会话', group_id: 10 }];
    aiStore.conversationGroups = [{ id: 10, name: '项目' }];
    const { wrapper } = await mountPage('zh-CN');
    wrapper.findComponent(ConversationList).vm.$emit('action', 'move', 1);
    await flushPromises();
    const select = wrapper.findAllComponents({ name: 'ElSelect' }).find((item) => item.attributes('aria-label') === '分组名称')!;
    select.vm.$emit('update:modelValue', 0);
    await nextTick();
    const confirm = wrapper.findAll('button').find((button) => button.text() === '确定')!;
    aiStore.updateConversationState.mockRejectedValueOnce(new Error('失败'));
    await confirm.trigger('click');
    await flushPromises();
    expect(aiStore.updateConversationState).toHaveBeenCalledWith(1, { group_id: null });
    expect(wrapper.findComponent({ name: 'ElSelect' }).exists()).toBe(true);
    select.vm.$emit('update:modelValue', 10);
    await confirm.trigger('click');
    await flushPromises();
    expect(aiStore.updateConversationState).toHaveBeenLastCalledWith(1, { group_id: 10 });
    wrapper.unmount();
  });

  it('审批升级取消时不更新真实会话', async () => {
    aiStore.conversations = [{ id: 1, approval_mode: 'request_approval' }]; aiStore.selectedConversationId = 1;
    const { wrapper } = await mountPage('zh-CN'); await flushPromises();
    vi.spyOn(ElMessageBox, 'confirm').mockRejectedValueOnce('cancel');
    await wrapper.findAll('.toolbar button').find(button => button.text() === zhCN.aiComposer.approval)!.trigger('click');
    wrapper.findComponent({ name: 'ApprovalModeSelector' }).vm.$emit('update:modelValue', 'full_access');
    await flushPromises(); expect(aiDevelopmentApi.updateConversation).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('发送中切换会话不把旧消息或任务写入新工作区', async () => {
    aiStore.conversations = [{ id: 1, title: '旧会话' }, { id: 2, title: '新会话' }];
    aiStore.selectedConversationId = 1;
    let finish!: (value: unknown) => void;
    vi.mocked(aiDevelopmentApi.createMessage).mockImplementationOnce(() => new Promise((resolve) => { finish = resolve as never; }));
    const { wrapper } = await mountPage('zh-CN');
    await wrapper.find('.ai-composer textarea').setValue('旧请求');
    await wrapper.find('.ai-composer textarea').trigger('keydown', { key: 'Enter' });
    aiStore.selectedConversationId = 2;
    aiStore.selectionGeneration += 1;
    finish({ id: 7, conversation_id: 1 });
    await flushPromises();
    expect(aiStore.messages).toEqual([]);
    expect(aiDevelopmentApi.executeTask).not.toHaveBeenCalled();
    expect(aiStore.activateTask).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('切换语言会更新页面、Tabs、按钮与真实空状态文案', async () => {
    const { wrapper, locale } = await mountPage('zh-CN', true);
    expect(wrapper.text()).not.toContain('AI 开发助手');
    expect(wrapper.text()).not.toContain('会话、工具审批与工作区变更');
    expect(wrapper.find('[data-testid="mobile-conversations"]').text()).toBe('会话');
    expect(wrapper.find('[data-testid="mobile-context"]').text()).toBe('任务与权限');
    expect(wrapper.text()).toContain('发送');
    expect(wrapper.text()).toContain('开始一个新的 AI 会话');

    locale.value = 'en-US';
    await nextTick();

    expect(wrapper.text()).not.toContain('AI Development Assistant');
    expect(wrapper.text()).not.toContain('Conversations, tool approvals, and workspace changes');
    expect(wrapper.find('[data-testid="mobile-conversations"]').text()).toBe('Conversations');
    expect(wrapper.find('[data-testid="mobile-context"]').text()).toBe('Task and permissions');
    expect(wrapper.text()).toContain('Send');
    expect(wrapper.text()).toContain('Start a new AI conversation');
    expect(wrapper.text()).not.toContain('开始一个新的 AI 会话');
  });

  it('移动端顶部入口位于内容区域之前且切换时只显示对应真实区域', async () => {
    const { wrapper } = await mountPage('zh-CN', true);
    const actions = wrapper.find('.mobile-actions').element;
    const layout = wrapper.find('.ai-layout').element;
    expect(actions.compareDocumentPosition(layout) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(visibleRegions(wrapper)).toEqual(['workspace']);

    const workspaceButton = wrapper.find('[data-testid="mobile-workspace"]');
    const conversationsButton = wrapper.find('[data-testid="mobile-conversations"]');
    const contextButton = wrapper.find('[data-testid="mobile-context"]');
    expect(workspaceButton.attributes('aria-pressed')).toBe('true');
    expect(conversationsButton.attributes('aria-pressed')).toBe('false');
    expect(contextButton.attributes('aria-expanded')).toBe('false');

    await conversationsButton.trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations']);
    expect(wrapper.find('[data-ai-region="conversations"]').text()).toContain('新建');
    expect(workspaceButton.attributes('aria-pressed')).toBe('false');
    expect(conversationsButton.attributes('aria-pressed')).toBe('true');

    await contextButton.trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations']);
    expect(wrapper.find('.task-permissions-scroll').text()).toContain('暂无活动任务');
    expect(contextButton.attributes('aria-expanded')).toBe('true');
  });

  it('reviewing、任务阶段类型与 ChangeSet 状态均翻译，未知枚举原样回退', async () => {
    aiStore.conversations = [{ id: 1, title: '测试会话', status: 'reviewing', group_id: null, is_archived: false, is_unread: false }];
    aiStore.selectedConversationId = 1;
    aiStore.activeTask = { id: 7, type: 'code_change', stage: 'review', status: 'paused' };
    aiStore.changeSet = { id: 9, status: 'proposed' };
    const { wrapper } = await mountPage('zh-CN');
    expect(wrapper.text()).toContain('审核中');
    await wrapper.find('[data-testid="toggle-inspector"]').trigger('click');
    expect(wrapper.text()).toContain('#7 代码变更');
    expect(wrapper.text()).toContain('审核');
    expect(wrapper.text()).toContain('ChangeSet #9 · 待应用');
    expect(wrapper.text()).not.toContain('reviewing');
    expect(wrapper.text()).not.toContain('code_change');
    expect(wrapper.text()).not.toContain('proposed');
  });

  it('桌面端默认以会话栏和主工作区为两栏，检查器按需打开且无任务不永久占栏', async () => {
    const { wrapper } = await mountPage('zh-CN');
    expect(visibleRegions(wrapper)).toEqual(['conversations', 'workspace']);
    expect(wrapper.find('[data-ai-region="context"]').exists()).toBe(false);

    await wrapper.find('[data-testid="toggle-inspector"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations', 'workspace']);
    expect(wrapper.find('[data-testid="toggle-inspector"]').text()).toContain('任务与权限');
    expect(wrapper.find('.ai-layout--inspector').exists()).toBe(false);

    await wrapper.find('[data-testid="toggle-inspector"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations', 'workspace']);
    expect(keys(zhCN.aiDevelopment).sort()).toEqual(keys(enUS.aiDevelopment).sort());
  });

  it.each([
    ['消息列表', timelineSource, '.message-timeline'],
    ['输入区', readFileSync(resolve(process.cwd(), 'src/views/development/ai/components/AiComposer.vue'), 'utf8'), '.ai-composer']
  ])('%s 满宽且仅保留左右 16px 内距，所有断点均不恢复固定宽度或自动居中', (_name, source, selector) => {
    const css = source.slice(source.indexOf('<style scoped>') + '<style scoped>'.length);
    const rules = [...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)]
      .filter(([, selectors]) => selectors!.trim() === selector)
      .map(([, , declarations]) => declarations!);
    expect(rules.length).toBeGreaterThan(0);
    const base = rules[0]!;
    expect(base).toMatch(/(?:^|;)\s*width:\s*100%\s*;/);
    expect(base).toMatch(/box-sizing:\s*border-box\s*;/);
    expect(base).toMatch(/padding:\s*\d+px\s+16px\s*;/);
    for (const rule of rules) {
      expect(rule).not.toMatch(/max-width:\s*(?!none)[^;]+;/);
      expect(rule).not.toMatch(/margin(?:-inline)?\s*:[^;]*auto/);
      const width = rule.match(/(?:^|;)\s*width:\s*([^;]+);/);
      if (width) expect(width[1]!.trim()).toBe('100%');
      const padding = rule.match(/padding:\s*([^;]+);/);
      if (padding) expect(padding[1]).toMatch(/^\d+px\s+16px$/);
    }
  });

  it('消息气泡保留合理宽度和用户右对齐，代码内部滚动且主消息区独立滚动', async () => {
    const { wrapper } = await mountPage('zh-CN');
    expect(wrapper.find('.workspace-scroll').attributes('data-scroll-container')).toBe('primary');
    expect(timelineSource).toMatch(/\.message\s*\{[^}]*max-width:\s*86%/s);
    expect(timelineSource).toMatch(/\.message\s*\{[^}]*min-width:\s*0/s);
    expect(timelineSource).toMatch(/\.message\s*\{[^}]*box-sizing:\s*border-box/s);
    expect(timelineSource).toMatch(/\.message--user\s*\{[^}]*align-self:\s*flex-end/s);
    expect(timelineSource).toMatch(/\.message-text\s*\{[^}]*overflow-wrap:\s*anywhere/s);
    expect(timelineSource).toMatch(/\.code-block\s*\{[^}]*overflow:\s*auto[^}]*white-space:\s*pre/s);
    expect(pageSource).toMatch(/\.ai-page\s*\{[^}]*height:\s*100%[^}]*min-height:\s*0/s);
    expect(pageSource).toMatch(/\.workspace-scroll\s*\{[^}]*overflow:\s*auto/s);
    expect(pageSource).toMatch(/\.ai-conversations-pane[^}]*overflow:\s*auto/s);
    expect(pageSource).toMatch(/\.task-permissions-scroll\s*\{[^}]*max-height:[^}]*overflow:\s*auto/s);
  });

  it('移动端通过顶部按钮打开会话和任务，选择会话后回到工作区', async () => {
    aiStore.conversations = [{ id: 1, title: '移动会话', status: 'running', group_id: null, is_archived: false, is_unread: false }];
    const { wrapper } = await mountPage('zh-CN', true);
    expect(visibleRegions(wrapper)).toEqual(['workspace']);

    await wrapper.find('[data-testid="mobile-conversations"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations']);
    await wrapper.find('[data-ai-region="conversations"] .conversation-item').trigger('click');
    expect(aiStore.selectConversation).toHaveBeenCalled();
    expect(visibleRegions(wrapper)).toEqual(['workspace']);

    await wrapper.find('[data-testid="mobile-context"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['workspace']);
    expect(wrapper.find('.task-permissions-scroll').exists()).toBe(true);
    await wrapper.find('[data-testid="mobile-workspace"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['workspace']);
  });
});
