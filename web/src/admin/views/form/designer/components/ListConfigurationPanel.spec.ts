import { defineComponent } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Panel from './ListConfigurationPanel.vue';
import { useDesigner } from '../../composables/useDesigner';
import ElementPlus from 'element-plus';
import { fieldNodes } from '../../schema/fieldNodes';
import type { FormSchemaNode } from '../../schema/types';

const api = vi.hoisted(() => ({ modules: vi.fn(), module: vi.fn(), meta: vi.fn(), sourceCandidates: vi.fn(), sourceMeta: vi.fn(), fieldCapabilities: vi.fn(), listActions: vi.fn(), designActionCatalog: vi.fn() }));
const safeSource = { moduleId: 1, moduleCode: 'allowed', formKey: 'allowed_form', primaryKey: { name: 'code', type: 'string' }, fields: [{ field: 'title', title: '标题' }] };
// 设计器字段节点（与 designer/index.vue 一致，经 fieldNodes() 展开后传入）。
const node = (field: string, title: string, type: string, columnType: string, extra: Partial<FormSchemaNode> = {}): FormSchemaNode => ({ id: `node_${field}`, kind: 'field', type, field, title, children: [], database: { columnType, relation: { type: 'none' } }, ...extra });
vi.mock('@/api/development/business', () => ({ businessDevelopmentApi: api }));
vi.mock('@/api/formData', () => ({ formDataApi: api }));
const box = defineComponent({ template: '<div><slot /></div>' });
const switchStub = defineComponent({ props: ['modelValue', 'disabled'], emits: ['change'], template: '<button :disabled="disabled" @click="$emit(\'change\', !modelValue)">{{ modelValue }}</button>' });
const item = defineComponent({ props: ['label'], template: '<section :data-label="label"><slot /></section>' });
const config = () => ({ category: { enabled: true, field: 'mention' }, leftTree: { enabled: true, source: { type: 'module' as const }, mapping: { valueField: '', labelField: '', targetField: 'category_id' }, actions: { addChild: true } } });
const render = (mode: 'all' | 'buttons' | 'categories' = 'all') => mount(Panel, { props: { modelValue: config(), fields: [], mode }, global: { plugins: [ElementPlus], stubs: { ElCard: box, ElForm: box, ElTabs: box, ElTabPane: box, ElFormItem: item, ElSwitch: switchStub, ElAlert: defineComponent({ props: ['title'], template: '<p>{{ title }}</p>' }), ElSelect: box, ElOption: true, ElRadioGroup: box, ElRadio: box } } });
beforeEach(() => { vi.clearAllMocks(); api.sourceCandidates.mockResolvedValue({ list: [safeSource], total: 1 }); api.sourceMeta.mockResolvedValue(safeSource); api.modules.mockResolvedValue({ list: [{ id: 1, code: 'allowed', name: '分类', lifecycle_status: 'published' }, { id: 2, code: 'denied', lifecycle_status: 'published' }], total: 2 }); api.module.mockImplementation(async (id: number) => ({ form: { form_key: id === 1 ? 'allowed' : 'denied' } })); api.meta.mockImplementation(async (key: string) => { if (key === 'denied') throw Error('拒绝'); return { primaryKey: { name: 'code' }, fields: [] }; }); });
describe('独立分类配置入口', () => {
  it('按实际入口加载权限目录并传给编辑器，失败不改写按钮', async () => {
    api.designActionCatalog.mockResolvedValue({ moduleId: 7, designOnly: true, executable: false, resources: { customer: { type: 'navigate', permission: 'view', capabilityVersion: 'v2', params: [], query: [] } }, actions: { row: { inspect: { permission: 'inspect', capabilityVersion: 'v3', parameters: [], parameterTypes: {}, locations: ['row'], targets: ['record'], effect: 'read', batch: false, requiresConfirmation: false, resultContract: 'json' } } } });
    const wrapper = render('buttons');
    await wrapper.setProps({ moduleId: 7, permissions: ['development:business:create-visual', 'development:business:create-from-database', 'development:business:validate-schema', 'development:business:save-schema', 'development:business:compile-schema', 'development:business:rollback-schema', 'development:business:adopt-resolved-baseline', 'development:business:save'] } as never);
    await flushPromises();
    expect(api.designActionCatalog).toHaveBeenCalledWith(7);
    expect(api.fieldCapabilities).not.toHaveBeenCalled();
    expect(api.listActions).not.toHaveBeenCalled();
    expect(wrapper.findComponent({ name: 'ButtonsWorkspace' }).props('resources')).toHaveProperty('customer');
    (wrapper.findComponent({ name: 'ButtonsWorkspace' }).vm as any).add('row'); await flushPromises();
    const editor = wrapper.findComponent({ name: 'ButtonInspector' });
    expect(editor.props('resources')).toHaveProperty('customer');
    const state = (editor.vm as any).$.setupState;
    state.changeAction('registered:inspect'); state.save();
    const saved = (wrapper.emitted('update')?.at(-1)?.[0] as any).buttons;
    expect(saved.row.at(-1).action).toEqual({ type: 'registered', key: 'inspect', capabilityVersion: 'v3' });
    await wrapper.setProps({ modelValue: { ...config(), buttons: saved } });
    const updates = wrapper.emitted('update')?.length;
    api.designActionCatalog.mockRejectedValueOnce(Error('离线'));
    await wrapper.setProps({ moduleId: 8 } as never); await flushPromises();
    expect(wrapper.emitted('update')?.length).toBe(updates);
    expect(wrapper.props('modelValue').buttons).toEqual(saved);
    expect(editor.props('catalogError')).toContain('保留');
    expect(wrapper.findComponent({ name: 'ButtonsWorkspace' }).props('catalogFailed')).toBe(true);
    wrapper.unmount();
  });
  it('右表允许选择真实 belongs_to 外键，但选项筛选仍排除关系字段', async () => {
    const wrapper = render();
    await wrapper.setProps({ fields: [node('category_id', '分类', 'relation', 'bigint', { database: { columnType: 'bigint', relation: { type: 'belongs_to', table: 'category' } } })] });
    const state = (wrapper.vm as any).$.setupState;
    expect(state.targetFields?.map((field: any) => field.field)).toEqual(['category_id']);
    expect(state.scalarFields).toEqual([]);
    wrapper.unmount();
  });
  it('右表关联字段读取字段节点：布局内的标量字段可选，敏感与多选字段排除，传给工作区的是 {field,title}', async () => {
    const nodes: FormSchemaNode[] = [
      { id: 'node_tabs', kind: 'layout', type: 'tabs', title: '页签', children: [node('code', '编码', 'input', 'varchar(32)')] },
      node('secret', '密钥', 'password', 'varchar(255)'),
      node('tags', '标签', 'select', 'varchar(255)', { props: { multiple: true } }),
      node('token', '令牌', 'input', 'varchar(64)', { props: { writeOnly: 1 } }),
      node('title', '标题', 'input', 'varchar(100)')
    ];
    const wrapper = render('categories');
    await wrapper.setProps({ fields: fieldNodes(nodes), modelValue: { leftTree: { ...config().leftTree, source: { type: 'current' as const } } } });
    const state = (wrapper.vm as any).$.setupState;
    expect(state.targetFields.map((field: FormSchemaNode) => field.field)).toEqual(['code', 'title']);
    const workspace = wrapper.findComponent({ name: 'CategoryWorkspace' });
    expect(workspace.props('targetFields')).toEqual([{ field: 'code', title: '编码' }, { field: 'title', title: '标题' }]);
    expect(workspace.props('sourceFields')).toEqual([{ field: 'code', title: '编码' }, { field: 'title', title: '标题' }]);
    wrapper.unmount();
  });
  it('分类字段、筛选字段、按钮字段与预览列读取节点的 dataSource / list / access', async () => {
    const nodes: FormSchemaNode[] = [
      node('status', '状态', 'select', 'varchar(20)', { dataSource: { kind: 'static', options: [{ label: '启用', value: 'on' }] }, list: { show: true, filter: 'eq' } }),
      node('level', '等级', 'select', 'int', { dataSource: { kind: 'dictionary' }, list: { filter: 'none' } }),
      node('owner', '负责人', 'input', 'varchar(50)', { access: { read: ['x:y:read'] }, list: { show: true, filter: 'like' } }),
      node('remote', '远程', 'select', 'varchar(20)', { dataSource: { kind: 'api' } })
    ];
    const wrapper = mount(Panel, { props: { modelValue: config(), fields: fieldNodes(nodes), mode: 'categories' }, global: { plugins: [ElementPlus], stubs: { ElCard: box, ElSwitch: switchStub, ElSelect: box, ElOption: true } } });
    const state = (wrapper.vm as any).$.setupState;
    expect(wrapper.findComponent({ name: 'CategoryWorkspace' }).props('categoryFields')).toEqual([{ field: 'status', title: '状态' }, { field: 'level', title: '等级' }]);
    expect(state.filterFields).toEqual(['status', 'owner']);
    // 带访问控制的字段不能被按钮参数或预览引用（与后端 ListButtonSchemaValidator 一致）。
    expect(state.buttonFields).toEqual(['id', 'status', 'level', 'remote']);
    expect(state.previewColumns).toEqual([
      { field: 'status', label: '状态', options: [{ label: '启用', value: 'on' }], numeric: false },
      { field: 'level', label: '等级', options: undefined, numeric: true },
      { field: 'remote', label: '远程', options: undefined, numeric: false }
    ]);
    wrapper.unmount();
  });
  it('页签行「刷新目录」调用原目录加载并更新目录规模', async () => {
    api.designActionCatalog.mockResolvedValue({ moduleId: 7, designOnly: true, executable: false, resources: {}, actions: { row: { inspect: { permission: 'inspect', capabilityVersion: 'v3', parameters: [], parameterTypes: {}, locations: ['row'], targets: ['record'], effect: 'read', batch: false, requiresConfirmation: false, resultContract: 'json' } } } });
    const wrapper = render('buttons');
    await wrapper.setProps({ moduleId: 7 } as never); await flushPromises();
    expect(api.designActionCatalog).toHaveBeenCalledTimes(1);
    const workspace = wrapper.findComponent({ name: 'ButtonsWorkspace' });
    expect(workspace.props('catalogCount')).toBe(1);
    await workspace.findAll('button').find(item => item.text() === '刷新目录')!.trigger('click'); await flushPromises();
    expect(api.designActionCatalog).toHaveBeenCalledTimes(2);
    wrapper.unmount();
  });
  it('配置面板提供四个共享按钮编辑器和工具开关', () => { const wrapper = render('buttons'); expect(wrapper.findAllComponents({ name: 'ListButtonEditor' })).toHaveLength(4); expect(wrapper.text()).toContain('通用工具'); wrapper.unmount(); });
  it('按钮与工具模式不再渲染列表展示配置外壳和说明，直接展示按钮编辑区', async () => {
    const wrapper = render();
    await wrapper.setProps({ mode: 'buttons' } as never);
    expect(wrapper.text()).not.toContain('列表展示配置');
    expect(wrapper.text()).not.toContain('独立分类请选择');
    expect(wrapper.find('.list-panel--buttons-only').exists()).toBe(true);
    expect(wrapper.findAllComponents({ name: 'ListButtonEditor' })).toHaveLength(4);
    expect(wrapper.text()).toContain('通用工具');
    wrapper.unmount();
  });
  it('明确区分可管理分类与选项筛选：以互斥的三张分类方式图卡呈现', () => { const wrapper = render('categories'); const cards = wrapper.findAll('[role="radio"][data-category-mode]'); expect(cards.map(item => item.attributes('data-category-mode'))).toEqual(['none', 'tabs', 'tree']); expect(wrapper.text()).toContain('顶部选项页签'); expect(wrapper.text()).toContain('左侧分类树'); expect(cards.filter(item => item.attributes('aria-checked') === 'true')).toHaveLength(1); wrapper.unmount(); });
  it('分类方式图卡沿用原互斥规则写回：页签关闭分类树、分类树关闭页签、不分类只关闭已开启的一方', async () => {
    const wrapper = render('categories');
    await wrapper.get('[data-category-mode="tabs"]').trigger('click');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toMatchObject({ category: { enabled: true, field: 'mention' }, leftTree: { enabled: false, mapping: { targetField: 'category_id' } } });
    await wrapper.setProps({ modelValue: { ...config(), leftTree: { ...config().leftTree, enabled: false } } });
    await wrapper.get('[data-category-mode="tree"]').trigger('click');
    // 来源主键/显示字段为空：不写入 enabled: true，界面保持分类树选中（待完成）。
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toMatchObject({ leftTree: { enabled: false, actions: { addChild: false } }, category: { enabled: false, field: 'mention' } });
    expect(wrapper.get('[data-category-mode="tree"]').attributes('aria-checked')).toBe('true');
    await wrapper.setProps({ modelValue: config() });
    // 与原先关闭「可管理分类」开关写回一致：无父级字段时 addChild / includeDescendants 被清为 false。
    const closedTree = { ...config().leftTree, enabled: false, actions: { addChild: false }, selection: { includeDescendants: false } };
    await wrapper.get('[data-category-mode="none"]').trigger('click');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ category: { enabled: false, field: 'mention' }, leftTree: closedTree });
    (wrapper.vm as any).$.setupState.updateLeft({ enabled: false });
    expect((wrapper.emitted('update')?.at(-1)?.[0] as any).leftTree).toEqual(closedTree);
    await wrapper.setProps({ modelValue: { leftTree: config().leftTree } });
    await wrapper.get('[data-category-mode="none"]').trigger('click');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ leftTree: closedTree });
    wrapper.unmount();
  });
  it('没有父级字段时禁用新增子节点，并在更新时清除旧开关', async () => { const wrapper = render(); expect(wrapper.get('[data-label="新增子节点"] button').attributes('disabled')).toBeDefined(); const state = (wrapper.vm as any).$.setupState; state.updateLeft({ enabled: true }); expect(wrapper.emitted('update')?.at(-1)?.[0]).toMatchObject({ leftTree: { actions: { addChild: false } }, category: { enabled: false } }); wrapper.unmount(); });
  it('开启选项筛选会关闭可管理分类，保留原映射', () => { const wrapper = render(); (wrapper.vm as any).$.setupState.category({ enabled: true }); expect(wrapper.emitted('update')?.at(-1)?.[0]).toMatchObject({ leftTree: { enabled: false, mapping: { targetField: 'category_id' } } }); wrapper.unmount(); });
  it('候选与选择只使用专用安全元数据，绝不请求完整模块详情或运行态 meta', async () => {
    const wrapper = render();
    const state = (wrapper.vm as any).$.setupState;
    await state.loadModules();
    await flushPromises();
    expect(api.module).not.toHaveBeenCalled();
    expect(api.modules).not.toHaveBeenCalled();
    expect(api.meta).not.toHaveBeenCalled();
    expect(api.sourceCandidates).toHaveBeenCalledOnce();
    expect(state.modules.map((value: any) => value.moduleCode)).toEqual(['allowed']);
    await state.chooseModule('allowed');
    expect(api.sourceMeta).toHaveBeenCalledWith('allowed_form');
    expect(api.module).not.toHaveBeenCalled();
    expect(state.externalFields).toEqual([{ field: 'code', title: '来源主键' }, { field: 'title', title: '标题' }]);
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toMatchObject({ leftTree: { source: { type: 'module', module: 'allowed' }, mapping: { valueField: 'code', targetField: 'category_id' } } });
    wrapper.unmount();
  });
  it('恢复已保存来源也不读取草稿，撤销授权时清空字段但不改写配置', async () => {
    const wrapper = render();
    await wrapper.setProps({ modelValue: { ...config(), leftTree: { ...config().leftTree, source: { type: 'module', module: 'allowed' } } } });
    await flushPromises();
    expect(api.sourceMeta).toHaveBeenCalledWith('allowed_form');
    expect(api.module).not.toHaveBeenCalled();
    expect(wrapper.emitted('update')).toBeUndefined();
    api.sourceMeta.mockRejectedValueOnce(Error('拒绝'));
    await (wrapper.vm as any).$.setupState.chooseModule('allowed');
    expect((wrapper.vm as any).$.setupState.externalFields).toEqual([]);
    expect(wrapper.emitted('update')).toBeUndefined();
    wrapper.unmount();
  });
  describe('分类树待完成：必填映射为空时不写入 enabled: true', () => {
    const fields = [
      node('sort', '排序', 'number', 'int'),
      node('title', '标题', 'input', 'varchar(100)'),
      node('remark', '备注', 'textarea', 'text'),
      node('category_id', '分类', 'number', 'bigint')
    ];
    const empty = { enabled: false, source: { type: 'current' as const }, mapping: { valueField: '', labelField: '', targetField: '' } };
    const mountPanel = (modelValue: Record<string, unknown>) => mount(Panel, { props: { modelValue, fields, mode: 'categories' }, global: { plugins: [ElementPlus], stubs: { ElCard: box, ElSwitch: switchStub, ElSelect: box, ElOption: true } } });
    const lastUpdate = (wrapper: ReturnType<typeof mountPanel>) => wrapper.emitted('update')?.at(-1)?.[0] as any;
    const checked = (wrapper: ReturnType<typeof mountPanel>) => wrapper.findAll('[data-category-mode][aria-checked="true"]').map(item => item.attributes('data-category-mode'));
    const missing = (wrapper: ReturnType<typeof mountPanel>) => wrapper.findAll('[data-category-pending] [data-missing-mapping]').map(item => item.attributes('data-missing-mapping'));

    it('选择分类树（当前业务）预填主键与首个文本字段，缺右表关联字段时写回 enabled: false 并显示待完成；补全后一并写入 enabled: true', async () => {
      const wrapper = mountPanel({ category: { enabled: true, field: 'status' }, leftTree: empty });
      await wrapper.get('[data-category-mode="tree"]').trigger('click');
      expect(lastUpdate(wrapper)).toMatchObject({ leftTree: { enabled: false, source: { type: 'current' }, mapping: { valueField: 'id', labelField: 'title', targetField: '' } }, category: { enabled: false, field: 'status' } });
      await wrapper.setProps({ modelValue: lastUpdate(wrapper) });
      expect(checked(wrapper)).toEqual(['tree']);
      expect(missing(wrapper)).toEqual(['targetField']);
      (wrapper.vm as any).$.setupState.updateLeft({ mapping: { ...lastUpdate(wrapper).leftTree.mapping, targetField: 'category_id' } });
      expect(lastUpdate(wrapper).leftTree).toMatchObject({ enabled: true, mapping: { valueField: 'id', labelField: 'title', targetField: 'category_id' } });
      await wrapper.setProps({ modelValue: lastUpdate(wrapper) });
      expect(checked(wrapper)).toEqual(['tree']);
      expect(wrapper.find('[data-category-pending]').exists()).toBe(false);
      wrapper.unmount();
    });

    it('已有映射不被预填覆盖；其他已发布业务来源不预填', async () => {
      const wrapper = mountPanel({ leftTree: { ...empty, mapping: { valueField: '', labelField: 'remark', targetField: 'category_id' } } });
      await wrapper.get('[data-category-mode="tree"]').trigger('click');
      expect(lastUpdate(wrapper).leftTree).toMatchObject({ enabled: true, mapping: { valueField: 'id', labelField: 'remark', targetField: 'category_id' } });
      const external = mountPanel({ leftTree: { ...empty, source: { type: 'module' as const } } });
      await external.get('[data-category-mode="tree"]').trigger('click');
      expect(lastUpdate(external).leftTree).toMatchObject({ enabled: false, mapping: { valueField: '', labelField: '', targetField: '' } });
      expect(checked(external)).toEqual(['tree']);
      wrapper.unmount(); external.unmount();
    });

    it('待完成时选择不分类或顶部选项页签清除待完成状态', async () => {
      const wrapper = mountPanel({ leftTree: empty });
      await wrapper.get('[data-category-mode="tree"]').trigger('click');
      await wrapper.setProps({ modelValue: lastUpdate(wrapper) });
      const updates = wrapper.emitted('update')!.length;
      await wrapper.get('[data-category-mode="none"]').trigger('click');
      // 已保存配置中两者都未开启：只清除界面状态，不写回
      expect(wrapper.emitted('update')!.length).toBe(updates);
      expect(checked(wrapper)).toEqual(['none']);
      expect(wrapper.find('[data-category-pending]').exists()).toBe(false);
      await wrapper.get('[data-category-mode="tree"]').trigger('click');
      expect(checked(wrapper)).toEqual(['tree']);
      await wrapper.get('[data-category-mode="tabs"]').trigger('click');
      expect(lastUpdate(wrapper)).toMatchObject({ category: { enabled: true }, leftTree: { enabled: false } });
      await wrapper.setProps({ modelValue: lastUpdate(wrapper) });
      expect(checked(wrapper)).toEqual(['tabs']);
      wrapper.unmount();
    });

    it('已开启的分类树清空必填映射时写回 enabled: false，界面保持分类树并显示待完成', async () => {
      const complete = { ...empty, enabled: true, mapping: { valueField: 'id', labelField: 'title', targetField: 'category_id' } };
      const wrapper = mountPanel({ leftTree: complete });
      (wrapper.vm as any).$.setupState.updateLeft({ mapping: { ...complete.mapping, labelField: '' } });
      expect(lastUpdate(wrapper).leftTree).toMatchObject({ enabled: false, mapping: { labelField: '' } });
      await wrapper.setProps({ modelValue: lastUpdate(wrapper) });
      expect(checked(wrapper)).toEqual(['tree']);
      expect(missing(wrapper)).toEqual(['labelField']);
      wrapper.unmount();
    });

    it('外部把未补全的已开启分类树改为关闭（保存前兜底）时保留分类树选中与待完成；本面板选择不分类则不保留', async () => {
      const stale = { ...empty, enabled: true, mapping: { valueField: 'id', labelField: '', targetField: '' } };
      const wrapper = mountPanel({ leftTree: stale });
      expect(checked(wrapper)).toEqual(['tree']);
      await wrapper.setProps({ modelValue: { leftTree: { ...stale, enabled: false } } });
      expect(checked(wrapper)).toEqual(['tree']);
      expect(missing(wrapper)).toEqual(['labelField', 'targetField']);
      expect(wrapper.emitted('update')).toBeUndefined();
      wrapper.unmount();

      const cleared = mountPanel({ leftTree: stale });
      await cleared.get('[data-category-mode="none"]').trigger('click');
      expect(lastUpdate(cleared).leftTree).toMatchObject({ enabled: false });
      await cleared.setProps({ modelValue: lastUpdate(cleared) });
      expect(checked(cleared)).toEqual(['none']);
      expect(cleared.find('[data-category-pending]').exists()).toBe(false);
      cleared.unmount();
    });
  });
});
describe('自定义动作写回', () => {
  it('按表单标识推导 entity、插件目标屏蔽入口；删除写回 undefined 并合并按钮，设计器不保留空数组', async () => {
    api.designActionCatalog.mockResolvedValue({ moduleId: 7, designOnly: true, executable: false, resources: {}, actions: {} });
    const action = { key: 'approve', label: '审批', locations: ['row' as const], batch: false, requiresConfirmation: true, effect: 'write' as const, parameters: [] };
    const toolbar = [{ id: 't', label: 'T', action: { type: 'refresh' as const } }];
    const wrapper = mount(Panel, { props: { modelValue: { customActions: [action], buttons: { toolbar, row: [] } }, fields: [], mode: 'buttons', formKey: 'fun_order' }, global: { plugins: [ElementPlus] } });
    const workspace = wrapper.findComponent({ name: 'ButtonsWorkspace' });
    expect(workspace.props('customActionEntity')).toBe('fun-order');
    expect(workspace.props('customActionsBlocked')).toBeFalsy();
    workspace.vm.$emit('updateCustomActions', undefined, { row: [] });
    const patch = wrapper.emitted('update')!.at(-1)![0] as Record<string, unknown>;
    expect(patch).toEqual({ customActions: undefined, buttons: { toolbar, row: [] } });
    const store = useDesigner();
    store.updateList({ customActions: [action] });
    expect(store.schemaDocument.value.list?.customActions).toEqual([action]);
    store.updateList(patch);
    expect(store.schemaDocument.value.list).not.toHaveProperty('customActions');
    await wrapper.setProps({ pluginTarget: true });
    expect(workspace.props('customActionsBlocked')).toBe(true);
    wrapper.unmount();
  });
});
