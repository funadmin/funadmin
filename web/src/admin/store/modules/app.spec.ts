import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { PRESET_THEMES, useAppStore } from './app';

const SAI_PRIMARY = '#5d87ff';
const variables = readFileSync(resolve(process.cwd(), 'src/shared/styles/variables.scss'), 'utf8');
const block = (selector: string) => {
  const start = variables.indexOf(`${selector} {`);
  expect(start, selector).toBeGreaterThanOrEqual(0);
  return variables.slice(start, variables.indexOf('\n}', start));
};
const token = (css: string, name: string) => css.match(new RegExp(`--${name}:\\s*([^;]+);`))?.[1].trim();

describe('默认配色对齐 SaiAdmin', () => {
  beforeEach(() => { localStorage.clear(); setActivePinia(createPinia()); });

  it('出厂默认、雅致预设与重置都使用 SaiAdmin 主色', () => {
    const app = useAppStore();
    expect(app.primaryColor.toLowerCase()).toBe(SAI_PRIMARY);
    expect(PRESET_THEMES.find(item => item.value === 'elegant')!.primaryColor.toLowerCase()).toBe(SAI_PRIMARY);
    app.primaryColor = '#123456';
    app.resetSettings();
    expect(app.primaryColor.toLowerCase()).toBe(SAI_PRIMARY);
  });

  it('仍停在旧默认蓝的老用户迁移到新主色，自定义颜色保持不变', () => {
    const app = useAppStore();
    app.preset = 'elegant';
    app.primaryColor = '#1677ff';
    app.migrateLegacyPrimary();
    expect(app.primaryColor.toLowerCase()).toBe(SAI_PRIMARY);
    app.primaryColor = '#e11d48';
    app.migrateLegacyPrimary();
    expect(app.primaryColor).toBe('#e11d48');
  });

  it('只改默认雅致预设，其它预设主色不变', () => {
    const colors = Object.fromEntries(PRESET_THEMES.map(item => [item.value, item.primaryColor.toLowerCase()]));
    expect(colors).toMatchObject({ fresh: '#13c2c2', classic: '#1677ff', minimal: '#0f172a', vibrant: '#2f80ed', sunset: '#f97316', midnight: '#1677ff' });
  });

  it('雅致预设与全局默认使用 SaiAdmin 的底色、文字、边框和卡片令牌', () => {
    for (const css of [block(':root'), block("html[data-preset='elegant']")]) {
      expect(token(css, 'app-app-bg')).toBe('#fafbfc');
      expect(token(css, 'app-content-bg')).toBe('#fafbfc');
      expect(token(css, 'app-border')).toBe('#e2e8ee');
      expect(token(css, 'app-text')).toBe('#29343d');
      expect(token(css, 'app-text-secondary')).toBe('#4d5875');
      expect(token(css, 'app-sidebar-text')).toBe('#29343d');
      expect(token(css, 'app-sidebar-hover-bg')).toBe('#edeff0');
      expect(token(css, 'app-card-border')).toBe('#f2f4f5');
      expect(token(css, 'app-radius-lg')).toBe('16px');
    }
  });

  it('圆角缩放在运行时写入的卡片大圆角同样是 SaiAdmin 的 16px，并随倍率缩放', () => {
    const app = useAppStore();
    app.applyRadiusScale();
    expect(document.documentElement.style.getPropertyValue('--app-radius-lg')).toBe('16px');
    app.radiusScale = 0.5;
    app.applyRadiusScale();
    expect(document.documentElement.style.getPropertyValue('--app-radius-lg')).toBe('8px');
  });

  it('夜间模式使用 SaiAdmin 的纯黑底与卡片色', () => {
    const dark = block('html.dark');
    expect(token(dark, 'app-app-bg')).toBe('#070707');
    expect(token(dark, 'app-content-bg')).toBe('#070707');
    expect(token(dark, 'app-card-bg')).toBe('#161618');
    expect(token(dark, 'app-card-border')).toBe('#ffffff14');
  });
});
