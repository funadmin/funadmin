import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const sources = [
  ['源插件', '../admin/plugins/cms/public/plugin-assets/cms/public/cms.js'],
  ['运行副本', '../admin/public/plugin-assets/cms/public/cms.js']
] as const;

beforeEach(() => {
  document.body.innerHTML = '<nav><ul class="cms-menu"><li><details class="cms-menu-dropdown"><summary>产品中心</summary><ul class="cms-menu"><li><a href="/products/">查看全部产品</a></li><li><a href="/solutions/">解决方案</a></li></ul></details></li></ul></nav>';
  vi.stubGlobal('matchMedia', () => ({ matches: true }));
});
afterEach(() => { document.body.innerHTML = ''; vi.unstubAllGlobals(); });

function pointer(element: Element, type: string, pointerType = 'mouse') {
  const event = new Event(type);
  Object.defineProperty(event, 'pointerType', { value: pointerType });
  element.dispatchEvent(event);
}

describe.each(sources)('%s 导航下拉交互', (_name, path) => {
  const script = readFileSync(resolve(process.cwd(), path), 'utf8');
  it('鼠标进入整个父级导航时展开，移出时关闭，子栏目链接保留', () => {
    new Function(script)();
    const dropdown = document.querySelector('details')!;
    pointer(dropdown, 'pointerenter');
    expect(dropdown.open).toBe(true);
    expect(dropdown.querySelector('a[href="/solutions/"]')?.textContent).toBe('解决方案');
    pointer(dropdown, 'pointerleave');
    expect(dropdown.open).toBe(false);
  });
  it('按 Escape 关闭菜单并将焦点留在开关，普通链接保持可跳转', () => {
    new Function(script)();
    const dropdown = document.querySelector('details')!;
    dropdown.open = true;
    const link = dropdown.querySelector('a')!;link.focus();
    link.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
    expect(dropdown.open).toBe(false);
    expect(document.activeElement).toBe(dropdown.querySelector('summary'));
    expect(link.getAttribute('href')).toBe('/products/');
  });
  it('桌面标题点击不会把刚因悬停展开的菜单立即关闭', () => {
    new Function(script)();
    const dropdown = document.querySelector('details')!;
    pointer(dropdown, 'pointerenter');
    const click = new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 });
    Object.defineProperty(click, 'pointerType', { value: 'mouse' });
    dropdown.querySelector('summary')!.dispatchEvent(click);
    expect(dropdown.open).toBe(true);
  });
  it('触屏不因进入或移出意外展开关闭，保留原生点击开关', () => {
    new Function(script)();
    const dropdown = document.querySelector('details')!;
    pointer(dropdown, 'pointerenter', 'touch');expect(dropdown.open).toBe(false);
    dropdown.querySelector('summary')!.click();expect(dropdown.open).toBe(true);
    pointer(dropdown, 'pointerleave', 'touch');expect(dropdown.open).toBe(true);
    dropdown.querySelector('summary')!.click();expect(dropdown.open).toBe(false);
  });
});
