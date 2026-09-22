import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import ElementPlus, { ElTable, ElTableColumn } from 'element-plus';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Sortable from 'sortablejs';
import MenuPage from './index.vue';

const api = vi.hoisted(() => ({ tree: vi.fn(), update: vi.fn() }));
vi.mock('@/api/system/menu', () => ({ menuApi: api }));
vi.mock('./components/MenuFormDialog.vue', () => ({ default: { template: '<div />' } }));
vi.mock('@/components/InlineEdit/index.vue', () => ({ default: { template: '<span />' } }));

const menuView = readFileSync(resolve(process.cwd(), 'src/views/system/menu/index.vue'), 'utf8');
const dragColumnIndex = menuView.search(/<el-table-column\b[^>]*label=""[^>]*width="52"/);
const nameColumnIndex = menuView.indexOf('<el-table-column prop="name" :label="t(\'systemMenu.colName\', \'名称\')"');
const selectionColumnIndex = menuView.indexOf('<el-table-column type="selection"');
const columns = [
  { index: dragColumnIndex, node: h(ElTableColumn, { label: '', width: 52, type: menuView.slice(dragColumnIndex, menuView.indexOf('>', dragColumnIndex)).includes('type="index"') ? 'index' : 'default' }, {
    default: ({ row }: { row: { id: number } }) => h('span', { class: 'menu-drag-handle' }, String(row.id)),
  }) },
  { index: nameColumnIndex, node: h(ElTableColumn, { prop: 'name', label: '名称' }) },
  { index: selectionColumnIndex, node: h(ElTableColumn, { type: 'selection', width: 48 }) },
].sort((a, b) => a.index - b.index);

const MenuTableContract = defineComponent({
  setup() {
    const rows = [{ id: 1, name: '系统管理', children: [{ id: 2, name: '菜单管理' }] }];
    return () => h(ElTable, {
      data: rows,
      rowKey: 'id',
      treeProps: { children: 'children' },
      defaultExpandAll: true,
    }, {
      default: () => columns.map(({ node }) => node),
    });
  },
});

const Shell = defineComponent({
  setup(_, { slots }) {
    return () => h('section', [slots['toolbar-left']?.(), slots.default?.({ size: 'default', stripe: false, border: false })]);
  },
});

async function renderMenu() {
  const wrapper = mount(MenuPage, {
    attachTo: document.body,
    global: {
      plugins: [ElementPlus],
      directives: { perm: () => {} },
      stubs: {
        PageWrapper: { template: '<main><slot /></main>' },
        DataTableShell: Shell,
        SearchForm: true,
        SvgIcon: true,
      },
    },
  });
  await flushPromises();
  await nextTick();
  return wrapper;
}

describe('真实菜单页面树表回归', () => {
  beforeEach(() => {
    api.tree.mockResolvedValue([
      { id: 1, parentId: 0, name: '系统管理', type: 'M', sort: 0, children: [
        { id: 2, parentId: 1, name: '菜单管理', type: 'C', sort: 0 },
        { id: 3, parentId: 1, name: '角色管理', type: 'C', sort: 1 },
      ] },
      { id: 4, parentId: 0, name: '受管菜单', type: 'C', sort: 1, readOnly: true },
    ]);
  });

  it('手柄最左，名称列承载展开和子级缩进，勾选及只读限制仍有效', async () => {
    const wrapper = await renderMenu();
    try {
      const rows = wrapper.findAll('.el-table__body tbody tr');
      expect(rows).toHaveLength(4);
      const cells = rows[0]!.findAll('td');
      expect(cells[0]!.get('.menu-drag-handle').attributes('data-menu-id')).toBe('1');
      expect(cells[1]!.find('.el-checkbox').exists()).toBe(true);
      expect(cells[2]!.text()).toBe('系统管理');
      expect(cells[2]!.find('.el-table__expand-icon').exists()).toBe(true);
      expect(cells[0]!.find('.el-table__expand-icon').exists()).toBe(false);
      expect(rows[1]!.findAll('td')[2]!.get('.el-table__indent').attributes('style')).toContain('padding-left: 16px');
      expect(rows[1]!.findAll('td')[0]!.find('.el-table__indent').exists()).toBe(false);
      await cells[2]!.get('.el-table__expand-icon').trigger('click');
      expect(rows[1]!.isVisible()).toBe(false);
      await cells[2]!.get('.el-table__expand-icon').trigger('click');
      expect(rows[1]!.isVisible()).toBe(true);
      await rows[1]!.get('input[type="checkbox"]').setValue(true);
      expect(wrapper.text()).toContain('批量删除(1)');
      expect(rows[3]!.get('input[type="checkbox"]').attributes('disabled')).toBeDefined();
      expect(rows[3]!.find('.menu-drag-handle').exists()).toBe(false);
    } finally { wrapper.unmount(); }
  });

  it('真实 Sortable 绑定手柄并限制同级拖动，全局折叠展开后恢复', async () => {
    const wrapper = await renderMenu();
    try {
      const tbody = wrapper.get('.el-table__body tbody').element as HTMLElement;
      const sortable = Sortable.get(tbody)!;
      expect(sortable.option('handle')).toBe('.menu-drag-handle');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      const move = sortable.option('onMove')!;
      expect(move.call(sortable, { dragged: rows[1], related: rows[2] } as Sortable.MoveEvent, new Event('mousemove'))).toBe(true);
      expect(move.call(sortable, { dragged: rows[1], related: rows[0] } as Sortable.MoveEvent, new Event('mousemove'))).toBe(false);
      expect(move.call(sortable, { dragged: rows[0], related: rows[3] } as Sortable.MoveEvent, new Event('mousemove'))).toBe(false);
      await wrapper.findAll('button').find((button) => button.text() === '折叠')!.trigger('click');
      await flushPromises();
      expect(wrapper.find('.menu-drag-handle').exists()).toBe(false);
      expect(wrapper.findAll('.el-table__body tbody tr')[1]!.isVisible()).toBe(false);
      await wrapper.findAll('button').find((button) => button.text() === '展开')!.trigger('click');
      await flushPromises();
      expect(wrapper.findAll('.el-table__body tbody tr')[1]!.isVisible()).toBe(true);
      expect(Sortable.get(wrapper.get('.el-table__body tbody').element as HTMLElement)?.option('handle')).toBe('.menu-drag-handle');
    } finally { wrapper.unmount(); }
  });
});

describe('系统菜单 Element Plus 树表契约', () => {
  it('拖拽手柄位于第一列，选择和名称列随后，保留树展开', async () => {
    const wrapper = mount(MenuTableContract, {
      attachTo: document.body,
      global: { plugins: [ElementPlus] },
    });
    await nextTick();
    await flushPromises();

    try {
      expect(dragColumnIndex).toBeGreaterThanOrEqual(0);
      expect(dragColumnIndex).toBe(menuView.indexOf('<el-table-column'));
      expect(dragColumnIndex).toBeLessThan(selectionColumnIndex);
      expect(selectionColumnIndex).toBeLessThan(nameColumnIndex);
      const rows = wrapper.findAll('.el-table__body tbody tr');
      expect(rows).toHaveLength(2);
      const cells = rows[0]!.findAll('td');
      expect(cells[0]!.find('.menu-drag-handle').exists()).toBe(true);
      expect(cells[1]!.find('.el-checkbox').exists()).toBe(true);
      expect(cells[2]!.text()).toBe('系统管理');
      await rows[0]!.find('.el-table__expand-icon').trigger('click');
      expect(wrapper.findAll('.el-table__body tbody tr')[1]!.isVisible()).toBe(false);
    } finally {
      wrapper.unmount();
    }
  });
});
