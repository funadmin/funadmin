import { describe, expect, it, vi, afterEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { ElMessage } from 'element-plus';

import { escapeHtml, highlight } from './highlighter';

describe('highlight', () => {
  it('highlights php into span tokens', async () => {
    const html = await highlight('<?php echo 1;', 'php');
    expect(html).toContain('<span');
    expect(html).not.toContain('<?php');
  }, 20000);

  it('returns escaped source for text', async () => {
    const html = await highlight('<a href="x">&\'</a>', 'text');
    expect(html).toBe('&lt;a href=&quot;x&quot;&gt;&amp;&#39;&lt;/a&gt;');
    expect(html).not.toContain('<span');
  });

  it('escapeHtml escapes all special characters', () => {
    expect(escapeHtml('<>&"\'')).toBe('&lt;&gt;&amp;&quot;&#39;');
  });

  it.each(['sql', 'typescript', 'vue', 'json'] as const)('highlights %s', async (lang) => {
    const html = await highlight(lang === 'json' ? '{"a":1}' : 'select 1', lang);
    expect(html).toContain('<span');
  }, 20000);
});

describe('CodeBlock', () => {
  afterEach(() => {
    vi.doUnmock('./highlighter');
    vi.resetModules();
    vi.restoreAllMocks();
  });

  // i18n is installed globally by tests/setup.ts; Element Plus is auto-resolved only in Vite, so stub the button.
  const global = { stubs: { 'el-button': { template: '<button type="button"><slot /></button>' } } };

  it('falls back to escaped plain text when highlighting fails', async () => {
    vi.resetModules();
    vi.doMock('./highlighter', async (importOriginal) => ({
      ...(await importOriginal<typeof import('./highlighter')>()),
      highlight: vi.fn().mockRejectedValue(new Error('load failed'))
    }));
    const { default: CodeBlock } = await import('./CodeBlock.vue');
    const wrapper = mount(CodeBlock, {
      props: { code: '<?php\necho "<b>";', language: 'php' },
      global
    });
    await flushPromises();
    const pre = wrapper.find('pre');
    expect(pre.exists()).toBe(true);
    expect(pre.text()).toContain('echo "<b>";');
    expect(wrapper.html()).not.toContain('<b>');
    expect(wrapper.findAll('.code-block__line-number')).toHaveLength(2);
  });

  it('renders shiki output with line numbers and copies code', async () => {
    const { default: CodeBlock } = await import('./CodeBlock.vue');
    const writeText = vi.fn().mockResolvedValue(undefined);
    const success = vi.spyOn(ElMessage, 'success').mockImplementation(() => ({ close: () => undefined }) as never);
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
    const wrapper = mount(CodeBlock, {
      props: { code: '{"a":1}\n{"b":2}\n{"c":3}', language: 'json' },
      global
    });
    await vi.waitFor(() => expect(wrapper.find('.code-block__body span').exists()).toBe(true), { timeout: 20000 });
    expect(wrapper.findAll('.code-block__line-number')).toHaveLength(3);
    await wrapper.find('button.code-block__copy').trigger('click');
    expect(writeText).toHaveBeenCalledWith('{"a":1}\n{"b":2}\n{"c":3}');
    await flushPromises();
    expect(success).toHaveBeenCalledWith('已复制');
  }, 25000);

  it('reports copy failure without throwing when clipboard is unavailable', async () => {
    const { default: CodeBlock } = await import('./CodeBlock.vue');
    const error = vi.spyOn(ElMessage, 'error').mockImplementation(() => ({ close: () => undefined }) as never);
    Object.defineProperty(navigator, 'clipboard', { value: { writeText: vi.fn().mockRejectedValue(new Error('denied')) }, configurable: true });
    const execCommand = vi.fn().mockReturnValue(false);
    Object.defineProperty(document, 'execCommand', { value: execCommand, configurable: true });
    const wrapper = mount(CodeBlock, { props: { code: 'plain', language: 'text' }, global });
    await wrapper.find('button.code-block__copy').trigger('click');
    await flushPromises();
    expect(execCommand).toHaveBeenCalledWith('copy');
    expect(error).toHaveBeenCalledWith('复制失败，请手动选择复制');
    expect(wrapper.find('pre').text()).toBe('plain');
    expect(wrapper.find('.code-block__body span').exists()).toBe(false);
  });
});
