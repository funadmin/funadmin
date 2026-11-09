import { defineComponent, h, inject, provide } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { BusinessModule, BusinessModuleDetail } from '@/api/development/business';
import type { FormDefinition } from '@/api/form';
import type { FormSchemaDocument, FormSchemaNode } from '@/views/form/schema/types';

const mocks = vi.hoisted(() => ({
  permissions: ['*'] as string[],
  module: vi.fn(),
  saveSchema: vi.fn(),
  savePublishConfig: vi.fn(),
  databaseTables: vi.fn(),
  databaseTableSchema: vi.fn(),
  menuTree: vi.fn(),
  dictTypes: vi.fn(),
  push: vi.fn(),
  message: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
  confirm: vi.fn()
}));

vi.mock('@/api/development/business', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/development/business')>();
  return {
    ...actual,
    businessDevelopmentApi: {
      ...actual.businessDevelopmentApi,
      module: mocks.module,
      saveSchema: mocks.saveSchema,
      savePublishConfig: mocks.savePublishConfig,
      databaseTables: mocks.databaseTables,
      databaseTableSchema: mocks.databaseTableSchema
    }
  };
});
vi.mock('@/api/system/menu', () => ({ menuApi: { tree: mocks.menuTree } }));
vi.mock('@/api/system/dict', () => ({ dictTypeApi: { list: mocks.dictTypes }, dictApi: { options: vi.fn() } }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ get permissions() { return mocks.permissions; } }) }));
vi.mock('vue-router', () => ({ useRouter: () => ({ push: mocks.push }) }));
vi.mock('element-plus', async (importOriginal) => {
  const actual = await importOriginal<typeof import('element-plus')>();
  return { ...actual, ElMessage: mocks.message, ElMessageBox: { confirm: mocks.confirm } };
});

import EditGenerationDialog from './EditGenerationDialog.vue';

const node = (id: string, type: string, name: string, columnType: string, extra: Partial<FormSchemaNode> = {}): FormSchemaNode => ({
  id, kind: 'field', type, field: name, title: name, props: {}, children: [], validation: [], dataSource: null,
  conditions: [], events: {}, hidden: false,
  database: { columnType, nullable: true, comment: name, relation: { type: 'none' } },
  list: { show: false, filter: '', sort: false, width: 0 },
  ...extra
});

const makeDoc = (): FormSchemaDocument => ({
  schemaVersion: 2, key: 'shop_goods', title: '商品', list: {},
  nodes: [
    node('n_title', 'input', 'title', 'varchar(120)', { list: { show: true, filter: 'like', sort: false, width: 160 } }),
    node('n_parent', 'number', 'parent_id', 'int'),
    node('n_owner', 'number', 'member_id', 'bigint'),
    node('n_status', 'input', 'status', 'tinyint'),
    node('n_created', 'input', 'created_at', 'datetime'),
    node('n_category', 'relation', 'category_id', 'bigint', {
      database: { columnType: 'bigint', relation: { type: 'belongs_to', table: 'shop_category', valueField: 'id', labelField: 'name' } }
    }),
    node('n_items', 'subform', 'items', '', { database: { relation: { type: 'has_many', table: 'shop_sku', valueField: 'goods_id' } } })
  ],
  relations: [{ type: 'hasOne', name: 'detail', table: 'shop_goods_ext', localField: 'id', foreignField: 'goods_id' }]
});

const coreModule: BusinessModule = {
  id: 7, code: 'shop_goods', name: '商品', origin: 'visual', lifecycle_status: 'draft', table_name: 'fa_shop_goods', form_id: 3,
  metadata: { target: { type: 'core', pluginCode: null, scope: 'admin', locked: true } }
};

const makeDetail = (): BusinessModuleDetail => ({
  module: { ...coreModule },
  form: {
    id: 3, form_key: 'shop_goods', name: '商品', table_name: 'fa_shop_goods', connection: 'mysql', source_type: 'created',
    status: 1, remark: '', sort_order: 0, schema_hash: 'hash-1', schema_document: makeDoc() as unknown as NonNullable<FormDefinition['schema_document']>, publish_config: { sortOrder: 10 }
  }
});

const conflictError = { code: 409, msg: '冲突', data: { error: { code: 'FORM_SCHEMA_CONFLICT', requestId: 'r1', retryable: true, details: {} } } };

const RadioGroup = defineComponent({
  inheritAttrs: false,
  props: ['modelValue', 'disabled'],
  emits: ['update:modelValue', 'change'],
  setup(props, { emit, slots, attrs }) {
    provide('stubRadioGroup', { current: () => props.modelValue, select: (value: unknown) => { emit('update:modelValue', value); emit('change', value); } });
    return () => h('div', { ...attrs, 'data-radio-group': '', 'data-value': String(props.modelValue) }, slots.default?.());
  }
});
const Radio = defineComponent({
  props: ['value', 'disabled'],
  setup(props, { slots }) {
    const group = inject<{ current: () => unknown; select: (value: unknown) => void }>('stubRadioGroup');
    return () => h('button', { type: 'button', 'data-radio': String(props.value), 'aria-pressed': String(group?.current() === props.value), disabled: props.disabled, onClick: () => group?.select(props.value) }, slots.default?.());
  }
});
const checkbox = (role: string) => defineComponent({
  inheritAttrs: false,
  props: ['modelValue', 'disabled'],
  emits: ['update:modelValue', 'change'],
  template: `<label :data-${role}="''"><input type="checkbox" v-bind="$attrs" :checked="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.checked); $emit('change', $event.target.checked)" /><slot /></label>`
});

const stubs = {
  ElDialog: defineComponent({
    props: ['modelValue', 'fullscreen', 'width', 'title'],
    emits: ['update:modelValue'],
    template: '<aside v-if="modelValue" role="dialog" :data-fullscreen="fullscreen ? \'true\' : \'false\'"><header>{{ title }}<slot name="header" /></header><slot /><footer><slot name="footer" /></footer></aside>'
  }),
  ElButton: defineComponent({
    inheritAttrs: false,
    props: ['disabled', 'loading', 'type', 'icon', 'link', 'plain', 'text'],
    emits: ['click'],
    template: '<button v-bind="$attrs" type="button" :disabled="disabled || loading" @click="$emit(\'click\')"><slot /></button>'
  }),
  ElTabs: defineComponent({ props: ['modelValue'], template: '<div data-tabs :data-active="modelValue"><slot /></div>' }),
  ElTabPane: defineComponent({ props: ['name', 'label'], template: '<section :data-tab-pane="name"><h2>{{ label }}</h2><slot /></section>' }),
  ElForm: defineComponent({ props: ['labelWidth'], template: '<form :data-label-width="labelWidth" @submit.prevent><slot /></form>' }),
  ElFormItem: defineComponent({ props: ['label', 'required'], template: '<div class="form-item"><span class="form-item__label">{{ label }}</span><slot /></div>' }),
  ElInput: defineComponent({
    inheritAttrs: false,
    props: ['modelValue', 'disabled', 'readonly'],
    emits: ['update:modelValue'],
    template: '<input v-bind="$attrs" :value="modelValue" :disabled="disabled" :readonly="readonly" @input="$emit(\'update:modelValue\', $event.target.value)" />'
  }),
  ElInputNumber: defineComponent({
    inheritAttrs: false,
    props: ['modelValue', 'disabled', 'min', 'max', 'stepStrictly'],
    emits: ['update:modelValue', 'change'],
    template: '<input type="number" v-bind="$attrs" :data-step-strictly="stepStrictly === undefined || stepStrictly === false ? \'false\' : \'true\'" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value === \'\' ? undefined : Number($event.target.value)); $emit(\'change\', $event.target.value === \'\' ? undefined : Number($event.target.value))" />'
  }),
  ElSelect: defineComponent({
    inheritAttrs: false,
    props: ['modelValue', 'disabled'],
    emits: ['update:modelValue', 'change', 'visible-change'],
    template: '<select v-bind="$attrs" :value="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.value); $emit(\'change\', $event.target.value)"><slot /></select>'
  }),
  ElOption: defineComponent({ props: ['value', 'label'], template: '<option :value="value">{{ label }}</option>' }),
  ElOptionGroup: defineComponent({ props: ['label'], template: '<optgroup :label="label"><slot /></optgroup>' }),
  ElTreeSelect: defineComponent({ inheritAttrs: false, props: ['modelValue', 'data'], template: '<div v-bind="$attrs" data-tree-select :data-value="modelValue" />' }),
  ElRadioGroup: RadioGroup,
  ElRadio: Radio,
  ElRadioButton: Radio,
  ElSwitch: checkbox('switch'),
  ElCheckbox: checkbox('checkbox'),
  ElTag: defineComponent({ inheritAttrs: false, props: ['type'], template: '<span v-bind="$attrs" data-tag><slot /></span>' }),
  ElAlert: defineComponent({ inheritAttrs: false, props: ['title', 'type'], template: '<div v-bind="$attrs" role="alert">{{ title }}<slot /></div>' }),
  ElTooltip: defineComponent({ template: '<span><slot /></span>' }),
  ElDivider: defineComponent({ template: '<h4><slot /></h4>' }),
  ElEmpty: defineComponent({ props: ['description'], template: '<p>{{ description }}</p>' }),
  IconSelect: defineComponent({ props: ['modelValue'], emits: ['update:modelValue'], template: '<div data-icon-select>{{ modelValue }}</div>' }),
  VersionHistoryDrawer: defineComponent({ props: ['modelValue', 'moduleId', 'schemaHash'], template: '<div v-if="modelValue" data-version-history :data-hash="schemaHash" />' })
};

async function open(props: Record<string, unknown> = {}) {
  const wrapper = mount(EditGenerationDialog, { props: { modelValue: true, moduleId: 7, ...props }, global: { stubs } });
  await flushPromises();
  return wrapper;
}

type Wrapper = Awaited<ReturnType<typeof open>>;
const action = (wrapper: Wrapper, name: string) => wrapper.find(`[data-action="${name}"]`);
const configItem = (wrapper: Wrapper, name: string) => wrapper.find(`[data-config="${name}"]`);
const lastSavedSchema = (): FormSchemaDocument => mocks.saveSchema.mock.calls.at(-1)?.[1];

async function editTitleAndApiPrefix(wrapper: Wrapper) {
  await configItem(wrapper, 'title').find('input').setValue('商品管理');
  await configItem(wrapper, 'apiPrefix').find('input').setValue('/shop/goods');
}

beforeEach(() => {
  vi.clearAllMocks();
  mocks.permissions = ['*'];
  mocks.module.mockImplementation(async () => makeDetail());
  mocks.saveSchema.mockImplementation(async (_id: number, schema: FormSchemaDocument) => ({ schemaHash: 'hash-2', document: schema }));
  mocks.savePublishConfig.mockImplementation(async (_id: number, config: Record<string, unknown>) => ({ publishConfig: { sortOrder: 10, ...config } }));
  mocks.menuTree.mockResolvedValue([]);
  mocks.databaseTables.mockResolvedValue(['shop_category', 'shop_sku', 'shop_goods_ext', 'shop_profile', 'shop_goods_profile'].map((name) => ({ name, comment: '' })));
  mocks.databaseTableSchema.mockImplementation(async (_connection: string, table: string) => ({ table, columns: [{ name: 'id', type: 'bigint', comment: '', primary: true }, { name: 'goods_id', type: 'bigint', comment: '', primary: false }, { name: 'name', type: 'varchar(60)', comment: '', primary: false }] }));
  mocks.dictTypes.mockResolvedValue({ list: [], total: 0 });
  mocks.confirm.mockResolvedValue('confirm');
});

describe('EditGenerationDialog', () => {
  it('打开时加载模块详情，默认显示配置信息页签，页签齐全', async () => {
    const wrapper = await open();
    expect(mocks.module).toHaveBeenCalledWith(7);
    expect(wrapper.find('[data-tabs]').attributes('data-active')).toBe('config');
    expect(wrapper.findAll('[data-tab-pane]').map((pane) => pane.attributes('data-tab-pane'))).toEqual(['config', 'fields', 'relations', 'menu']);
    expect(wrapper.text()).toContain('fa_shop_goods');
  });

  it('initialTab 指定初始页签', async () => {
    const wrapper = await open({ initialTab: 'fields' });
    expect(wrapper.find('[data-tabs]').attributes('data-active')).toBe('fields');
  });

  it('配置页签包含设计器原「基本信息」全部项：来源、绑定表、所属菜单、树形列表/父级字段/新增页父级选择', async () => {
    const wrapper = await open();
    for (const key of ['tableName', 'title', 'formKey', 'className', 'connection', 'source', 'parentMenu', 'generationType', 'apiPrefix', 'routePath', 'formMode', 'formWidth', 'formFullscreen', 'leftTree', 'softDeletes', 'batchDelete', 'import', 'export', 'dataScopeEnabled', 'memberApiEnabled']) {
      expect(configItem(wrapper, key).exists(), key).toBe(true);
    }
    expect(configItem(wrapper, 'tableName').text()).toContain('fa_shop_goods');
    expect(configItem(wrapper, 'source').text()).toContain('创建新表');
    expect(configItem(wrapper, 'className').text()).toContain('ShopGoods');
    expect(configItem(wrapper, 'parentMenu').find('[data-tree-select]').exists()).toBe(true);
    expect(configItem(wrapper, 'treeParentField').exists()).toBe(false);
    await configItem(wrapper, 'generationType').find('[data-radio="tree"]').trigger('click');
    expect(configItem(wrapper, 'treeParentField').exists()).toBe(true);
    expect(configItem(wrapper, 'treeSelection').exists()).toBe(true);
    const parentOptions = configItem(wrapper, 'treeParentField').findAll('option').map((option) => option.attributes('value'));
    expect(parentOptions).toContain('parent_id');
    expect(parentOptions).not.toContain('items');
  });

  it('配置信息与菜单页签的标签不换行：表名称标签简短，标签宽度按最长标签自适应', async () => {
    const wrapper = await open();
    const tableName = configItem(wrapper, 'tableName');
    expect(tableName.find('.form-item__label').text()).toBe('表名称');
    expect(tableName.find('[title="即绑定的数据表"]').exists()).toBe(true);
    // 固定 108px 时「新增页父级选择」「回收站（软删除）」及英文标签会折行；auto 由 el-form 取最长标签宽度。
    for (const pane of ['config', 'menu']) {
      expect(wrapper.find(`[data-tab-pane="${pane}"] form`).attributes('data-label-width'), pane).toBe('auto');
    }
  });

  it('生成目标只读展示', async () => {
    const wrapper = await open();
    const target = configItem(wrapper, 'target');
    expect(target.text()).toContain('核心');
    expect(target.findAll('input, select, button, [data-radio-group]')).toHaveLength(0);
  });

  it('字段页签「全选列表」只改动列表显示，未触碰的节点和属性保持原样', async () => {
    const wrapper = await open();
    await action(wrapper, 'select-all-list').trigger('click');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).toHaveBeenCalledTimes(1);
    const [id, schema, expectedHash] = mocks.saveSchema.mock.calls[0];
    expect(id).toBe(7);
    expect(expectedHash).toBe('hash-1');
    const original = makeDoc();
    const expected = original.nodes.map((item) => item.type === 'subform' || item.list?.show ? item : { ...item, list: { ...item.list, show: true } });
    expect(schema.nodes).toEqual(expected);
    expect(schema.relations).toEqual(original.relations);
    expect(mocks.savePublishConfig).not.toHaveBeenCalled();
  });

  it('字段行编辑只提交改动的键；子表容器行只读', async () => {
    const wrapper = await open();
    await wrapper.find('[data-field-row="parent_id"] [data-col="title"] input').setValue('上级');
    expect(wrapper.find('[data-field-row="items"]').findAll('input, select').length).toBe(0);
    expect(wrapper.find('[data-field-row="items"]').text()).toContain('子表');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    const saved = lastSavedSchema();
    const original = makeDoc();
    expect(saved.nodes[1]).toEqual({ ...original.nodes[1], title: '上级' });
    expect(saved.nodes.filter((_, index) => index !== 1)).toEqual(original.nodes.filter((_, index) => index !== 1));
    // 一次保存只 emit 一次 saved
    expect(wrapper.emitted('saved')).toHaveLength(1);
  });

  it('保存顺序 saveSchema → savePublishConfig，发布设置只提交改动键；成功后 emit saved 并关闭', async () => {
    const wrapper = await open();
    await editTitleAndApiPrefix(wrapper);
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(lastSavedSchema().title).toBe('商品管理');
    expect(mocks.savePublishConfig).toHaveBeenCalledWith(7, { apiPrefix: '/shop/goods' });
    expect(mocks.saveSchema.mock.invocationCallOrder[0]).toBeLessThan(mocks.savePublishConfig.mock.invocationCallOrder[0]);
    expect(wrapper.emitted('saved')).toHaveLength(1);
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([false]);
    expect(wrapper.emitted('preview')).toBeUndefined();
  });

  it('saveSchema 冲突时不调用 savePublishConfig，提示核对版本并保留改动', async () => {
    mocks.saveSchema.mockRejectedValue(conflictError);
    const wrapper = await open();
    await editTitleAndApiPrefix(wrapper);
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.savePublishConfig).not.toHaveBeenCalled();
    expect(mocks.message.warning).toHaveBeenCalledWith(expect.stringContaining('核对版本'));
    expect(wrapper.find('[data-testid="schema-conflict-alert"]').exists()).toBe(true);
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    expect(wrapper.emitted('saved')).toBeUndefined();
    expect((configItem(wrapper, 'title').find('input').element as HTMLInputElement).value).toBe('商品管理');
    // 冲突未处理前不再盲目重试保存
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).toHaveBeenCalledTimes(1);
  });

  it('冲突核对：采用服务端版本后保留发布设置改动，可继续保存', async () => {
    mocks.saveSchema.mockRejectedValueOnce(conflictError);
    const wrapper = await open();
    await editTitleAndApiPrefix(wrapper);
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    const server = makeDetail();
    server.form!.schema_hash = 'hash-server';
    server.form!.schema_document = { ...makeDoc(), title: '服务端标题' } as unknown as NonNullable<FormDefinition['schema_document']>;
    mocks.module.mockResolvedValueOnce(server);
    await action(wrapper, 'review-conflict').trigger('click');
    await flushPromises();
    expect(wrapper.find('[data-testid="schema-conflict-review"]').text()).toContain('hash-server');
    await action(wrapper, 'conflict-use-server').trigger('click');
    await flushPromises();
    expect(wrapper.find('[data-testid="schema-conflict-alert"]').exists()).toBe(false);
    expect((configItem(wrapper, 'title').find('input').element as HTMLInputElement).value).toBe('服务端标题');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).toHaveBeenCalledTimes(1);
    expect(mocks.savePublishConfig).toHaveBeenCalledWith(7, { apiPrefix: '/shop/goods' });
  });

  it('冲突核对：以本地覆盖写入 Schema 后 emit saved，并使用核对的服务端 hash', async () => {
    mocks.saveSchema.mockRejectedValueOnce(conflictError);
    const wrapper = await open();
    await configItem(wrapper, 'title').find('input').setValue('商品管理');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    const server = makeDetail();
    server.form!.schema_hash = 'hash-server';
    mocks.module.mockResolvedValueOnce(server);
    await action(wrapper, 'review-conflict').trigger('click');
    await flushPromises();
    await action(wrapper, 'conflict-use-local').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).toHaveBeenCalledTimes(2);
    expect(mocks.saveSchema.mock.calls[1][2]).toBe('hash-server');
    expect(mocks.saveSchema.mock.calls[1][1].title).toBe('商品管理');
    expect(wrapper.emitted('saved')).toHaveLength(1);
    expect(wrapper.find('[data-testid="schema-conflict-alert"]').exists()).toBe(false);
  });

  it('第二步失败：弹窗保持打开，提示「字段已保存，发布设置未保存」，保留发布设置改动可重试', async () => {
    mocks.savePublishConfig.mockRejectedValueOnce(new Error('网络错误'));
    const wrapper = await open();
    await editTitleAndApiPrefix(wrapper);
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.message.error).toHaveBeenCalledWith(expect.stringContaining('字段已保存，发布设置未保存'));
    expect(wrapper.find('[data-testid="publish-save-failed"]').text()).toContain('字段已保存，发布设置未保存');
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    // Schema 已写入：宿主需要刷新新的版本 hash
    expect(wrapper.emitted('saved')).toHaveLength(1);
    expect((configItem(wrapper, 'apiPrefix').find('input').element as HTMLInputElement).value).toBe('/shop/goods');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).toHaveBeenCalledTimes(1);
    expect(mocks.savePublishConfig).toHaveBeenCalledTimes(2);
    expect(mocks.savePublishConfig).toHaveBeenLastCalledWith(7, { apiPrefix: '/shop/goods' });
    expect(wrapper.emitted('saved')).toHaveLength(2);
  });

  it('「保存并预览」任一步失败都不 emit preview，全部成功才 emit', async () => {
    mocks.saveSchema.mockRejectedValueOnce(new Error('保存失败'));
    const wrapper = await open();
    await editTitleAndApiPrefix(wrapper);
    await action(wrapper, 'save-preview').trigger('click');
    await flushPromises();
    expect(mocks.savePublishConfig).not.toHaveBeenCalled();
    expect(wrapper.emitted('preview')).toBeUndefined();

    mocks.savePublishConfig.mockRejectedValueOnce(new Error('网络错误'));
    await action(wrapper, 'save-preview').trigger('click');
    await flushPromises();
    expect(wrapper.emitted('preview')).toBeUndefined();
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();

    expect(wrapper.emitted('saved')).toHaveLength(1);

    await action(wrapper, 'save-preview').trigger('click');
    await flushPromises();
    expect(wrapper.emitted('preview')).toHaveLength(1);
    expect(wrapper.emitted('saved')).toHaveLength(2);
  });

  it('无改动时「保存并预览」不调用保存接口，直接 emit preview', async () => {
    const wrapper = await open();
    await action(wrapper, 'save-preview').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).not.toHaveBeenCalled();
    expect(mocks.savePublishConfig).not.toHaveBeenCalled();
    expect(wrapper.emitted('preview')).toHaveLength(1);
  });

  it('有未保存改动时「打开表单设计器」先提示；取消不跳转，确认后先保存再跳转', async () => {
    const wrapper = await open();
    await configItem(wrapper, 'title').find('input').setValue('商品管理');
    mocks.confirm.mockRejectedValueOnce('cancel');
    await action(wrapper, 'open-designer').trigger('click');
    await flushPromises();
    expect(mocks.confirm).toHaveBeenCalledTimes(1);
    expect(mocks.push).not.toHaveBeenCalled();
    expect(mocks.saveSchema).not.toHaveBeenCalled();

    await action(wrapper, 'open-designer').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).toHaveBeenCalledTimes(1);
    expect(mocks.push).toHaveBeenCalledWith({ path: '/admin/development/business/designer', query: { id: '3', moduleId: '7' } });
  });

  it('无改动时「打开表单设计器」直接跳转', async () => {
    const wrapper = await open();
    await action(wrapper, 'open-designer').trigger('click');
    await flushPromises();
    expect(mocks.confirm).not.toHaveBeenCalled();
    expect(mocks.push).toHaveBeenCalledWith({ path: '/admin/development/business/designer', query: { id: '3', moduleId: '7' } });
  });

  it('hideDesignerLink：从设计器内打开时隐藏「打开表单设计器」与子表单的设计器入口，默认显示', async () => {
    const shown = await open();
    expect(action(shown, 'open-designer').exists()).toBe(true);
    expect(action(shown, 'relation-open-designer').exists()).toBe(true);
    const hidden = await open({ hideDesignerLink: true });
    expect(action(hidden, 'open-designer').exists()).toBe(false);
    expect(action(hidden, 'relation-open-designer').exists()).toBe(false);
    expect(hidden.find('[data-relation-row="items"]').text()).toContain('子表单');
  });

  it('启用会员端 API 但归属字段不是整数字段时不保存发布设置', async () => {
    const wrapper = await open();
    wrapper.findComponent({ name: 'ConfigTab' }).vm.$emit('update:publish', { memberApiEnabled: true, memberApiOwnerField: 'title' });
    await flushPromises();
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.savePublishConfig).not.toHaveBeenCalled();
    expect(mocks.message.warning).toHaveBeenCalledWith(expect.stringContaining('会员归属字段'));
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
  });

  it('模块名：默认 business，可编辑并只提交改动键；不合法时拒绝保存', async () => {
    const wrapper = await open();
    const input = () => configItem(wrapper, 'module').find('input');
    expect((input().element as HTMLInputElement).value).toBe('business');
    await input().setValue('Shop_1');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.savePublishConfig).not.toHaveBeenCalled();
    expect(mocks.message.warning).toHaveBeenCalledWith('模块名须以小写字母开头，仅包含小写字母、数字和短横线');
    await input().setValue('shop-admin');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).not.toHaveBeenCalled();
    expect(mocks.savePublishConfig).toHaveBeenCalledWith(7, { module: 'shop-admin' });
  });

  it('模块名：插件目标由插件标识决定，只读展示', async () => {
    mocks.module.mockImplementation(async () => {
      const detail = makeDetail();
      detail.module.metadata = { target: { type: 'plugin', pluginCode: 'demo', scope: 'admin' } };
      return detail;
    });
    const wrapper = await open();
    expect(configItem(wrapper, 'module').find('input').exists()).toBe(false);
    expect(configItem(wrapper, 'module').text()).toContain('demo');
  });

  it('取消：有改动时先确认放弃', async () => {
    const wrapper = await open();
    await configItem(wrapper, 'title').find('input').setValue('商品管理');
    mocks.confirm.mockRejectedValueOnce('cancel');
    await action(wrapper, 'cancel').trigger('click');
    await flushPromises();
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    await action(wrapper, 'cancel').trigger('click');
    await flushPromises();
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([false]);
  });

  it('按字段名推荐控件只应用用户确认的行；新建表的物理类型随控件重置', async () => {
    const wrapper = await open();
    await action(wrapper, 'suggest-controls').trigger('click');
    expect(wrapper.findAll('[data-suggestion]').map((item) => item.attributes('data-suggestion'))).toEqual(['status', 'created_at']);
    await wrapper.find('[data-suggestion="created_at"] input').setValue(false);
    await action(wrapper, 'apply-suggestions').trigger('click');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    const saved = lastSavedSchema();
    const status = saved.nodes.find((item) => item.field === 'status')!;
    expect(status.type).toBe('radio');
    expect(status.database?.columnType).toBe('varchar(100)');
    expect(saved.nodes.find((item) => item.field === 'created_at')).toEqual(makeDoc().nodes.find((item) => item.field === 'created_at'));
  });

  it('采纳表切换控件保留物理类型', async () => {
    const detail = makeDetail();
    detail.form!.source_type = 'adopted';
    mocks.module.mockResolvedValueOnce(detail);
    const wrapper = await open();
    await wrapper.find('[data-field-row="status"] [data-col="component"] select').setValue('switch');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    const status = lastSavedSchema().nodes.find((item) => item.field === 'status')!;
    expect(status.type).toBe('switch');
    expect(status.database?.columnType).toBe('tinyint');
  });

  it('拖拽排序在同一父容器内调整顺序', async () => {
    const wrapper = await open();
    await wrapper.find('[data-field-row="parent_id"] [data-drag-handle]').trigger('dragstart');
    await wrapper.find('[data-field-row="title"]').trigger('dragover');
    await wrapper.find('[data-field-row="title"]').trigger('drop');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(lastSavedSchema().nodes.slice(0, 2).map((item) => item.field)).toEqual(['parent_id', 'title']);
  });

  it('未完成的关联草稿：计入未保存改动、阻止保存与静默关闭，其他页签改动文档时保留草稿', async () => {
    const wrapper = await open();
    await action(wrapper, 'add-relation').trigger('click');
    expect(wrapper.find('[data-testid="relation-error"]').exists()).toBe(true);
    expect(wrapper.find('[data-dirty-tag]').exists()).toBe(true);
    // 其他页签改动文档不应清掉草稿
    await configItem(wrapper, 'title').find('input').setValue('商品管理');
    expect(wrapper.findAll('[data-relation-row^="new-"]')).toHaveLength(1);
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).not.toHaveBeenCalled();
    expect(mocks.message.warning).toHaveBeenCalledWith(expect.stringContaining('关联配置有未完成的行'));
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    mocks.confirm.mockRejectedValueOnce('cancel');
    await action(wrapper, 'cancel').trigger('click');
    await flushPromises();
    expect(mocks.confirm).toHaveBeenCalledTimes(1);
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    // 补全草稿后写回当前文档（含标题改动）
    const row = wrapper.find('[data-relation-row^="new-"]');
    await row.find('[data-col="name"] input').setValue('profile');
    await wrapper.find('[data-relation-row="profile"] [data-col="table"] select').setValue('shop_profile');
    expect(wrapper.find('[data-testid="relation-error"]').exists()).toBe(false);
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    const saved = lastSavedSchema();
    expect(saved.title).toBe('商品管理');
    expect(saved.relations?.map((item) => item.name)).toEqual(['detail', 'profile']);
  });

  it('删除关联行后其余行的元素不被复用错位（稳定 key）', async () => {
    const wrapper = await open();
    const before = wrapper.find('[data-relation-row="detail"] [data-col="table"] select').element;
    await wrapper.find('[data-relation-row="category"] [data-action="remove-relation"]').trigger('click');
    expect(wrapper.find('[data-relation-row="category"]').exists()).toBe(false);
    expect(wrapper.find('[data-relation-row="detail"] [data-col="table"] select').element).toBe(before);
  });

  it('切换模块后加载失败：不显示也不能保存上一个模块的数据', async () => {
    const wrapper = await open();
    await configItem(wrapper, 'apiPrefix').find('input').setValue('/shop/goods');
    mocks.module.mockRejectedValueOnce(new Error('模块不存在'));
    await wrapper.setProps({ moduleId: 8 });
    await flushPromises();
    expect(mocks.module).toHaveBeenLastCalledWith(8);
    expect(wrapper.find('[data-testid="load-error"]').text()).toContain('模块不存在');
    expect(configItem(wrapper, 'title').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('fa_shop_goods');
    expect(action(wrapper, 'save').attributes('disabled')).toBeDefined();
    expect(action(wrapper, 'save-preview').attributes('disabled')).toBeDefined();
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).not.toHaveBeenCalled();
    expect(mocks.savePublishConfig).not.toHaveBeenCalled();
  });

  it('乱序响应：旧请求先返回时不显示旧模块、不结束加载状态，只采用最新一次加载', async () => {
    const wrapper = await open();
    let resolveOld: (value: BusinessModuleDetail) => void = () => {};
    let resolveNew: (value: BusinessModuleDetail) => void = () => {};
    mocks.module.mockImplementationOnce(() => new Promise<BusinessModuleDetail>((resolve) => { resolveOld = resolve; }));
    await wrapper.setProps({ moduleId: 8 });
    mocks.module.mockImplementationOnce(() => new Promise<BusinessModuleDetail>((resolve) => { resolveNew = resolve; }));
    await wrapper.setProps({ moduleId: 9 });
    await flushPromises();
    resolveOld(makeDetail());
    await flushPromises();
    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true);
    expect(configItem(wrapper, 'tableName').exists()).toBe(false);
    expect(action(wrapper, 'save').attributes('disabled')).toBeDefined();
    const other = makeDetail();
    other.module = { ...other.module, id: 9 };
    other.form = { ...other.form!, table_name: 'fa_other', schema_hash: 'hash-9' };
    resolveNew(other);
    await flushPromises();
    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(false);
    expect(configItem(wrapper, 'tableName').text()).toContain('fa_other');
    await configItem(wrapper, 'apiPrefix').find('input').setValue('/other/x');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.savePublishConfig).toHaveBeenCalledWith(9, { apiPrefix: '/other/x' });
  });

  it('关联页签：按模块连接加载数据表，一次打开内只加载一次（保存后重建页签复用），重新打开时重新加载', async () => {
    const detail = makeDetail();
    detail.form = { ...detail.form!, connection: 'shop_db' };
    mocks.module.mockImplementation(async () => detail);
    const wrapper = await open();
    expect(mocks.databaseTables).toHaveBeenCalledTimes(1);
    expect(mocks.databaseTables).toHaveBeenCalledWith('shop_db');
    expect(mocks.databaseTableSchema).toHaveBeenCalledWith('shop_db', 'shop_goods_ext');
    await wrapper.find('[data-relation-row="detail"] [data-col="table"] select').setValue('shop_profile');
    await flushPromises();
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.databaseTables).toHaveBeenCalledTimes(1);
    await wrapper.setProps({ modelValue: false });
    await wrapper.setProps({ modelValue: true });
    await flushPromises();
    expect(mocks.databaseTables).toHaveBeenCalledTimes(2);
  });

  it('关联页签：字段关联名称只读，子表只读，模型关联可编辑写入 relations', async () => {
    const wrapper = await open();
    const fieldRow = wrapper.find('[data-relation-row="category"]');
    expect(fieldRow.find('[data-col="name"] input').exists()).toBe(false);
    expect(fieldRow.find('[data-col="name"]').text()).toBe('category');
    expect(wrapper.find('[data-relation-row="items"]').findAll('input, select').length).toBe(0);
    await wrapper.find('[data-relation-row="detail"] [data-col="table"] select').setValue('shop_goods_profile');
    await flushPromises();
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(lastSavedSchema().relations).toEqual([{ type: 'hasOne', name: 'detail', table: 'shop_goods_profile', localField: 'id', foreignField: 'goods_id' }]);
    expect(lastSavedSchema().nodes).toEqual(makeDoc().nodes);
  });

  it('菜单与权限页签：菜单配置项与按方法推导的只读权限码', async () => {
    const wrapper = await open();
    const menu = wrapper.find('[data-tab-pane="menu"]');
    for (const key of ['menuEnabled', 'menuParent', 'menuName', 'icon', 'sortOrder']) expect(menu.find(`[data-menu="${key}"]`).exists(), key).toBe(true);
    const codes = menu.findAll('[data-permission-code]').map((item) => item.text());
    expect(codes).toContain('business:shop-goods:index');
    expect(codes).toContain('business:shop-goods:export');
    expect(menu.text()).toContain('按方法自动推导');
  });

  it('表单宽度接受 360–1600 之间的任意整数（不限步长）', async () => {
    const wrapper = await open();
    expect(configItem(wrapper, 'formWidth').find('input').attributes('data-step-strictly')).toBe('false');
    await configItem(wrapper, 'formWidth').find('input').setValue('365');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.savePublishConfig).toHaveBeenCalledWith(7, { formWidth: 365 });
  });

  it('表单宽度超出 360–1600 时拒绝保存', async () => {
    const wrapper = await open();
    await configItem(wrapper, 'formWidth').find('input').setValue('200');
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.savePublishConfig).not.toHaveBeenCalled();
    expect(mocks.message.warning).toHaveBeenCalledWith(expect.stringContaining('360'));
  });

  it('版本历史与全屏切换', async () => {
    const wrapper = await open();
    expect(wrapper.find('[role="dialog"]').attributes('data-fullscreen')).toBe('false');
    await action(wrapper, 'fullscreen').trigger('click');
    expect(wrapper.find('[role="dialog"]').attributes('data-fullscreen')).toBe('true');
    await action(wrapper, 'version-history').trigger('click');
    expect(wrapper.find('[data-version-history]').attributes('data-hash')).toBe('hash-1');
  });
});

describe('保存前兜底：分类树映射未补全按未开启保存', () => {
  const staleTree = { enabled: true, source: { type: 'current' as const }, mapping: { valueField: 'id', labelField: '', targetField: '' } };
  const staleDetail = (): BusinessModuleDetail => {
    const detail = makeDetail();
    const document = { ...makeDoc(), list: { leftTree: staleTree } };
    return { ...detail, form: { ...detail.form!, schema_document: document as unknown as NonNullable<FormDefinition['schema_document']> } };
  };

  it('保存 Schema 时发送关闭的分类树并提示缺少项，本地采用已保存值后不再重复保存', async () => {
    mocks.module.mockImplementation(async () => staleDetail());
    const wrapper = await open();
    await editTitleAndApiPrefix(wrapper);
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).toHaveBeenCalledTimes(1);
    expect(lastSavedSchema().list?.leftTree).toEqual({ ...staleTree, enabled: false });
    expect(mocks.message.warning).toHaveBeenCalledWith('左侧分类树的字段对应未填完（缺少：显示字段 / 右表关联字段），已按未开启保存');
    expect(wrapper.emitted('saved')).toHaveLength(1);
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).toHaveBeenCalledTimes(1);
  });

  it('冲突核对后采用本地同样发送关闭的分类树', async () => {
    mocks.module.mockImplementation(async () => staleDetail());
    mocks.saveSchema.mockRejectedValueOnce(conflictError);
    const wrapper = await open();
    await editTitleAndApiPrefix(wrapper);
    await action(wrapper, 'save').trigger('click');
    await flushPromises();
    await action(wrapper, 'review-conflict').trigger('click');
    await flushPromises();
    await action(wrapper, 'conflict-use-local').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).toHaveBeenCalledTimes(2);
    expect(mocks.saveSchema.mock.calls[1][1].list.leftTree).toEqual({ ...staleTree, enabled: false });
  });
});
