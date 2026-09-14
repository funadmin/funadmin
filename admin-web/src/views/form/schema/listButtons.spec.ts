import { describe, expect, it } from 'vitest';
import { resolveListButtons, buildListFieldMap } from './listButtons';
import type { FormListButton, FormListConfiguration } from './types';

const defaults: FormListButton[] = [{ id: 'edit', label: '编辑', action: { type: 'builtin', key: 'edit' } }];
describe('列表按钮共享协议', () => {
  it('缺省使用宿主默认集合，显式空集合保持隐藏', () => {
    expect(resolveListButtons(undefined, 'row', defaults)).toEqual(defaults);
    expect(resolveListButtons({ buttons: {} }, 'row', defaults)).toEqual(defaults);
    expect(resolveListButtons({ buttons: { row: [] } }, 'row', defaults)).toEqual([]);
    expect(resolveListButtons({ buttons: { toolbar: [] } }, 'row', defaults)).toEqual(defaults);
  });
  it('稳定排序并且不修改原配置或宿主默认集合', () => {
    const buttons: FormListButton[] = [
      { id: 'b', label: '删除', order: 2, action: { type: 'refresh' } },
      { id: 'a', label: '改名不改变行为', order: 1, action: { type: 'builtin', key: 'edit' } },
      { id: 'c', label: '刷新', order: 2, action: { type: 'refresh' } }
    ];
    const result = resolveListButtons({ buttons: { row: buttons } }, 'row', defaults);
    expect(result.map((button) => button.id)).toEqual(['a', 'b', 'c']);
    expect(result[0]?.action).toEqual({ type: 'builtin', key: 'edit' });
    expect(buttons.map((button) => button.id)).toEqual(['b', 'a', 'c']);
    result[0]!.label = '独立副本';
    expect(buttons[1]!.label).toBe('改名不改变行为');
  });
  it('运行期损坏集合 fail-closed，不能回退默认写按钮', () => {
    expect(() => resolveListButtons({ buttons: { row: null } } as unknown as FormListConfiguration, 'row', defaults)).toThrow();
    expect(() => resolveListButtons({ buttons: { row: [defaults[0], defaults[0]] } } as FormListConfiguration, 'row', defaults)).toThrow();
  });
  it('字段映射确定性，snake/camel 别名冲突及原型字段拒绝', () => {
    expect(buildListFieldMap(['id', 'display_name'], 'camel')).toEqual({ id: 'id', display_name: 'displayName' });
    expect(buildListFieldMap(['display_name'], 'snake')).toEqual({ display_name: 'display_name' });
    expect(() => buildListFieldMap(['foo_bar', 'foo__bar'], 'camel')).toThrow();
    for (const field of ['__proto__', 'constructor', 'prototype', 'row.id', 'displayName']) {
      expect(() => buildListFieldMap([field], 'camel')).toThrow();
    }
  });
});
