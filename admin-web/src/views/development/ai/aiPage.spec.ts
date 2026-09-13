import { computed, defineComponent, inject, nextTick, provide, reactive, ref, type InjectionKey, type Ref } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { ElMessageBox } from 'element-plus';
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
  ElForm: passthrough,
  ElFormItem: passthrough,
  ElTooltip: passthrough,
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

const mountPage = (locale: 'zh-CN' | 'en-US', mobile = false) => {
  setMobile(mobile);
  const i18n = createI18n({ legacy: false, locale, messages: { 'zh-CN': zhCN, 'en-US': enUS } });
  const wrapper = mount(AiDevelopment, { global: { plugins: [i18n], stubs } });
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

  it.each([false, true])('Provider 位于会话栏新建工具区并保持打开行为，移动端=%s', async (mobile) => {
    const { wrapper } = mountPage('zh-CN', mobile);
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
    const { wrapper } = mountPage('zh-CN');
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
    const { wrapper } = mountPage('zh-CN');
    wrapper.findComponent(ConversationList).vm.$emit('create');
    await flushPromises();
    expect(aiDevelopmentApi.createConversation).toHaveBeenCalledWith({ title: '新 AI 会话', approval_mode: 'request_approval', profile_id: 7 });
  });

  it('显示档案选择与默认继承入口，档案设置不再读取临时全局设置', async () => {
    const { wrapper } = mountPage('zh-CN');
    expect(wrapper.find('[data-testid="conversation-profile"]').exists()).toBe(true);
    await wrapper.findAll('button').find((button) => button.text().includes('Provider'))!.trigger('click');
    await flushPromises();
    expect(aiDevelopmentApi.profiles).toHaveBeenCalled();
    expect(aiDevelopmentApi.settings).not.toHaveBeenCalled();
    expect(wrapper.find('[data-testid="profile-save"]').exists()).toBe(true);
  });

  it('思考模式继承档案，展示默认模型和常用模型，阻止不兼容模型切换', async () => {
    aiStore.conversations = [{ id: 1, model: 'm', profile_id: 7 }];
    aiStore.selectedConversationId = 1;
    vi.mocked(aiDevelopmentApi.profiles).mockResolvedValueOnce([{ id: 7, name: '能力档案', enabled: true, is_default: true, model: 'm', favorite_models: ['m', 'b'], reasoning_effort: 'high', model_capabilities: [{ model: 'm', reasoning_efforts: ['high'], output_token_parameter: 'max_tokens', context_window: 1000, max_output_tokens: 200 }], max_output_tokens: 100 }] as never);
    const { wrapper } = mountPage('zh-CN');
    await flushPromises();
    expect(wrapper.find('[data-testid="inherited-reasoning"]').text()).toContain('high');
    expect(wrapper.find('[data-testid="inherited-reasoning"]').text()).toContain('继承档案');
    const reasoning = wrapper.get('[data-testid="conversation-reasoning"]');
    expect(reasoning.findAll('option').map(option => option.element.value)).toEqual(['', 'high']);
    await reasoning.setValue('high');
    expect(aiStore.updateConversationReasoning).toHaveBeenLastCalledWith(1, 'high');
    await flushPromises();
    aiStore.conversations[0].reasoning_effort = 'high';
    await nextTick();
    await reasoning.setValue('');
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
    const { wrapper, locale } = mountPage('zh-CN');
    expect(wrapper.find('[data-testid="save-model"]').exists()).toBe(true);
    expect(wrapper.get('[data-testid="save-model"]').attributes('disabled')).toBeDefined();
    aiStore.conversations = [{ id: 1, model: 'old-model' }];
    aiStore.selectedConversationId = 1;
    await nextTick();
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
    const { wrapper } = mountPage('zh-CN');
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
    const { wrapper } = mountPage('zh-CN');
    await flushPromises();
    aiStore.connectEvents.mockClear();
    let reject!: (error: Error) => void;
    aiStore.updateConversationReasoning.mockImplementationOnce(() => new Promise((_, fail) => { reject = fail; }));
    await wrapper.get('[data-testid="conversation-reasoning"]').setValue('low');
    expect(wrapper.get('[data-testid="conversation-reasoning"]').attributes('disabled')).toBeDefined();
    expect(wrapper.get('[data-testid="save-model"]').attributes('disabled')).toBeDefined();
    await wrapper.get('[data-testid="model-form"]').trigger('submit');
    expect(aiStore.updateConversationModel).not.toHaveBeenCalled();
    aiStore.selectedConversationId = 2;
    aiStore.selectionGeneration++;
    await nextTick();
    reject(new Error('旧思考保存失败'));
    await flushPromises();
    expect(wrapper.find('[data-testid="model-error"]').exists()).toBe(false);
    expect(wrapper.get('[data-testid="conversation-reasoning"]').attributes('disabled')).toBeUndefined();
    expect((wrapper.get('[data-testid="conversation-reasoning"]').element as HTMLSelectElement).value).toBe('');
    expect(aiStore.activeTask.model).toBe('frozen');
    expect(aiStore.activateTask).not.toHaveBeenCalled();
    expect(aiStore.closeEvents).not.toHaveBeenCalled();
    expect(aiStore.connectEvents).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('保存失败显示错误并保留输入，可重试', async () => {
    aiStore.conversations = [{ id: 1, model: 'old' }];
    aiStore.selectedConversationId = 1;
    const { wrapper } = mountPage('zh-CN');
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
    const { wrapper } = mountPage('zh-CN');
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
    const { wrapper } = mountPage('zh-CN');
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
    const { wrapper } = mountPage('zh-CN');
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
    const { wrapper } = mountPage('zh-CN');
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
    const { wrapper } = mountPage('zh-CN');
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
    const { wrapper } = mountPage('zh-CN'); await flushPromises();
    vi.spyOn(ElMessageBox, 'confirm').mockRejectedValueOnce('cancel');
    wrapper.findComponent({ name: 'ApprovalModeSelector' }).vm.$emit('update:modelValue', 'full_access');
    await flushPromises(); expect(aiDevelopmentApi.updateConversation).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('发送中切换会话不把旧消息或任务写入新工作区', async () => {
    aiStore.conversations = [{ id: 1, title: '旧会话' }, { id: 2, title: '新会话' }];
    aiStore.selectedConversationId = 1;
    let finish!: (value: unknown) => void;
    vi.mocked(aiDevelopmentApi.createMessage).mockImplementationOnce(() => new Promise((resolve) => { finish = resolve as never; }));
    const { wrapper } = mountPage('zh-CN');
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
    const { wrapper, locale } = mountPage('zh-CN', true);
    expect(wrapper.text()).not.toContain('AI 开发助手');
    expect(wrapper.text()).not.toContain('会话、工具审批与工作区变更');
    expect(wrapper.find('[data-testid="mobile-conversations"]').text()).toBe('会话');
    expect(wrapper.find('[data-testid="mobile-context"]').text()).toBe('任务');
    expect(wrapper.text()).toContain('发送');
    expect(wrapper.text()).toContain('开始一个新的 AI 会话');

    locale.value = 'en-US';
    await nextTick();

    expect(wrapper.text()).not.toContain('AI Development Assistant');
    expect(wrapper.text()).not.toContain('Conversations, tool approvals, and workspace changes');
    expect(wrapper.find('[data-testid="mobile-conversations"]').text()).toBe('Conversations');
    expect(wrapper.find('[data-testid="mobile-context"]').text()).toBe('Task');
    expect(wrapper.text()).toContain('Send');
    expect(wrapper.text()).toContain('Start a new AI conversation');
    expect(wrapper.text()).not.toContain('开始一个新的 AI 会话');
  });

  it('移动端顶部入口位于内容区域之前且切换时只显示对应真实区域', async () => {
    const { wrapper } = mountPage('zh-CN', true);
    const actions = wrapper.find('.mobile-actions').element;
    const layout = wrapper.find('.ai-layout').element;
    expect(actions.compareDocumentPosition(layout) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(visibleRegions(wrapper)).toEqual(['workspace']);

    const workspaceButton = wrapper.find('[data-testid="mobile-workspace"]');
    const conversationsButton = wrapper.find('[data-testid="mobile-conversations"]');
    const contextButton = wrapper.find('[data-testid="mobile-context"]');
    expect(workspaceButton.attributes('aria-pressed')).toBe('true');
    expect(conversationsButton.attributes('aria-pressed')).toBe('false');
    expect(contextButton.attributes('aria-pressed')).toBe('false');

    await conversationsButton.trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations']);
    expect(wrapper.find('[data-ai-region="conversations"]').text()).toContain('新建');
    expect(workspaceButton.attributes('aria-pressed')).toBe('false');
    expect(conversationsButton.attributes('aria-pressed')).toBe('true');

    await contextButton.trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['context']);
    expect(wrapper.find('[data-ai-region="context"]').text()).toContain('暂无活动任务');
    expect(contextButton.attributes('aria-pressed')).toBe('true');
  });

  it('reviewing、任务阶段类型与 ChangeSet 状态均翻译，未知枚举原样回退', async () => {
    aiStore.conversations = [{ id: 1, title: '测试会话', status: 'reviewing', group_id: null, is_archived: false, is_unread: false }];
    aiStore.selectedConversationId = 1;
    aiStore.activeTask = { id: 7, type: 'code_change', stage: 'review', status: 'paused' };
    aiStore.changeSet = { id: 9, status: 'proposed' };
    const { wrapper } = mountPage('zh-CN');
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
    const { wrapper } = mountPage('zh-CN');
    expect(visibleRegions(wrapper)).toEqual(['conversations', 'workspace']);
    expect(wrapper.find('[data-ai-region="context"]').exists()).toBe(false);

    await wrapper.find('[data-testid="toggle-inspector"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations', 'workspace', 'context']);
    expect(wrapper.find('[data-testid="toggle-inspector"]').text()).toContain('关闭');

    await wrapper.find('[data-testid="toggle-inspector"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations', 'workspace']);
    expect(keys(zhCN.aiDevelopment).sort()).toEqual(keys(enUS.aiDevelopment).sort());
  });

  it('工作区消息正文限制在舒适宽度，三栏各自处理溢出且主消息区保持独立滚动', () => {
    const { wrapper } = mountPage('zh-CN');
    expect(wrapper.find('.workspace-scroll').attributes('data-scroll-container')).toBe('primary');
    expect(timelineSource).toMatch(/\.message-timeline\s*\{[^}]*max-width:\s*\d+px/s);
    expect(pageSource).toMatch(/\.ai-page\s*\{[^}]*height:\s*100%[^}]*min-height:\s*0/s);
    expect(pageSource).toMatch(/\.workspace-scroll\s*\{[^}]*overflow:\s*auto/s);
    expect(pageSource).toMatch(/\.ai-conversations-pane[^}]*overflow:\s*auto/s);
    expect(pageSource).toMatch(/\.ai-context-pane[^}]*overflow:\s*auto/s);
  });

  it('移动端通过顶部按钮打开会话和任务，选择会话后回到工作区', async () => {
    aiStore.conversations = [{ id: 1, title: '移动会话', status: 'running', group_id: null, is_archived: false, is_unread: false }];
    const { wrapper } = mountPage('zh-CN', true);
    expect(visibleRegions(wrapper)).toEqual(['workspace']);

    await wrapper.find('[data-testid="mobile-conversations"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations']);
    await wrapper.find('[data-ai-region="conversations"] .conversation-item').trigger('click');
    expect(aiStore.selectConversation).toHaveBeenCalled();
    expect(visibleRegions(wrapper)).toEqual(['workspace']);

    await wrapper.find('[data-testid="mobile-context"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['context']);
    await wrapper.find('[data-testid="mobile-workspace"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['workspace']);
  });
});
