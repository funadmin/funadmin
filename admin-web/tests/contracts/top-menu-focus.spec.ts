import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const styles = readFileSync(resolve(process.cwd(), 'src/styles/index.scss'), 'utf8');
const variables = readFileSync(resolve(process.cwd(), 'src/styles/variables.scss'), 'utf8');
const topMenu = readFileSync(resolve(process.cwd(), 'src/layout/components/TopMenu.vue'), 'utf8');
const lightPresets = ['elegant', 'fresh', 'classic', 'minimal', 'vibrant', 'sunset'] as const;

const readBlock = (selector: string): string => {
  const escapedSelector = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const match = variables.match(new RegExp(`${escapedSelector}\\s*\\{([^}]*)\\}`, 's'));
  expect(match, `缺少主题选择器 ${selector}`).not.toBeNull();
  return match?.[1] ?? '';
};

const readVariable = (block: string, name: string): string | undefined =>
  block.match(new RegExp(`${name}\\s*:\\s*([^;]+);`))?.[1].trim();

const root = readBlock(':root');

const effectiveLightValue = (preset: string, name: string): string | undefined =>
  readVariable(readBlock(`html[data-preset='${preset}']`), name) ?? readVariable(root, name);

describe('顶部菜单焦点样式', () => {
  it('菜单复合组件不使用通用 tabindex 外圈', () => {
    expect(styles).toContain(':not(.el-menu):not(.el-menu-item):not(.el-sub-menu):not(.el-sub-menu__title):not(.el-menu--popup-container):not(.el-popper)');
  });

  it('顶部菜单各级焦点容器强制取消外框和阴影', () => {
    expect(styles).toMatch(
      /\.app-top-menu \.el-sub-menu:focus[^}]*outline:\s*none\s*!important;[^}]*box-shadow:\s*none\s*!important;/s
    );
    expect(styles).toMatch(
      /\.app-top-menu-popper\.el-menu--popup-container:focus[^}]*outline:\s*none\s*!important;[^}]*box-shadow:\s*none\s*!important;/s
    );
  });

  it('菜单项键盘聚焦使用内部背景而非外框', () => {
    expect(styles).toMatch(
      /\.el-menu-item:focus-visible,[\s\S]*?\.el-sub-menu__title:focus-visible\s*\{[^}]*outline:\s*none[^}]*background-color:/s
    );
  });

  it('路由切换后顶层父菜单保持浅主色背景', () => {
    expect(topMenu).toMatch(
      /\.app-top-menu\.el-menu--horizontal\s*>\s*:deep\(\.el-sub-menu\.is-active\s*>\s*\.el-sub-menu__title\)[^}]*background:\s*color-mix\(in srgb, var\(--el-color-primary\) 10%, var\(--app-header-bg\)\)\s*!important;/s
    );
    expect(topMenu).not.toMatch(/\.el-sub-menu\.is-active[^}]*background:\s*transparent\s*!important;/s);
  });
});

describe('布局主题背景契约', () => {
  it('根主题的顶部导航和页签栏使用纯白卡片背景', () => {
    expect(readVariable(root, '--app-header-bg')).toMatch(/^(?:var\(--app-card-bg\)|#fff(?:fff)?)$/i);
    expect(readVariable(root, '--app-tabs-bg')).toMatch(/^(?:var\(--app-card-bg\)|#fff(?:fff)?)$/i);
  });

  it.each(lightPresets)('%s 浅色 preset 的顶部导航和页签栏最终为纯白', (preset) => {
    expect(effectiveLightValue(preset, '--app-header-bg')).toMatch(/^(?:var\(--app-card-bg\)|#fff(?:fff)?)$/i);
    expect(effectiveLightValue(preset, '--app-tabs-bg')).toMatch(/^(?:var\(--app-card-bg\)|#fff(?:fff)?)$/i);
  });

  it('内容区和侧栏背景保持原有主题职责', () => {
    expect(readVariable(root, '--app-content-bg')).toBe('#f5f7fa');
    expect(readVariable(root, '--app-sidebar-bg')).toBe('#ffffff');
  });

  it('dark 模式的顶部导航、页签栏和内容区保持深色', () => {
    const dark = readBlock('html.dark');
    expect(readVariable(dark, '--app-header-bg')).toBe('#161b22');
    expect(readVariable(dark, '--app-tabs-bg')).toBe('#161b22');
    expect(readVariable(dark, '--app-content-bg')).toBe('#0d1117');
  });
});
