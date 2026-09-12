import { computed, defineComponent, inject, nextTick, provide, reactive, ref, type InjectionKey, type Ref } from 'vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import zhCN from '@/locales/zh-CN';
import enUS from '@/locales/en-US';

const aiStore = reactive({
  conversations: [] as Array<Record<string, unknown>>,
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
      conversations: vi.fn().mockResolvedValue([]),
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
  PageWrapper: passthrough,
  ElButton: buttonStub,
  ElInput: inputStub,
  ElEmpty: emptyStub,
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
    aiStore.selectedConversationId = null;
    aiStore.messages = [];
    aiStore.activeTask = null;
    aiStore.approvals = [];
    aiStore.toolCalls = [];
    aiStore.changeSet = null;
    vi.clearAllMocks();
  });

  it('切换语言会更新页面、Tabs、按钮与真实空状态文案', async () => {
    const { wrapper, locale } = mountPage('zh-CN', true);
    expect(wrapper.text()).toContain('AI 开发助手');
    expect(wrapper.text()).toContain('会话、工具审批与工作区变更');
    expect(wrapper.find('[data-tab="workspace"]').text()).toBe('工作区');
    expect(wrapper.text()).toContain('发送');
    expect(wrapper.text()).toContain('开始一个新的 AI 会话');

    locale.value = 'en-US';
    await nextTick();

    expect(wrapper.text()).toContain('AI Development Assistant');
    expect(wrapper.text()).toContain('Conversations, tool approvals, and workspace changes');
    expect(wrapper.find('[data-tab="workspace"]').text()).toBe('Workspace');
    expect(wrapper.text()).toContain('Send');
    expect(wrapper.text()).toContain('Start a new AI conversation');
    expect(wrapper.text()).not.toContain('开始一个新的 AI 会话');
  });

  it('移动端 Tabs 位于内容区域之前且切换时只显示对应真实区域', async () => {
    const { wrapper } = mountPage('zh-CN', true);
    const tabs = wrapper.find('[data-testid="mobile-tabs"]').element;
    const layout = wrapper.find('.ai-layout').element;
    expect(tabs.compareDocumentPosition(layout) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(visibleRegions(wrapper)).toEqual(['workspace']);

    await wrapper.find('[data-tab="conversations"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations']);
    expect(wrapper.find('[data-ai-region="conversations"]').text()).toContain('新建');

    await wrapper.find('[data-tab="context"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['context']);
    expect(wrapper.find('[data-ai-region="context"]').text()).toContain('暂无活动任务');
  });

  it('reviewing、任务阶段类型与 ChangeSet 状态均翻译，未知枚举原样回退', () => {
    aiStore.conversations = [{ id: 1, title: '测试会话', status: 'reviewing' }];
    aiStore.selectedConversationId = 1;
    aiStore.activeTask = { id: 7, type: 'code_change', stage: 'review', status: 'paused' };
    aiStore.changeSet = { id: 9, status: 'proposed' };
    const { wrapper } = mountPage('zh-CN');
    expect(wrapper.text()).toContain('审核中');
    expect(wrapper.text()).toContain('#7 代码变更');
    expect(wrapper.text()).toContain('审核');
    expect(wrapper.text()).toContain('ChangeSet #9 · 待应用');
    expect(wrapper.text()).not.toContain('reviewing');
    expect(wrapper.text()).not.toContain('code_change');
    expect(wrapper.text()).not.toContain('proposed');
  });

  it('桌面端保持会话、工作区、任务三栏同时显示，且中英文 key 完全对齐', () => {
    const { wrapper } = mountPage('zh-CN');
    expect(visibleRegions(wrapper)).toEqual(['conversations', 'workspace', 'context']);
    expect(keys(zhCN.aiDevelopment).sort()).toEqual(keys(enUS.aiDevelopment).sort());
  });
});
