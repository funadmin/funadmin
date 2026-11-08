import { describe, expect, it } from 'vitest';
import categoryWorkspace from './CategoryWorkspace.vue?raw';
import leftPanel from './DesignerLeftPanel.vue?raw';
import canvasNode from './DesignerCanvasNode.vue?raw';
import listPreview from './ListPreview.vue?raw';
import listPreviewZone from './ListPreviewZone.vue?raw';

/**
 * 夜间模式：store/modules/app.ts 的 applyPrimaryColor 在 <html> 内联写入 --el-color-primary-light-1…9，
 * 一律与白色混合，覆盖了 Element Plus 的 dark css-vars；因此这些变量在 html.dark 下仍是浅色，
 * 当作背景时会让 --el-text-color-primary（夜间为浅色）的文字看不见。设计器组件改用与 --el-bg-color 混合的主色。
 */
const styleOf = (source: string) => source.slice(source.indexOf('<style'));
const files = { CategoryWorkspace: categoryWorkspace, DesignerLeftPanel: leftPanel, DesignerCanvasNode: canvasNode, ListPreview: listPreview, ListPreviewZone: listPreviewZone };

describe('设计器组件夜间模式配色', () => {
  it.each(Object.entries(files))('%s 不使用夜间仍为浅色的 --el-color-primary-light-* 变量', (_name, source) => {
    expect(styleOf(source)).not.toMatch(/--el-color-primary-light-\d/);
  });

  it.each(Object.entries(files))('%s 不写死颜色字面量（布局强调色除外）', (_name, source) => {
    const literals = styleOf(source)
      .split('\n')
      .filter((line) => /#[0-9a-f]{3,8}\b|rgba?\(|(?<![-\w])(?:white|black)(?![-\w])/i.test(line))
      .filter((line) => !/^\s*--designer-layout-accent:/.test(line));
    expect(literals).toEqual([]);
  });

  it('选中的分类方式卡片：背景随主题，标题使用主文字色', () => {
    const rule = styleOf(categoryWorkspace).match(/\.category-card\.is-on\s*\{([^}]*)\}/)?.[1] ?? '';
    expect(rule).toMatch(/background:\s*color-mix\(in srgb, var\(--el-color-primary\) \d+%, var\(--el-bg-color\)\)/);
    expect(styleOf(categoryWorkspace)).toMatch(/\.category-card\s*\{[^}]*color:\s*var\(--el-text-color-primary\)/);
  });

  it.each([['DesignerLeftPanel', leftPanel], ['DesignerCanvasNode', canvasNode]])('%s 的紫色布局强调色在 html.dark 下有更亮的覆盖值', (_name, source) => {
    const style = styleOf(source);
    const light = style.match(/(?:^|\n)\.[\w-]+ \{[^}]*--designer-layout-accent:\s*(#[0-9a-f]{6})/i)?.[1];
    // scoped 样式里写普通的 html.dark 前缀（:global(...) 会把整条选择器替换成 html.dark，丢掉组件类名）。
    const dark = style.match(/(?:^|\n)html\.dark [^{]*\{[^}]*--designer-layout-accent:\s*(#[0-9a-f]{6})/i)?.[1];
    expect(light).toBeTruthy();
    expect(dark).toBeTruthy();
    expect(dark?.toLowerCase()).not.toBe(light?.toLowerCase());
  });
});
