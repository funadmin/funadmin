import { describe, expect, it } from 'vitest';
import { controlIcon, paletteContainers } from './controlPalette';

describe('表单设计器控件面板', () => {
  it('为常用控件提供可识别图标', () => {
    expect(controlIcon({ type: 'input' })).toBe('i-ep-edit');
    expect(controlIcon({ type: 'date' })).toBe('i-ep-calendar');
    expect(controlIcon({ type: 'image' })).toBe('i-ep-picture');
    expect(controlIcon({ type: 'grid' })).toBe('i-ep-grid');
  });

  it('所有映射和兜底均使用图标包中存在的类名', async () => {
    const iconPack = await import('@iconify-json/ep/icons.json');
    const icons = iconPack.default.icons;
    const controls = ['input', 'datetime', 'json', 'demo:rating'];

    for (const type of controls) {
      expect(icons[controlIcon({ type }).replace('i-ep-', '')]).toBeDefined();
    }
  });

  it('以每个控件网格作为拖拽源容器', () => {
    const palette = document.createElement('div');
    palette.innerHTML = '<div class="palette-group-grid"><div class="palette-item"></div></div><div class="palette-group-grid"></div>';

    expect(paletteContainers(palette)).toEqual(Array.from(palette.querySelectorAll('.palette-group-grid')));
  });
});
