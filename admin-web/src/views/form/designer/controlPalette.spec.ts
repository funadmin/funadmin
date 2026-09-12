import { describe, expect, it } from 'vitest';
import { controlIcon } from './controlPalette';

describe('表单设计器控件面板', () => {
  it('为常用控件提供可识别图标', () => {
    expect(controlIcon({ type: 'input' })).toBe('i-ep-edit');
    expect(controlIcon({ type: 'date' })).toBe('i-ep-calendar');
    expect(controlIcon({ type: 'image' })).toBe('i-ep-picture');
    expect(controlIcon({ type: 'grid' })).toBe('i-ep-grid');
  });

  it('插件和未知控件使用通用组件图标', () => {
    expect(controlIcon({ type: 'demo:rating' })).toBe('i-ep-component');
  });
});
