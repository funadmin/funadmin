import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const styles = readFileSync(resolve(process.cwd(), 'src/styles/index.scss'), 'utf8');

describe('顶部菜单焦点样式', () => {
  it('菜单复合组件不使用通用 tabindex 外圈', () => {
    expect(styles).toContain(':not(.el-menu):not(.el-menu-item):not(.el-sub-menu):not(.el-sub-menu__title):not(.el-menu--popup-container):not(.el-popper)');
  });

  it('菜单项键盘聚焦使用内部背景而非外框', () => {
    expect(styles).toMatch(
      /\.el-menu-item:focus-visible,[\s\S]*?\.el-sub-menu__title:focus-visible\s*\{[^}]*outline:\s*none[^}]*background-color:/s
    );
  });
});
