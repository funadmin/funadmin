import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import PrivateAttachment from './PrivateAttachment.vue';
import zhCN from '@/locales/zh-CN';
import { aiDevelopmentApi as api } from '@/api/development/ai';
vi.mock('@/api/development/ai', () => ({ aiDevelopmentApi: { attachmentContent: vi.fn() } }));
const setup = () => mount(PrivateAttachment, { props: { conversationId: 1, attachmentId: 9 }, global: { plugins: [createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } })] } });
const deferred = () => { let resolve!: (blob: Blob) => void; const promise = new Promise<Blob>(r => { resolve = r; }); return { promise, resolve }; };
beforeEach(() => { vi.resetAllMocks(); let id = 0; URL.createObjectURL = vi.fn(() => `blob:private-${++id}`); URL.revokeObjectURL = vi.fn(); vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {}); });
afterEach(() => { vi.useRealTimers(); vi.restoreAllMocks(); });
describe('私有附件 BlobURL 生命周期', () => {
  it('卸载后迟到响应不得创建预览，且取消请求', async () => {
    const pending = deferred(); vi.mocked(api.attachmentContent).mockReturnValue(pending.promise);
    const wrapper = setup(); const signal = vi.mocked(api.attachmentContent).mock.calls[0][2]; wrapper.unmount();
    pending.resolve(new Blob(['png'], { type: 'image/png' })); await flushPromises();
    expect(signal?.aborted).toBe(true); expect(URL.createObjectURL).not.toHaveBeenCalled();
  });
  it('切换引用忽略旧响应并释放现有预览', async () => {
    const old = deferred(); vi.mocked(api.attachmentContent).mockReturnValueOnce(old.promise).mockResolvedValue(new Blob(['new'], { type: 'image/png' }));
    const wrapper = setup(); await wrapper.setProps({ attachmentId: 10 }); await flushPromises();
    old.resolve(new Blob(['old'], { type: 'image/png' })); await flushPromises();
    expect(URL.createObjectURL).toHaveBeenCalledTimes(1); wrapper.unmount(); expect(URL.revokeObjectURL).toHaveBeenCalledExactlyOnceWith('blob:private-1');
  });
  it('下载等待期间切换附件，旧点击不得下载新附件', async () => {
    const old = deferred();
    vi.mocked(api.attachmentContent).mockRejectedValueOnce(new Error('retry')).mockReturnValueOnce(old.promise).mockResolvedValueOnce(new Blob(['new'], { type: 'text/plain' }));
    const wrapper = setup(); await flushPromises(); await wrapper.get('button').trigger('click');
    await wrapper.setProps({ attachmentId: 10 }); await flushPromises();
    old.resolve(new Blob(['old'], { type: 'text/plain' })); await flushPromises();
    expect(HTMLAnchorElement.prototype.click).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('下载成功后卸载与延时清理竞争，每个 URL 只回收一次', async () => {
    vi.mocked(api.attachmentContent).mockResolvedValue(new Blob(['png'], { type: 'image/png' }));
    const wrapper = setup(); await flushPromises(); vi.useFakeTimers();
    await wrapper.get('button').trigger('click'); expect(HTMLAnchorElement.prototype.click).toHaveBeenCalledTimes(1);
    wrapper.unmount(); vi.runAllTimers();
    expect(URL.revokeObjectURL).toHaveBeenCalledTimes(2);
    expect(new Set(vi.mocked(URL.revokeObjectURL).mock.calls.map(([url]) => url)).size).toBe(2);
  });
});
