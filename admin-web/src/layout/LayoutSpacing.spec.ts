import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const readSource = (relativePath: string): string =>
  readFileSync(resolve(import.meta.dirname, relativePath), 'utf8');

const header = readSource('components/Header.vue');
const tabs = readSource('components/Tabs.vue');
const layout = readSource('index.vue');

const readStyleBlock = (source: string, selector: string): string => {
  const escapedSelector = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const match = source.match(new RegExp(`${escapedSelector}\\s*\\{([^}]*)\\}`, 's'));
  expect(match, `缺少布局选择器 ${selector}`).not.toBeNull();
  return match?.[1] ?? '';
};

describe('共享布局间距契约', () => {
  it('Header 和 Tabs 使用统一的 8px 水平留白', () => {
    expect(readStyleBlock(header, '.app-header')).toMatch(/padding:\s*0 8px;/);
    expect(readStyleBlock(tabs, '.app-tabs')).toMatch(/padding:\s*0 8px;/);
  });

  it('主内容区保留 16px 垂直留白并收紧为 8px 水平留白', () => {
    expect(readStyleBlock(layout, '.app-layout__main')).toMatch(/padding:\s*16px 8px;/);
  });

  it('移动端主内容区继续使用现有 12px 留白', () => {
    expect(readStyleBlock(layout, '.app-layout.is-mobile .app-layout__main')).toMatch(
      /padding:\s*12px;/
    );
  });
});
