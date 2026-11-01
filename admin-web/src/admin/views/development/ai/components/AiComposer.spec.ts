import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import AiComposer from './AiComposer.vue';
import zhCN from '@/locales/zh-CN';
import { aiDevelopmentApi as api } from '@/api/development/ai';
vi.mock('@/api/development/ai', async original => ({ ...await original<object>(), aiDevelopmentApi: { createMessage: vi.fn(), executeTask: vi.fn(), uploadAttachment: vi.fn(), deleteAttachment: vi.fn() } }));
beforeEach(() => { vi.resetAllMocks(); URL.createObjectURL = vi.fn(() => 'blob:private'); URL.revokeObjectURL = vi.fn(); });
const setup = () => mount(AiComposer, { props: { conversationId: 1, model: 'm', running: false, saving: false }, global: { plugins: [createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } })] } });
import { readFileSync } from 'node:fs';

describe('独立 composer 边界', () => {
  it('IME、repeat、ShiftEnter 不发送；失败重试复用 message_id 和任务 key', async () => {
    vi.mocked(api.createMessage).mockResolvedValue({ id: 7 } as never);
    vi.mocked(api.executeTask).mockRejectedValueOnce(new Error('fail')).mockResolvedValueOnce({ id: 8 } as never);
    const wrapper = setup(); const input = wrapper.get('textarea');
    await input.setValue('hello');
    for (const extra of [{ isComposing: true }, { repeat: true }, { shiftKey: true }]) await input.trigger('keydown', { key: 'Enter', ...extra });
    expect(api.createMessage).not.toHaveBeenCalled();
    await input.trigger('keydown', { key: 'Enter' }); await flushPromises();
    await wrapper.get('.send').trigger('click'); await flushPromises();
    expect(api.createMessage).toHaveBeenCalledTimes(1);
    expect(api.executeTask).toHaveBeenCalledTimes(2);
    expect(vi.mocked(api.executeTask).mock.calls[0]).toEqual(vi.mocked(api.executeTask).mock.calls[1]);
    expect(wrapper.emitted('sent')).toHaveLength(1); wrapper.unmount();
  });
  it('发送锁阻止重复点击，模型保存时禁发，草稿按会话隔离', async () => {
    vi.mocked(api.createMessage).mockImplementation(() => new Promise(() => {}));
    const wrapper = setup(); await wrapper.get('textarea').setValue('draft1');
    await wrapper.setProps({ saving: true }); await wrapper.get('.send').trigger('click'); expect(api.createMessage).not.toHaveBeenCalled();
    await wrapper.setProps({ saving: false }); await wrapper.get('.send').trigger('click'); await wrapper.get('.send').trigger('click');
    expect(api.createMessage).toHaveBeenCalledTimes(1);
    await wrapper.setProps({ conversationId: 2 }); expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('');
    await wrapper.setProps({ conversationId: 1 }); expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('draft1'); wrapper.unmount();
  });
  it('明确拒绝 PDF，不发起上传', async () => {
    const wrapper = setup(); const input = wrapper.get('input[type=file]');
    Object.defineProperty(input.element, 'files', { value: [new File(['%PDF'], 'private.pdf', { type: 'application/pdf' })] });
    await input.trigger('change'); await flushPromises();
    expect(api.uploadAttachment).not.toHaveBeenCalled(); expect(wrapper.get('[role=alert]').text()).toContain('拒绝'); wrapper.unmount();
  });
  it('响应丢失重试保持消息 key，成功后的新草稿更换 key', async () => {
    vi.mocked(api.createMessage).mockRejectedValueOnce(new Error('response lost')).mockResolvedValue({ id: 7 } as never);
    vi.mocked(api.executeTask).mockResolvedValue({ id: 8 } as never);
    const wrapper = setup(); await wrapper.get('textarea').setValue('hello');
    await wrapper.get('.send').trigger('click'); await flushPromises();
    await wrapper.get('.send').trigger('click'); await flushPromises();
    const calls = vi.mocked(api.createMessage).mock.calls;
    expect(calls[0][1]).toHaveProperty('idempotency_key', expect.any(String));
    expect(calls[0]).toEqual(calls[1]);
    await wrapper.get('textarea').setValue('hello'); await wrapper.get('.send').trigger('click'); await flushPromises();
    expect(calls[2][1]).not.toEqual(calls[0][1]); wrapper.unmount();
  });
  it('纯附件上传失败可恢复并成功发送，卸载释放预览', async () => {
    vi.mocked(api.uploadAttachment).mockRejectedValueOnce(new Error('upload')).mockResolvedValue({ id: 9, kind: 'text' } as never);
    vi.mocked(api.createMessage).mockResolvedValue({ id: 7 } as never);
    vi.mocked(api.executeTask).mockResolvedValue({ id: 8 } as never);
    const wrapper = setup(); const input = wrapper.get('input[type=file]');
    const file = new File(['hello'], 'hello.txt', { type: 'text/plain' });
    Object.defineProperty(file, 'arrayBuffer', { value: async () => new TextEncoder().encode('hello').buffer });
    Object.defineProperty(input.element, 'files', { value: [file] });
    await input.trigger('change'); await flushPromises();
    expect(wrapper.get('.send').attributes('disabled')).toBeDefined();
    await wrapper.get('article button').trigger('click'); await flushPromises();
    expect(wrapper.find('[role=alert]').exists()).toBe(false);
    await wrapper.get('.send').trigger('click'); await flushPromises();
    expect(vi.mocked(api.createMessage).mock.calls[0][1].content).toEqual([{ type: 'attachment', attachment_id: 9 }]);
    expect(wrapper.emitted('sent')).toHaveLength(1); wrapper.unmount();
  });
  it('图片上传未完成即卸载也只释放一次 BlobURL', async () => {
    let resolve!: (value: never) => void;
    vi.mocked(api.uploadAttachment).mockImplementation(() => new Promise(r => { resolve = r; }));
    const wrapper = setup(); const input = wrapper.get('input[type=file]');
    Object.defineProperty(input.element, 'files', { value: [new File(['png'], 'a.png', { type: 'image/png' })] });
    await input.trigger('change'); wrapper.unmount(); resolve({ id: 9 } as never); await flushPromises();
    expect(URL.revokeObjectURL).toHaveBeenCalledTimes(1);
  });
  it('页面应使用独立输入组件，不保留无锁的内联发送表单', () => {
    const source = readFileSync('src/views/development/ai/index.vue', 'utf8');
    expect(source).toContain('<AiComposer');
    expect(source).not.toContain('<form class="composer"');
  });
});
