import { describe, expect, it, vi, beforeEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

const box = vi.hoisted(() => ({ prompt: vi.fn(), error: vi.fn() }));
const upload = vi.hoisted(() => vi.fn());
vi.mock('element-plus', () => ({ ElMessageBox: { prompt: box.prompt }, ElMessage: { error: box.error } }));
vi.mock('@/api/common/upload', () => ({ uploadApi: { upload } }));

import DocumentEditor from '../../../admin/plugins/document/web/src/admin/plugins/document/editor/DocumentEditor.vue';
import { documentMessages } from '../../../admin/plugins/document/web/src/admin/plugins/document/locales';

const i18n = (locale: 'zh-CN' | 'en-US') => createI18n({ legacy: false, locale, messages: documentMessages });
type EditorVm = { editor: { getJSON: () => any; commands: { setNodeSelection: (p: number) => boolean } } };
const mountEditor = (content: any, locale: 'zh-CN' | 'en-US' = 'zh-CN') =>
  mount(DocumentEditor, { props: { modelValue: content, generation: 1 }, attachTo: document.body, global: { plugins: [i18n(locale)] } });
const button = (wrapper: any, text: string) => wrapper.findAll('.doc-toolbar button').find((b: any) => b.text() === text)!;

describe('编辑器工具栏动作', () => {
  beforeEach(() => { box.prompt.mockReset(); upload.mockReset(); box.error.mockReset(); });

  it('选中已有 Mermaid 图表时，图表按钮编辑其源码而不是再插入一个', async () => {
    const wrapper = mountEditor({ type: 'doc', content: [{ type: 'mermaid', attrs: { source: 'graph TD; A-->B' } }] });
    await flushPromises();
    const vm = wrapper.vm as unknown as EditorVm;
    vm.editor.commands.setNodeSelection(0);
    box.prompt.mockResolvedValue({ value: 'graph TD; A-->C' });
    await button(wrapper, '图表').trigger('click');
    await flushPromises();
    expect(box.prompt.mock.calls[0][2]).toMatchObject({ inputValue: 'graph TD; A-->B' });
    const nodes = vm.editor.getJSON().content.filter((n: any) => n.type === 'mermaid');
    expect(nodes).toHaveLength(1);
    expect(nodes[0].attrs.source).toBe('graph TD; A-->C');
    wrapper.unmount();
  });

  it('上传附件后插入指向附件的链接', async () => {
    upload.mockResolvedValue({ url: '/storage/uploads/spec.pdf', name: 'spec.pdf' });
    const wrapper = mountEditor({ type: 'doc', content: [{ type: 'paragraph' }] });
    await flushPromises();
    const input = wrapper.find('input[data-upload="file"]');
    Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'spec.pdf')] });
    await input.trigger('change');
    await flushPromises();
    expect(upload).toHaveBeenCalledWith(expect.any(File), 'file');
    const json = JSON.stringify((wrapper.vm as unknown as EditorVm).editor.getJSON());
    expect(json).toContain('"href":"/storage/uploads/spec.pdf"');
    expect(json).toContain('spec.pdf');
    wrapper.unmount();
  });

  it('上传返回不安全地址时拒绝插入', async () => {
    upload.mockResolvedValue({ url: 'javascript:alert(1)', name: 'x' });
    const wrapper = mountEditor({ type: 'doc', content: [{ type: 'paragraph' }] });
    await flushPromises();
    const input = wrapper.find('input[data-upload="file"]');
    Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'x.pdf')] });
    await input.trigger('change');
    await flushPromises();
    expect(JSON.stringify((wrapper.vm as unknown as EditorVm).editor.getJSON())).not.toContain('javascript:');
    expect(box.error).toHaveBeenCalled();
    wrapper.unmount();
  });

  it('英文界面下工具栏使用英文', async () => {
    const wrapper = mountEditor({ type: 'doc', content: [{ type: 'paragraph' }] }, 'en-US');
    await flushPromises();
    const text = wrapper.find('.doc-toolbar').text();
    expect(text).toContain('Code block');
    expect(text).toContain('Undo');
    expect(text).not.toMatch(/[一-龥]/);
    wrapper.unmount();
  });
});
