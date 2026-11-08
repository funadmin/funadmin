import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { nextTick } from 'vue';

vi.mock('@/api/common/upload', () => ({ uploadApi: { upload: vi.fn() } }));
import { createI18n } from 'vue-i18n';
import { documentMessages } from '../../../admin/plugins/document/web/src/admin/modules/document/locales';
import DocumentEditor from '../../../admin/plugins/document/web/src/admin/modules/document/editor/DocumentEditor.vue';

const doc = (text: string) => ({ type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text }] }] });

const mountEditor = (generation = 1, value = doc('hello world')) =>
  mount(DocumentEditor, { props: { modelValue: value, generation }, attachTo: document.body, global: { plugins: [createI18n({ legacy: false, locale: 'zh-CN', messages: documentMessages })] } });

describe('DocumentEditor', () => {
  it('同一份内容换了键顺序或对象引用时不替换编辑器内容，光标保持不动', async () => {
    const wrapper = mountEditor();
    await flushPromises();
    const editor = (wrapper.vm as unknown as { editor: { commands: { setTextSelection: (n: number) => void }; state: { selection: { from: number } } } }).editor;
    editor.commands.setTextSelection(3);
    const reordered = { content: [{ content: [{ text: 'hello world', type: 'text' }], type: 'paragraph' }], type: 'doc' };
    await wrapper.setProps({ modelValue: reordered });
    await nextTick();
    expect(editor.state.selection.from).toBe(3);
    wrapper.unmount();
  });

  it('generation 变化（切换文档、恢复、重新载入、导入）时替换为新内容', async () => {
    const wrapper = mountEditor();
    await flushPromises();
    await wrapper.setProps({ modelValue: doc('另一篇文档'), generation: 2 });
    await nextTick();
    expect(wrapper.text()).toContain('另一篇文档');
    wrapper.unmount();
  });
});
