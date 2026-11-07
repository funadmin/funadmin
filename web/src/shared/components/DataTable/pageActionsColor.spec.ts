import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import PageActions from './PageActions.vue';
import type { PageAction, PageContext } from './pageSchema';

const toggle = (id: string, color: 'primary' | 'warning', recycled: boolean): PageAction => ({
  id, label: id, color, action: { type: 'registered', key: id }, activeWhen: { field: 'recycled', op: 'eq', value: recycled }, inactiveColor: 'info'
} as unknown as PageAction);
const context = (recycled: boolean): PageContext => ({ values: { recycled }, permissions: ['*'], handlers: { normal: { version: '1', run: () => {} }, recycled: { version: '1', run: () => {} } } } as unknown as PageContext);
const colorOf = (recycled: boolean) => {
  const wrapper = mount(PageActions, { props: { actions: [toggle('normal', 'primary', false), toggle('recycled', 'warning', true)], context: context(recycled) },
    global: { stubs: { ListButtonBar: { name: 'ListButtonBar', props: ['buttons'], template: '<i />' } } } });
  return Object.fromEntries((wrapper.findComponent({ name: 'ListButtonBar' }).props('buttons') as Array<{ id: string; color: string }>).map(b => [b.id, b.color]));
};

describe('列表模式切换按钮（正常列表 / 回收站）', () => {
  it('当前模式保持各自语义色，非当前模式跟随主题色而不是灰色', () => {
    expect(colorOf(false)).toEqual({ normal: 'primary', recycled: 'primary' });
    expect(colorOf(true)).toEqual({ normal: 'primary', recycled: 'warning' });
  });

  it('手写的回收站切换与 CRUD 生成器不再用灰色 info', () => {
    for (const file of ['src/admin/views/system/member-group/index.vue', 'src/admin/views/system/member-level/index.vue', 'src/admin/views/system/blacklist/index.vue']) {
      expect(readFileSync(resolve(process.cwd(), file), 'utf8'), file).not.toMatch(/recycled \? 'warning' : 'info'|recycled \? 'info' : 'primary'/);
    }
    const generator = readFileSync(resolve(process.cwd(), '../admin/app/common/crud/ProductionTemplateContext.php'), 'utf8');
    expect(generator).not.toContain("? 'primary' : 'info')");
    expect(generator).not.toContain("? 'warning' : 'info')");
  });
});
