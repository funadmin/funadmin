import { computed, defineComponent, inject, nextTick, provide, reactive, ref, type InjectionKey, type Ref } from 'vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
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
    expect(wrapper.find('[data-testid="mobile-conversations"]').text()).toBe('会话');
    expect(wrapper.find('[data-testid="mobile-context"]').text()).toBe('任务');
    expect(wrapper.text()).toContain('发送');
    expect(wrapper.text()).toContain('开始一个新的 AI 会话');

    locale.value = 'en-US';
    await nextTick();

    expect(wrapper.text()).toContain('AI Development Assistant');
    expect(wrapper.text()).toContain('Conversations, tool approvals, and workspace changes');
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

    await wrapper.find('[data-testid="mobile-conversations"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations']);
    expect(wrapper.find('[data-ai-region="conversations"]').text()).toContain('新建');

    await wrapper.find('[data-testid="mobile-context"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['context']);
    expect(wrapper.find('[data-ai-region="context"]').text()).toContain('暂无活动任务');
  });

  it('reviewing、任务阶段类型与 ChangeSet 状态均翻译，未知枚举原样回退', async () => {
    aiStore.conversations = [{ id: 1, title: '测试会话', status: 'reviewing' }];
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

  it('工作区消息正文限制在舒适宽度，壳层使用剩余高度且只有工作区消息区滚动', () => {
    const { wrapper } = mountPage('zh-CN');
    expect(wrapper.find('.workspace-scroll').attributes('data-scroll-container')).toBe('primary');
    expect(timelineSource).toMatch(/\.message-timeline\s*\{[^}]*max-width:\s*\d+px/s);
    expect(pageSource).toMatch(/\.ai-page\s*\{[^}]*height:\s*100%[^}]*min-height:\s*0/s);
    expect(pageSource).toMatch(/\.workspace-scroll\s*\{[^}]*overflow:\s*auto/s);
    expect(pageSource).not.toMatch(/\.ai-conversations-pane[^}]*overflow:\s*auto/s);
  });

  it('移动端通过顶部按钮打开会话和任务，选择会话后回到工作区', async () => {
    aiStore.conversations = [{ id: 1, title: '移动会话', status: 'running' }];
    const { wrapper } = mountPage('zh-CN', true);
    expect(visibleRegions(wrapper)).toEqual(['workspace']);

    await wrapper.find('[data-testid="mobile-conversations"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['conversations']);
    await wrapper.find('[data-ai-region="conversations"] .conversation-item').trigger('click');
    expect(aiStore.selectConversation).toHaveBeenCalled();
    expect(visibleRegions(wrapper)).toEqual(['workspace']);

    await wrapper.find('[data-testid="mobile-context"]').trigger('click');
    expect(visibleRegions(wrapper)).toEqual(['context']);
  });
});
