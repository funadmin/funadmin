import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import ElementPlus, { ElOption, ElSelect, ElSwitch } from 'element-plus';
import Workspace from './CategoryWorkspace.vue';
import { listButtonKeys } from '../../runtime/listButtonHost';
import type { FormLeftTreeConfiguration, FormListButtonLocation, FormListConfiguration } from '../../schema/types';

afterEach(() => { document.body.innerHTML = ''; });
const locationKeys: FormListButtonLocation[] = ['toolbar', 'row', 'categoryToolbar', 'categoryNode'];
const locations = locationKeys.map(key => ({ key, label: key, fields: ['id', 'title'], builtinKeys: listButtonKeys[key], resourceEnabled: true }));
const columns = [{ field: 'title', label: '标题' }, { field: 'status', label: '状态', options: [{ label: '待审核', value: 'pending' }, { label: '已审核', value: 'approved' }] }];
const categoryFields = [{ field: 'status', title: '状态' }, { field: 'level', title: '等级' }];
const sourceFields = [{ field: 'id', title: 'ID' }, { field: 'name', title: '名称' }, { field: 'parent_id', title: '父级' }];
const targetFields = [{ field: 'category_id', title: '分类' }, { field: 'code', title: 'code' }];
const tree = (patch: Partial<FormLeftTreeConfiguration> = {}): FormLeftTreeConfiguration => ({ enabled: true, source: { type: 'current' }, mapping: { valueField: 'id', labelField: 'name', targetField: 'category_id' }, actions: { create: true, addChild: true }, ...patch });
const render = (modelValue: FormListConfiguration = {}, extra: { left?: FormLeftTreeConfiguration; treePending?: boolean } = {}) => {
  const left = extra.left ?? modelValue.leftTree ?? { enabled: false, source: { type: 'current' as const }, mapping: { valueField: '', labelField: '', targetField: '' } };
  return mount(Workspace, { attachTo: document.body, props: { modelValue, left, categoryFields, sourceFields, targetFields, modules: [{ moduleId: 3, moduleCode: 'order-category', formKey: 'oc', primaryKey: { name: 'id', type: 'integer' }, fields: [] }], columns, locations, ...(extra.treePending === undefined ? {} : { treePending: extra.treePending }) }, global: { plugins: [ElementPlus] } });
};
const card = (wrapper: ReturnType<typeof render>, mode: string) => wrapper.get(`[data-category-mode="${mode}"]`);
const sections = (wrapper: ReturnType<typeof render>) => wrapper.findAll('[data-category-section]').map(item => item.attributes('data-category-section'));
const row = (wrapper: ReturnType<typeof render>, label: string) => wrapper.get(`[data-label="${label}"]`);

describe('分类管理工作区', () => {
  it('三张分类方式图卡互斥，选中态取自 leftTree / category', () => {
    const none = render();
    expect(none.findAll('[role="radio"][data-category-mode]').map(item => item.text())).toEqual([expect.stringContaining('不分类'), expect.stringContaining('顶部选项页签'), expect.stringContaining('左侧分类树')]);
    expect(card(none, 'none').attributes('aria-checked')).toBe('true');
    expect(none.findAll('[data-category-mode][aria-checked="true"]')).toHaveLength(1);
    expect(card(render({ category: { enabled: true, field: 'status' } }), 'tabs').attributes('aria-checked')).toBe('true');
    // 二者同时开启的历史数据沿用运行时优先级：分类树优先。
    const both = render({ category: { enabled: true }, leftTree: tree() });
    expect(card(both, 'tree').attributes('aria-checked')).toBe('true');
    expect(both.findAll('[data-category-mode][aria-checked="true"]')).toHaveLength(1);
  });
  it('点击图卡按原规则写回：页签→开启 category，树→开启 leftTree，不分类→全部关闭，重复点击不写回', async () => {
    const wrapper = render();
    await card(wrapper, 'none').trigger('click');
    expect(wrapper.emitted()).not.toHaveProperty('clear');
    await card(wrapper, 'tabs').trigger('click');
    expect(wrapper.emitted('category')?.at(-1)).toEqual([{ enabled: true }]);
    await card(wrapper, 'tree').trigger('click');
    expect(wrapper.emitted('updateLeft')?.at(-1)).toEqual([{ enabled: true }]);
    const enabled = render({ leftTree: tree() });
    await card(enabled, 'tree').trigger('click');
    expect(enabled.emitted('updateLeft')).toBeUndefined();
    await card(enabled, 'none').trigger('click');
    expect(enabled.emitted('clear')).toHaveLength(1);
  });
  it('只显示所选方式的设置：不分类无设置，页签只有分类绑定字段，分类树分四组', async () => {
    expect(sections(render())).toEqual([]);
    const tabs = render({ category: { enabled: true, field: 'status' } });
    expect(sections(tabs)).toEqual(['field']);
    const select = tabs.findComponent(ElSelect);
    expect(select.props('modelValue')).toBe('status');
    select.vm.$emit('change', 'level');
    expect(tabs.emitted('category')?.at(-1)).toEqual([{ field: 'level' }]);
    const treeMode = render({ leftTree: tree() });
    expect(sections(treeMode)).toEqual(['source', 'mapping', 'selection', 'actions']);
    for (const title of ['分类数据从哪来', '字段对应', '选择行为', '分类节点可执行的操作']) expect(treeMode.text()).toContain(title);
    expect(treeMode.text()).toContain('按钮与工具');
  });
  it('数据来源：切换来源、按需加载模块列表并选择模块', async () => {
    const wrapper = render({ leftTree: tree() });
    expect(wrapper.find('[data-label="业务模块"]').exists()).toBe(false);
    const source = wrapper.get('[data-category-section="source"]');
    expect(source.get('[role="radio"][aria-checked="true"]').text()).toBe('当前业务（自关联）');
    await source.findAll('[role="radio"]').find(item => item.text() === '其他已发布业务')!.trigger('click');
    expect(wrapper.emitted('changeSource')?.at(-1)).toEqual(['module']);
    await source.findAll('[role="radio"]').find(item => item.text() === '当前业务（自关联）')!.trigger('click');
    expect(wrapper.emitted('changeSource')).toHaveLength(1);
    const external = render({ leftTree: tree({ source: { type: 'module', module: 'order-category' } }) });
    const moduleSelect = row(external, '业务模块').findComponent(ElSelect);
    expect(moduleSelect.props('modelValue')).toBe('order-category');
    moduleSelect.vm.$emit('visible-change', true);
    expect(external.emitted('loadModules')).toHaveLength(1);
    moduleSelect.vm.$emit('change', 'order-category');
    expect(external.emitted('chooseModule')?.at(-1)).toEqual(['order-category']);
  });
  it('字段对应以「分类表字段 → 本表字段」展示，修改合并进 mapping', () => {
    const wrapper = render({ leftTree: tree({ mapping: { valueField: 'id', labelField: 'name', targetField: 'category_id' } }) });
    const mapping = wrapper.get('[data-category-section="mapping"]');
    for (const label of ['来源主键', '显示字段', '父级字段', '排序字段', '右表关联字段']) expect(mapping.text()).toContain(label);
    expect(mapping.get('[data-mapping-key-readonly]').text()).toBe('id');
    expect(mapping.text()).toContain('→');
    const parent = mapping.get('[data-mapping="parentField"]').findComponent(ElSelect);
    parent.vm.$emit('change', 'parent_id');
    expect(wrapper.emitted('updateLeft')?.at(-1)).toEqual([{ mapping: { valueField: 'id', labelField: 'name', targetField: 'category_id', parentField: 'parent_id' } }]);
    const target = mapping.get('[data-mapping="targetField"]').findComponent(ElSelect);
    expect(target.props('modelValue')).toBe('category_id');
    target.vm.$emit('change', 'other_id');
    expect(wrapper.emitted('updateLeft')?.at(-1)).toEqual([{ mapping: { valueField: 'id', labelField: 'name', targetField: 'other_id' } }]);
  });
  it('未选父级字段时「新增子节点」「包含后代」禁用且显示为关闭；选中后可用', async () => {
    const wrapper = render({ leftTree: tree({ selection: { includeDescendants: true } }) });
    const addChild = row(wrapper, '新增子节点').findComponent(ElSwitch);
    const descendants = row(wrapper, '选中父级时包含后代').findComponent(ElSwitch);
    expect(addChild.props('disabled')).toBe(true);
    expect(addChild.props('modelValue')).toBe(false);
    expect(descendants.props('disabled')).toBe(true);
    expect(row(wrapper, '新增根节点').findComponent(ElSwitch).props('modelValue')).toBe(true);
    await wrapper.setProps({ left: tree({ mapping: { valueField: 'id', labelField: 'name', targetField: 'category_id', parentField: 'parent_id' }, selection: { includeDescendants: true } }) });
    expect(row(wrapper, '新增子节点').findComponent(ElSwitch).props('disabled')).toBe(false);
    expect(row(wrapper, '新增子节点').findComponent(ElSwitch).props('modelValue')).toBe(true);
    expect(row(wrapper, '选中父级时包含后代').findComponent(ElSwitch).props('modelValue')).toBe(true);
    row(wrapper, '删除节点').findComponent(ElSwitch).vm.$emit('change', true);
    expect(wrapper.emitted('updateLeft')?.at(-1)).toEqual([{ actions: { create: true, addChild: true, delete: true } }]);
    row(wrapper, '选中父级时包含后代').findComponent(ElSwitch).vm.$emit('change', false);
    expect(wrapper.emitted('updateLeft')?.at(-1)).toEqual([{ selection: { includeDescendants: false } }]);
    const selection = wrapper.get('[data-category-section="selection"]');
    await selection.findAll('[role="radio"]').find(item => item.text() === '多选')!.trigger('click');
    expect(wrapper.emitted('updateLeft')?.at(-1)).toEqual([{ selection: { includeDescendants: true, mode: 'multiple' } }]);
  });
  it('右侧预览随分类方式切换：无分类、顶部页签（取字段选项）、左侧树（有父级时显示层级）', async () => {
    const wrapper = render();
    expect(wrapper.find('[data-preview-category]').exists()).toBe(false);
    expect(wrapper.find('[data-preview-tabs]').exists()).toBe(false);
    await wrapper.setProps({ modelValue: { category: { enabled: true, field: 'status' } } });
    expect(wrapper.get('[data-preview-tabs]').text()).toContain('全部');
    expect(wrapper.get('[data-preview-tabs]').text()).toContain('待审核');
    expect(wrapper.find('[data-preview-category]').exists()).toBe(false);
    await wrapper.setProps({ modelValue: { category: { enabled: true, field: 'level' } } });
    expect(wrapper.get('[data-preview-tabs]').findAll('span').length).toBeGreaterThan(1);
    await wrapper.setProps({ modelValue: { leftTree: tree() }, left: tree() });
    expect(wrapper.find('[data-preview-tabs]').exists()).toBe(false);
    const flat = wrapper.get('[data-preview-category]').findAll('.list-preview__node');
    expect(flat.length).toBeGreaterThan(1);
    expect(flat.every(node => node.attributes('style')?.includes('padding-left: 8px'))).toBe(true);
    const nested = tree({ mapping: { valueField: 'id', labelField: 'name', targetField: 'category_id', parentField: 'parent_id' } });
    await wrapper.setProps({ modelValue: { leftTree: nested }, left: nested });
    expect(wrapper.get('[data-preview-category]').findAll('.list-preview__node').some(node => !node.attributes('style')?.includes('padding-left: 8px'))).toBe(true);
    // 预览只读：不出现添加按钮入口。
    expect(wrapper.find('.preview-chip.is-add').exists()).toBe(false);
  });
  it('分类树待完成：已选分类树但必填映射为空时保持选中并列出缺少的映射，补全后不再显示', async () => {
    const pendingLeft = { enabled: false, source: { type: 'current' as const }, mapping: { valueField: 'id', labelField: '', targetField: '' } };
    const wrapper = render({}, { left: pendingLeft, treePending: true });
    expect(card(wrapper, 'tree').attributes('aria-checked')).toBe('true');
    expect(wrapper.findAll('[data-category-mode][aria-checked="true"]')).toHaveLength(1);
    expect(sections(wrapper)).toEqual(['source', 'mapping', 'selection', 'actions']);
    expect(card(wrapper, 'tree').find('[data-category-card-pending]').text()).toBe('待完成');
    const pending = wrapper.get('[data-category-pending]');
    expect(pending.text()).toContain('待完成');
    expect(pending.text()).toContain('显示字段');
    expect(pending.text()).toContain('右表关联字段');
    expect(pending.text()).not.toContain('来源主键');
    expect(pending.findAll('[data-missing-mapping]').map(item => item.attributes('data-missing-mapping'))).toEqual(['labelField', 'targetField']);
    // 映射仍按原规则写回
    wrapper.get('[data-mapping="labelField"]').findComponent(ElSelect).vm.$emit('change', 'name');
    expect(wrapper.emitted('updateLeft')?.at(-1)).toEqual([{ mapping: { valueField: 'id', labelField: 'name', targetField: '' } }]);
    // 待完成时再点分类树不写回；点不分类交给面板清除待完成状态
    await card(wrapper, 'tree').trigger('click');
    expect(wrapper.emitted('updateLeft')).toHaveLength(1);
    await card(wrapper, 'none').trigger('click');
    expect(wrapper.emitted('clear')).toHaveLength(1);
    // 已开启且映射完整：没有待完成提示
    const done = render({ leftTree: tree() });
    expect(done.find('[data-category-pending]').exists()).toBe(false);
    expect(card(done, 'tree').find('[data-category-card-pending]').exists()).toBe(false);
    // 历史数据：已开启但映射缺失时同样提示
    const legacy = render({ leftTree: tree({ mapping: { valueField: '', labelField: 'name', targetField: 'category_id' } }) });
    expect(legacy.get('[data-category-pending]').findAll('[data-missing-mapping]').map(item => item.attributes('data-missing-mapping'))).toEqual(['valueField']);
  });
  it('字段选项读取 {field,title}：标题与字段名不同时显示「标题 (字段)」，值为字段名', () => {
    const wrapper = render({ leftTree: tree() });
    const options = (ariaLabel: string) => wrapper.findAllComponents(ElSelect).find(select => select.attributes('aria-label') === ariaLabel || select.find(`[aria-label="${ariaLabel}"]`).exists())!
      .findAllComponents(ElOption).map(option => [option.props('label'), option.props('value')]);
    expect(options('右表关联字段')).toEqual([['分类 (category_id)', 'category_id'], ['code', 'code']]);
    expect(options('显示字段')).toEqual([['ID (id)', 'id'], ['名称 (name)', 'name'], ['父级 (parent_id)', 'parent_id']]);
    const tabs = render({ category: { enabled: true, field: 'status' } });
    expect(tabs.findComponent(ElSelect).findAllComponents(ElOption).map(option => option.props('value'))).toEqual(['status', 'level']);
  });
});
