import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { getClampedTabScrollLeft, getTabScrollState } from './Tabs.vue';

const source = readFileSync(resolve(import.meta.dirname, 'Tabs.vue'), 'utf8');
const iconStyles = readFileSync(resolve(import.meta.dirname, '../../styles/ep-icons.css'), 'utf8');

describe('后台页签横向滚动按钮', () => {
  it('在滚动容器两侧提供左右按钮，并保留右侧下拉按钮', () => {
    const leftButton = source.indexOf('class="app-tabs__scroll-button is-left"');
    const scrollContainer = source.indexOf('class="app-tabs__scroll"');
    const rightButton = source.indexOf('class="app-tabs__scroll-button is-right"');
    const dropdown = source.indexOf('<el-dropdown trigger="click" @command="onCmd">');

    expect(leftButton).toBeGreaterThan(-1);
    expect(leftButton).toBeLessThan(scrollContainer);
    expect(rightButton).toBeGreaterThan(scrollContainer);
    expect(rightButton).toBeLessThan(dropdown);
    expect(source).toContain('class="app-tabs__more"');
  });

  it('为左右滚动按钮使用的 Element Plus 图标提供 CSS 定义', () => {
    const scrollIconClasses = [...source.matchAll(/class="(i-ep-arrow-(?:left|right))"/g)]
      .map((match) => match[1]);

    expect(scrollIconClasses).toEqual(['i-ep-arrow-left', 'i-ep-arrow-right']);
    for (const iconClass of scrollIconClasses) {
      expect(iconStyles).toContain(`.${iconClass}{--ep-icon:url(`);
    }
  });

  it.each([
    { scrollLeft: 0, clientWidth: 300, scrollWidth: 300, expected: [false, false] },
    { scrollLeft: 0, clientWidth: 300, scrollWidth: 900, expected: [false, true] },
    { scrollLeft: 300, clientWidth: 300, scrollWidth: 900, expected: [true, true] },
    { scrollLeft: 600, clientWidth: 300, scrollWidth: 900, expected: [true, false] }
  ])('根据溢出和滚动边界返回按钮可用状态 %#', ({ expected, ...metrics }) => {
    const state = getTabScrollState(metrics);

    expect([state.canScrollLeft, state.canScrollRight]).toEqual(expected);
  });

  it.each([
    { scrollLeft: 100, clientWidth: 300, scrollWidth: 900, direction: -1 as const, expected: 0 },
    { scrollLeft: 500, clientWidth: 300, scrollWidth: 900, direction: 1 as const, expected: 600 },
    { scrollLeft: 0, clientWidth: 300, scrollWidth: 200, direction: 1 as const, expected: 0 }
  ])('将按钮目标滚动位置显式限制在有效范围 %#', ({ expected, direction, ...metrics }) => {
    expect(getClampedTabScrollLeft(metrics, direction)).toBe(expected);
  });
});
