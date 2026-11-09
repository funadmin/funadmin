import { mount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ElementPlus from 'element-plus';
import { createI18n } from 'vue-i18n';
import zhCN from '@/locales/zh-CN';
import { CONTROL_REGISTRY } from '../../registry';
import { useDesigner, type DesignerStore } from '../../composables/useDesigner';
import DesignerLeftPanel from './DesignerLeftPanel.vue';

// 捕获 Sortable.create 的容器与配置，用来模拟拖拽结束事件。
const sortables = vi.hoisted(() => [] as Array<{ el: HTMLElement; options: Record<string, any>; disabled: boolean }>);
vi.mock('sortablejs', () => ({
  default: {
    create: (el: HTMLElement, options: Record<string, any>) => {
      const entry = { el, options, disabled: Boolean(options.disabled) };
      sortables.push(entry);
      return { destroy() { const index = sortables.indexOf(entry); if (index >= 0) sortables.splice(index, 1); }, option(name: string, value: unknown) { if (name === 'disabled') entry.disabled = Boolean(value); } };
    }
  }
}));

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
let wrapper: VueWrapper;
let store: DesignerStore;
const render = () => {
  wrapper = mount(DesignerLeftPanel, { attachTo: document.body, props: { store, controls: CONTROL_REGISTRY }, global: { plugins: [ElementPlus, i18n] } });
  return wrapper;
};
const groupOf = (title: string) => wrapper.findAll('.palette-group').find((group) => group.get('.palette-group-name').text() === title)!;
const outlineSortable = (parentId = '') => sortables.find((entry) => entry.el.classList.contains('schema-node-tree') && (entry.el.dataset.parentId ?? '') === parentId)!;

beforeEach(() => {
  sortables.length = 0;
  store = useDesigner();
});
afterEach(() => { wrapper?.unmount(); document.body.innerHTML = ''; });

describe('设计器左栏：控件', () => {
  it('默认显示控件页签，紧凑两列分组并带分组计数，每个分组网格都是拖拽源', async () => {
    render();
    await nextTick();
    expect(wrapper.get('[data-left-tab="controls"]').attributes('aria-selected')).toBe('true');
    expect(wrapper.get('[data-left-tab="outline"]').attributes('aria-selected')).toBe('false');
    const basic = groupOf('基础控件');
    const basicCount = CONTROL_REGISTRY.filter((control) => control.group === '基础控件').length;
    expect(basic.get('.palette-group-count').text()).toBe(String(basicCount));
    expect(basic.findAll('.palette-item')).toHaveLength(basicCount);
    const grids = wrapper.findAll('.palette-group-grid').map((grid) => grid.element);
    expect(sortables.filter((entry) => grids.includes(entry.el))).toHaveLength(grids.length);
    expect(sortables.find((entry) => entry.el === grids[0])!.options.group).toMatchObject({ name: 'form-designer', pull: 'clone', put: false });
  });

  it('搜索按控件名称过滤并更新分组计数，无结果时提示', async () => {
    render();
    await wrapper.get('input[data-testid="palette-search"]').setValue('日期');
    const items = wrapper.findAll('.palette-item');
    expect(items.length).toBeGreaterThan(0);
    expect(items.every((item) => item.text().includes('日期'))).toBe(true);
    expect(wrapper.findAll('.palette-group-name').map((item) => item.text())).toEqual(['日期时间']);
    expect(groupOf('日期时间').get('.palette-group-count').text()).toBe(String(items.length));
    await wrapper.get('input[data-testid="palette-search"]').setValue('不存在的控件');
    expect(wrapper.findAll('.palette-item')).toHaveLength(0);
    expect(wrapper.text()).toContain('没有匹配的控件');
  });

  it('点击或回车控件追加到选中节点之后（未选中时追加到末尾）', async () => {
    render();
    store.addNode('input');
    store.addNode('select');
    const first = store.nodes.value[0];
    store.selectNode(first.id);
    await nextTick();
    await wrapper.get('.palette-item[data-type="textarea"]').trigger('click');
    expect(store.nodes.value.map((node) => node.type)).toEqual(['input', 'textarea', 'select']);
    expect(wrapper.emitted('added')).toHaveLength(1);
    store.selectNode(null);
    await wrapper.get('.palette-item[data-type="number"]').trigger('keydown', { key: 'Enter' });
    expect(store.nodes.value.at(-1)!.type).toBe('number');
  });

  it('选中容器内字段时追加到容器内该字段之后', async () => {
    render();
    const group = store.addNode('group');
    const inner = store.addNode('input', group.id);
    store.addNode('select', group.id);
    store.selectNode(inner.id);
    await nextTick();
    await wrapper.get('.palette-item[data-type="date"]').trigger('click');
    expect(store.findNode(group.id)!.children.map((node) => node.type)).toEqual(['input', 'date', 'select']);
  });
});

describe('设计器左栏：大纲', () => {
  const openOutline = async () => { await wrapper.get('[data-left-tab="outline"]').trigger('click'); };

  it('大纲为常驻页签，显示节点数，点击节点选中画布字段', async () => {
    store.addNode('input');
    store.addNode('group');
    store.selectNode(null);
    render();
    expect(wrapper.get('[data-outline-count]').text()).toBe('2');
    expect(wrapper.find('.schema-node-tree').isVisible()).toBe(false);
    await openOutline();
    expect(wrapper.get('[data-left-tab="outline"]').attributes('aria-selected')).toBe('true');
    expect(wrapper.get('.schema-node-tree').isVisible()).toBe(true);
    const rows = wrapper.findAll('.schema-tree-row');
    await rows[0].trigger('click');
    expect(store.selectedNodeId.value).toBe(store.nodes.value[0].id);
    expect(rows[0].classes()).toContain('is-selected');
  });

  it('「添加布局分组」追加分组容器', async () => {
    render();
    await openOutline();
    await wrapper.get('[data-action="add-layout-group"]').trigger('click');
    expect(store.nodes.value.map((node) => node.type)).toEqual(['group']);
  });

  it('搜索字段过滤大纲（保留匹配节点的祖先），并在搜索期间禁用拖拽', async () => {
    const group = store.addNode('group');
    const inner = store.addNode('input', group.id);
    store.updateNode({ title: '订单号' });
    store.addNode('select');
    render();
    await openOutline();
    expect(outlineSortable().disabled).toBe(false);
    await wrapper.get('input[data-testid="outline-search"]').setValue('订单');
    const titles = wrapper.findAll('.schema-tree-title').map((item) => item.text());
    expect(titles).toEqual([group.title, '订单号']);
    expect(wrapper.find(`.schema-tree-node[data-node-id="${inner.id}"]`).exists()).toBe(true);
    expect(outlineSortable().disabled).toBe(true);
    await wrapper.get('input[data-testid="outline-search"]').setValue('');
    expect(outlineSortable().disabled).toBe(false);
  });

  it('拖拽大纲节点调整顺序与层级，调用 store.moveNode 并还原 DOM', async () => {
    const a = store.addNode('input');
    const b = store.addNode('select');
    const group = store.addNode('group');
    render();
    await openOutline();
    const root = outlineSortable();
    // 同层下移：A 从 0 拖到最后（最终位置 2）
    const itemA = root.el.querySelector<HTMLElement>(`.schema-tree-node[data-node-id="${a.id}"]`)!;
    root.el.appendChild(itemA);
    root.options.onEnd({ item: itemA, from: root.el, to: root.el, oldIndex: 0, newIndex: 2, oldDraggableIndex: 0, newDraggableIndex: 2 });
    expect(store.nodes.value.map((node) => node.id)).toEqual([b.id, group.id, a.id]);
    await nextTick();
    // 改层级：B 拖进分组
    const inner = outlineSortable(group.id);
    expect(inner, '容器即使为空也提供放置区').toBeDefined();
    const itemB = root.el.querySelector<HTMLElement>(`.schema-tree-node[data-node-id="${b.id}"]`)!;
    inner.el.appendChild(itemB);
    root.options.onEnd({ item: itemB, from: root.el, to: inner.el, oldIndex: 0, newIndex: 0, oldDraggableIndex: 0, newDraggableIndex: 0 });
    expect(store.nodes.value.map((node) => node.id)).toEqual([group.id, a.id]);
    expect(store.findNode(group.id)!.children.map((node) => node.id)).toEqual([b.id]);
    await nextTick();
    expect(Array.from(root.el.children).map((item) => (item as HTMLElement).dataset.nodeId)).toEqual([group.id, a.id]);
  });

  it('不允许把容器拖入自身后代', async () => {
    const group = store.addNode('group');
    const nested = store.addNode('group', group.id);
    render();
    await openOutline();
    const root = outlineSortable();
    const inner = outlineSortable(nested.id);
    expect(root.options.onMove({ dragged: root.el.querySelector(`[data-node-id="${group.id}"]`), to: inner.el })).toBe(false);
  });
});
