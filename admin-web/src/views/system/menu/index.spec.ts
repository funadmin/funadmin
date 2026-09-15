import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import ElementPlus, { ElTable, ElTableColumn } from 'element-plus';
import { describe, expect, it } from 'vitest';

const menuView = readFileSync(resolve(process.cwd(), 'src/views/system/menu/index.vue'), 'utf8');
const dragColumnIndex = menuView.indexOf('<el-table-column label="" width="52"');
const nameColumnIndex = menuView.indexOf('<el-table-column prop="name" label="名称"');
const dataColumns = [
  h(ElTableColumn, { label: '', width: 52 }, {
    default: ({ row }: { row: { id: number } }) => h('span', String(row.id)),
  }),
  h(ElTableColumn, { prop: 'name', label: '名称' }),
];
if (nameColumnIndex < dragColumnIndex) dataColumns.reverse();

const MenuTableContract = defineComponent({
  setup() {
    const rows = [{ id: 1, name: '系统管理', children: [{ id: 2, name: '菜单管理' }] }];
    return () => h(ElTable, {
      data: rows,
      rowKey: 'id',
      treeProps: { children: 'children' },
      defaultExpandAll: true,
    }, {
      default: () => [h(ElTableColumn, { type: 'selection', width: 48 }), ...dataColumns],
    });
  },
});

describe('系统菜单 Element Plus 树表契约', () => {
  it('名称列作为首个普通列承载树层级', async () => {
    const wrapper = mount(MenuTableContract, {
      attachTo: document.body,
      global: { plugins: [ElementPlus] },
    });
    await nextTick();

    expect(nameColumnIndex).toBeLessThan(dragColumnIndex);
    expect(wrapper.findAll('.el-table__body tbody tr')).toHaveLength(2);

    wrapper.unmount();
  });
});
