import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import zhCN from '@/locales/zh-CN';
import MessageTimeline from './MessageTimeline.vue';
import ConversationList from './ConversationList.vue';
import type { AiMessage, AiToolCall } from '@/api/development/ai';

vi.mock('@/utils/permission', () => ({ hasPermission: () => true }));
const i18n = () => createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
const call = (id: number, message_id: number | null, extra: Record<string, unknown> = {}) => ({
  id, conversation_id: 1, task_id: 1, message_id, approval_id: null, tool_name: `tool_${id}`, operation: 'read_workspace',
  risk_level: 'low', status: 'succeeded', approval_decision: 'not_required', redacted_arguments: {}, duration_ms: 40 + id, ...extra
}) as AiToolCall;
const msg = (id: number, role: string) => ({ id, conversation_id: 1, parent_id: null, sequence: id, role, content: [{ type: 'text', text: `正文 ${id}` }], metadata: {} }) as AiMessage;

describe('工作台改版：工具调用内嵌对话', () => {
  it('按 message_id 挂到对应消息内，未归属的调用排在末尾，一条不丢', () => {
    const wrapper = mount(MessageTimeline, { props: { messages: [msg(1, 'user'), msg(2, 'assistant'), msg(3, 'assistant')], toolCalls: [call(10, 2), call(11, 3), call(12, null), call(13, 99)] }, global: { plugins: [i18n()] } });
    const messages = wrapper.findAll('.message');
    expect(messages[1]!.findAll('[data-tool-call]').map(item => item.attributes('data-tool-call'))).toEqual(['10']);
    expect(messages[2]!.findAll('[data-tool-call]').map(item => item.attributes('data-tool-call'))).toEqual(['11']);
    expect(messages[0]!.findAll('[data-tool-call]')).toHaveLength(0);
    expect(wrapper.get('[data-testid="unassigned-tool-calls"]').findAll('[data-tool-call]').map(item => item.attributes('data-tool-call'))).toEqual(['12', '13']);
  });

  it('工具调用默认折叠，展开后显示输出摘要并可打开日志', async () => {
    const wrapper = mount(MessageTimeline, { props: { messages: [msg(2, 'assistant')], toolCalls: [call(10, 2, { stdout_summary: '找到 3 处' })] }, global: { plugins: [i18n()] } });
    const toggle = wrapper.get('[data-tool-call="10"] button');
    expect(toggle.attributes('aria-expanded')).toBe('false');
    expect(wrapper.text()).not.toContain('找到 3 处');
    await toggle.trigger('click');
    expect(toggle.attributes('aria-expanded')).toBe('true');
    expect(wrapper.text()).toContain('找到 3 处');
    await wrapper.findAll('[data-tool-call="10"] button').find(button => button.text().includes(zhCN.aiDevelopment.toolCalls.viewStdout))!.trigger('click');
    expect(wrapper.emitted('open-log')).toEqual([[10, 'stdout']]);
  });

  it('等待审批与失败的调用默认展开，避免被忽略', () => {
    const wrapper = mount(MessageTimeline, { props: { messages: [msg(2, 'assistant')], toolCalls: [call(10, 2, { status: 'awaiting_approval' }), call(11, 2, { status: 'failed', stderr_summary: '权限不足' })] }, global: { plugins: [i18n()] } });
    expect(wrapper.get('[data-tool-call="10"] button').attributes('aria-expanded')).toBe('true');
    expect(wrapper.get('[data-tool-call="11"] button').attributes('aria-expanded')).toBe('true');
    expect(wrapper.text()).toContain('权限不足');
  });
});

describe('工作台改版：会话栏头部收纳', () => {
  const mountList = (filters = {}) => mount(ConversationList, { props: { conversations: [], groups: [{ id: 3, name: '插件中心' }], selectedId: null, filters }, global: { plugins: [i18n()], stubs: { ElDropdown: { template: '<div><slot /><slot name="dropdown" /></div>' }, ElDropdownMenu: { template: '<div><slot /></div>' }, ElDropdownItem: { props: ['command'], template: '<button type="button" :data-command="command" @click="$parent.$parent.$emit(\'command\', command)"><slot /></button>' }, ElPopover: { template: '<div><slot name="reference" /><slot /></div>' } } } });

  it('头部只保留新建会话与更多菜单，筛选收进搜索框旁', () => {
    const wrapper = mountList();
    const headerButtons = wrapper.findAll('.conversation-list__header > .conversation-list__actions > button, .conversation-list__header > .conversation-list__actions > div > button');
    expect(wrapper.get('[data-testid="new-conversation"]').text()).toContain(zhCN.aiDevelopment.newConversation);
    expect(wrapper.find('[data-testid="conversation-more"]').exists()).toBe(true);
    expect(wrapper.find('.conversation-list__filters [data-testid="conversation-filter-trigger"]').exists()).toBe(true);
    expect(headerButtons.length).toBeLessThanOrEqual(2);
  });

  it('有筛选条件时筛选按钮标记为已启用', () => {
    expect(mountList({ is_unread: 1 }).get('[data-testid="conversation-filter-trigger"]').attributes('data-active')).toBe('true');
    expect(mountList({}).get('[data-testid="conversation-filter-trigger"]').attributes('data-active')).toBe('false');
  });

  it('更多菜单发出新建分组和切换归档事件', async () => {
    const wrapper = mountList();
    await wrapper.get('[data-command="create-group"]').trigger('click');
    await wrapper.get('[data-testid="archived-conversations"]').trigger('click');
    expect(wrapper.emitted('create-group')).toHaveLength(1);
    expect(wrapper.emitted('toggle-archived')).toHaveLength(1);
  });
});

import AiComposer from './AiComposer.vue';
describe('工作台改版：输入框只显示设置摘要', () => {
  const profile = { id: 7, name: '智谱 GLM', enabled: true, model: 'glm-4.6', reasoning_effort: 'medium' } as never;
  const mountComposer = (props: Record<string, unknown> = {}) => mount(AiComposer, { props: { conversationId: 1, model: 'glm-4.6', profile, reasoning: 'medium', approvalMode: 'request_approval', running: false, saving: false, ...props }, global: { plugins: [i18n()] } });

  it('摘要显示模型、档案、思考与审批模式，点击发出 open-settings 而不是展开弹层', async () => {
    const wrapper = mountComposer();
    const trigger = wrapper.get('[data-testid="model-menu-trigger"]');
    expect(trigger.text()).toContain('glm-4.6');
    expect(trigger.text()).toContain('智谱 GLM');
    expect(trigger.text()).toContain('medium');
    expect(wrapper.get('[data-testid="approval-mode-trigger"]').text()).toContain(zhCN.aiDevelopment.approvalModes.request);
    await trigger.trigger('click');
    await wrapper.get('[data-testid="approval-mode-trigger"]').trigger('click');
    expect(wrapper.emitted('open-settings')).toHaveLength(2);
    expect(wrapper.find('.el-popover').exists()).toBe(false);
  });
});

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
describe('工作台改版：图标都已生成', () => {
  it('改版用到的 i-ep 图标都存在于生成的样式中，避免出现空白按钮', () => {
    const css = readFileSync(resolve(process.cwd(), 'src/shared/styles/ep-icons.css'), 'utf8');
    const files = ['ConversationList.vue', 'InlineToolCall.vue', 'AiComposer.vue'].map(name => readFileSync(resolve(process.cwd(), 'src/admin/views/development/ai/components', name), 'utf8'));
    const used = new Set(files.join('\n').match(/i-ep-[a-z0-9-]+/g) || []);
    const missing = [...used].filter(icon => !new RegExp(`\\.${icon}[^a-z0-9-]`).test(css));
    expect(missing).toEqual([]);
  });
});
