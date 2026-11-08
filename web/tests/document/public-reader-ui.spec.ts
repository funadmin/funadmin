import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { JSDOM } from 'jsdom';
import { afterEach, describe, expect, it, vi } from 'vitest';

const source = () => readFileSync(resolve(process.cwd(), '../admin/plugins/document/app/document/view/public/reader.js'), 'utf8');
let dom: JSDOM | undefined;
afterEach(() => { dom?.window.close(); vi.restoreAllMocks(); });
function page(mobile = false) {
  dom = new JSDOM(`<!doctype html><html><body>
    <form role="search"><input id="docs-search" name="keyword" /></form>
    <button data-theme-toggle aria-label="切换主题"></button>
    <details data-navigation open><summary>文档目录</summary><nav><a href="/docs/intro">入门</a></nav></details>
    <details class="category-group" data-category-id="framework" open><summary class="category-link">框架</summary><a class="chapter-link" href="/docs/intro">入门</a></details>
    <div data-code-block><pre><code>&lt;?php echo '你好';</code></pre><button data-copy-code>复制代码</button></div>
    <button data-copy-page>复制链接</button><span data-copy-feedback role="status"></span>
    </body></html>`, { url: 'http://localhost/docs/intro', runScripts: 'outside-only' });
  const screen = { matches: mobile, addEventListener: vi.fn() };
  Object.defineProperty(dom.window, 'matchMedia', { value: vi.fn((query: string) => query.includes('820') ? screen : { matches: false, addEventListener: vi.fn() }) });
  const writeText = vi.fn(async () => {});
  Object.defineProperty(dom.window.navigator, 'clipboard', { value: { writeText } });
  dom.window.eval(source());
  return { window: dom.window, document: dom.window.document, writeText, screen };
}
describe('公开文档站交互', () => {
  it('主题按钮切换页面主题并保存偏好', () => {
    const p = page();
    p.document.querySelector<HTMLButtonElement>('[data-theme-toggle]')!.click();
    expect(p.document.documentElement.dataset.theme).toBe('dark');
    expect(p.window.localStorage.getItem('FUNADMIN_DOCS_THEME')).toBe('dark');
    p.document.querySelector<HTMLButtonElement>('[data-theme-toggle]')!.click();
    expect(p.document.documentElement.dataset.theme).toBe('light');
  });
  it('代码复制保留原始文本，分享按钮复制当前公开链接', async () => {
    const p = page();
    p.document.querySelector<HTMLButtonElement>('[data-copy-code]')!.click();
    await Promise.resolve();
    expect(p.writeText).toHaveBeenCalledWith("<?php echo '你好';");
    p.document.querySelector<HTMLButtonElement>('[data-copy-page]')!.click();
    await Promise.resolve();
    expect(p.writeText).toHaveBeenCalledWith('http://localhost/docs/intro');
    await vi.waitFor(() => expect(p.document.querySelector('[role="status"]')!.textContent).toContain('已复制'));
  });
  it('键盘快捷键聚焦搜索，输入框中的斜杠仍能正常输入', () => {
    const p = page();
    const command = new p.window.KeyboardEvent('keydown', { key:'k', ctrlKey:true, bubbles:true, cancelable:true });
    p.document.dispatchEvent(command);
    expect(p.document.activeElement).toBe(p.document.querySelector('#docs-search'));
    expect(command.defaultPrevented).toBe(true);
    const slash = new p.window.KeyboardEvent('keydown', { key:'/', bubbles:true, cancelable:true });
    p.document.querySelector('input')!.dispatchEvent(slash);
    expect(slash.defaultPrevented).toBe(false);
  });
  it('窄屏初始折叠目录，目录链接仍可通过原生 details 打开', () => {
    const p = page(true);
    const navigation = p.document.querySelector<HTMLDetailsElement>('[data-navigation]')!;
    expect(navigation.open).toBe(false);
    p.document.querySelector<HTMLElement>('summary')!.click();
    expect(navigation.open).toBe(true);
    expect(navigation.querySelector('a')!.href).toBe('http://localhost/docs/intro');
  });
  it('保存文档分类的折叠状态，重新初始化后恢复', async () => {
    const p = page();
    const category = p.document.querySelector<HTMLDetailsElement>('[data-category-id="framework"]')!;
    category.querySelector<HTMLElement>('summary')!.click();
    expect(category.open).toBe(false);
    await vi.waitFor(() => expect(p.window.sessionStorage.getItem('FUNADMIN_DOCS_CATEGORIES')).toContain('framework'));

    p.window.eval(source());
    expect(category.open).toBe(false);
  });
});
