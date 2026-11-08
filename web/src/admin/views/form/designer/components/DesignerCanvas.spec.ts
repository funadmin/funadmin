import { mount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ElementPlus from 'element-plus';
import { createI18n } from 'vue-i18n';
import zhCN from '@/locales/zh-CN';
import { useDesigner, type DesignerStore } from '../../composables/useDesigner';
import DesignerCanvas from './DesignerCanvas.vue';

vi.mock('sortablejs', () => ({ default: { create: () => ({ destroy() {} }) } }));
vi.mock('@/api/system/dict', () => ({ dictApi: { options: vi.fn(async () => []), batch: vi.fn() }, dictTypeApi: {}, dictItemApi: {} }));

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
let wrapper: VueWrapper;
let store: DesignerStore;
const render = () => {
  wrapper = mount(DesignerCanvas, { props: { nodes: store.nodes.value, store }, global: { plugins: [ElementPlus, i18n], stubs: { FormControlRenderer: { template: '<div class="control-stub" />' } } } });
  return wrapper;
};
const node = (id: string) => wrapper.get(`[data-node-id="${id}"]`);
const op = (id: string, name: string) => node(id).get(`[data-node-op="${name}"]`);

beforeEach(() => { store = useDesigner(); });
afterEach(() => wrapper?.unmount());

describe('设计画布节点', () => {
  it('只有选中字段显示字段徽标与上移/下移/复制/删除浮动操作', async () => {
    const a = store.addNode('input');
    const b = store.addNode('select');
    render();
    await nextTick();
    expect(node(a.id).find('[data-node-ops]').exists()).toBe(false);
    expect(node(b.id).get('[data-node-badge]').text()).toBe(`${b.field} · 下拉选择`);
    expect(node(b.id).findAll('[data-node-op]').map((item) => item.attributes('data-node-op'))).toEqual(['up', 'down', 'duplicate', 'remove']);
    expect(op(b.id, 'down').attributes('disabled')).toBeDefined();
    expect(op(b.id, 'up').attributes('disabled')).toBeUndefined();
    await node(a.id).trigger('click');
    expect(store.selectedNodeId.value).toBe(a.id);
    expect(node(b.id).find('[data-node-ops]').exists()).toBe(false);
    expect(op(a.id, 'up').attributes('disabled')).toBeDefined();
  });

  it('浮动操作复用 store 的移动、复制与删除', async () => {
    const a = store.addNode('input');
    const b = store.addNode('select');
    render();
    await nextTick();
    await op(b.id, 'up').trigger('click');
    expect(store.nodes.value.map((item) => item.id)).toEqual([b.id, a.id]);
    await wrapper.setProps({ nodes: store.nodes.value });
    await op(b.id, 'down').trigger('click');
    expect(store.nodes.value.map((item) => item.id)).toEqual([a.id, b.id]);
    await wrapper.setProps({ nodes: store.nodes.value });
    await op(b.id, 'duplicate').trigger('click');
    expect(store.nodes.value).toHaveLength(3);
    expect(store.selectedNodeId.value).toBe(store.nodes.value[2].id);
    await wrapper.setProps({ nodes: store.nodes.value });
    const copy = store.nodes.value[2].id;
    await op(copy, 'remove').trigger('click');
    expect(store.nodes.value.map((item) => item.id)).toEqual([a.id, b.id]);
  });

  it('字段显示标签与必填标记，容器显示分组标题', async () => {
    const group = store.addNode('group');
    store.addNode('input', group.id);
    store.updateField({ label: '订单号', form_required: 1 });
    render();
    await nextTick();
    expect(node(group.id).get('.designer-group-title').text()).toContain(group.title);
    const label = wrapper.get('.designer-field-label');
    expect(label.text()).toBe('订单号');
    expect(label.classes()).toContain('is-required');
  });
});
