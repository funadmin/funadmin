import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const styles = readFileSync(resolve(process.cwd(), 'src/styles/index.scss'), 'utf8');
const topMenu = readFileSync(resolve(process.cwd(), 'src/layout/components/TopMenu.vue'), 'utf8');

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
