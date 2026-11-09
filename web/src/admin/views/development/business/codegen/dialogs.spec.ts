import { defineComponent, h, inject, provide, toRef, type Ref } from 'vue';
import { enableAutoUnmount, flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { BusinessFormalGenerationPreview, BusinessModule, BusinessModuleDetail } from '@/api/development/business';
import type { FormDefinition } from '@/api/form';
import type { FormSchemaDocument, FormSchemaNode } from '@/views/form/schema/types';
import type { DatabaseSyncDiff } from './syncMerge';

const mocks = vi.hoisted(() => ({
  permissions: ['*'] as string[],
  calls: [] as string[],
  targets: vi.fn(),
  databaseTables: vi.fn(),
  modules: vi.fn(),
  inspectDatabase: vi.fn(),
  createFromDatabase: vi.fn(),
  module: vi.fn(),
  databaseSync: vi.fn(),
  saveSchema: vi.fn(),
  previewFormalGeneration: vi.fn(),
  formalGeneration: vi.fn(),
  generation: vi.fn(),
  menuTree: vi.fn(),
  refreshBusinessMenu: vi.fn(),
  mergeDatabaseSync: vi.fn(),
  message: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
  confirm: vi.fn()
}));

vi.mock('@/api/development/business', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/development/business')>();
  return {
    ...actual,
    businessDevelopmentApi: {
      ...actual.businessDevelopmentApi,
      targets: mocks.targets,
      databaseTables: mocks.databaseTables,
      modules: mocks.modules,
      inspectDatabase: mocks.inspectDatabase,
      createFromDatabase: mocks.createFromDatabase,
      module: mocks.module,
      databaseSync: mocks.databaseSync,
      saveSchema: mocks.saveSchema,
      previewFormalGeneration: mocks.previewFormalGeneration,
      formalGeneration: mocks.formalGeneration,
      generation: mocks.generation
    }
  };
});
vi.mock('./syncMerge', async (importOriginal) => {
  const actual = await importOriginal<typeof import('./syncMerge')>();
  mocks.mergeDatabaseSync.mockImplementation(actual.mergeDatabaseSync);
  return { ...actual, mergeDatabaseSync: mocks.mergeDatabaseSync };
});
vi.mock('@/api/system/menu', () => ({ menuApi: { tree: mocks.menuTree } }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ get permissions() { return mocks.permissions; } }) }));
vi.mock('vue-router', () => ({ useRoute: () => ({ query: {} }), useRouter: () => ({ push: vi.fn(), addRoute: vi.fn() }) }));
vi.mock('../composables/useBusinessMenuRefresh', () => ({
  useBusinessMenuRefresh: () => ({ refreshBusinessMenu: mocks.refreshBusinessMenu })
}));
vi.mock('element-plus', async (importOriginal) => {
  const actual = await importOriginal<typeof import('element-plus')>();
  return { ...actual, ElMessage: mocks.message, ElMessageBox: { confirm: mocks.confirm } };
});

import LoadTablesDialog from './LoadTablesDialog.vue';
import DatabaseSyncDialog from './DatabaseSyncDialog.vue';
import BatchGenerateDialog from './BatchGenerateDialog.vue';

const rowKey = Symbol('row');
const TableRow = defineComponent({
  props: ['row'],
  setup(props, { slots }) {
    provide(rowKey, toRef(props, 'row'));
    return () => h('div', { 'data-table-row': String(props.row.name ?? props.row.id ?? '') }, slots.default?.());
  }
});
const ElTable = defineComponent({
  props: ['data'],
  setup(props, { slots }) {
    return () => h('div', { 'data-table': '' }, (props.data ?? []).map((row: Record<string, unknown>) => h(TableRow, { row }, () => slots.default?.())));
  }
});
const ElTableColumn = defineComponent({
  props: ['label', 'prop'],
  setup(props, { slots }) {
    const row = inject<Ref<Record<string, unknown>> | undefined>(rowKey, undefined);
    return () => h('span', { 'data-column': props.label ?? '' }, slots.default ? slots.default({ row: row?.value }) : String(row?.value?.[props.prop] ?? ''));
  }
});

const stubs = {
  ElDialog: defineComponent({
    props: ['modelValue', 'title', 'width'],
    emits: ['update:modelValue'],
    template: '<aside v-if="modelValue" role="dialog"><header>{{ title }}<slot name="header" /></header><slot /><footer><slot name="footer" /></footer></aside>'
  }),
  ElButton: defineComponent({
    inheritAttrs: false,
    props: ['disabled', 'loading', 'type', 'icon', 'link', 'plain', 'text'],
    emits: ['click'],
    template: '<button v-bind="$attrs" type="button" :disabled="disabled || loading" @click="$emit(\'click\')"><slot /></button>'
  }),
  ElInput: defineComponent({
    inheritAttrs: false,
    props: ['modelValue', 'disabled'],
    emits: ['update:modelValue', 'change', 'keyup'],
    template: '<input v-bind="$attrs" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" @change="$emit(\'change\', $event.target.value)" />'
  }),
  ElSelect: defineComponent({
    inheritAttrs: false,
    props: ['modelValue', 'disabled'],
    emits: ['update:modelValue', 'change'],
    template: '<select v-bind="$attrs" :value="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.value); $emit(\'change\', $event.target.value)"><slot /></select>'
  }),
  ElOption: defineComponent({ props: ['value', 'label', 'disabled'], template: '<option :value="value" :disabled="disabled">{{ label }}</option>' }),
  ElCheckbox: defineComponent({
    inheritAttrs: false,
    props: ['modelValue', 'disabled', 'indeterminate'],
    emits: ['update:modelValue', 'change'],
    template: '<label><input type="checkbox" v-bind="$attrs" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked); $emit(\'change\', $event.target.checked)" /><slot /></label>'
  }),
  ElTag: defineComponent({ inheritAttrs: false, props: ['type'], template: '<span v-bind="$attrs" data-tag :data-type="type"><slot /></span>' }),
  ElAlert: defineComponent({ inheritAttrs: false, props: ['title', 'type'], template: '<div v-bind="$attrs" role="alert">{{ title }}<slot /></div>' }),
  ElEmpty: defineComponent({ props: ['description'], template: '<p data-empty>{{ description }}</p>' }),
  ElTable,
  ElTableColumn,
  SchemaConflictDialog: defineComponent({
    props: ['modelValue', 'review'],
    emits: ['cancel', 'recheck', 'resolve'],
    template: '<div v-if="modelValue" data-testid="conflict-dialog"><button data-action="conflict-use-local" @click="$emit(\'resolve\', \'local\')" /><button data-action="conflict-use-server" @click="$emit(\'resolve\', \'server\')" /></div>'
  })
};

enableAutoUnmount(afterEach);

const action = (wrapper: VueWrapper, name: string) => wrapper.find(`[data-action="${name}"]`);
const apiError = (msg: string, code = 'X', requestId = 'req-1') => ({ code: 422, msg, data: { error: { code, requestId, retryable: false, details: {} } } });

const baseModule: BusinessModule = {
  id: 7, code: 'shop_goods', name: '商品', origin: 'database', lifecycle_status: 'draft', table_name: 'fun_shop_goods', connection_name: 'mysql', form_id: 3,
  metadata: { target: { type: 'core', pluginCode: null, scope: 'admin' } }
};

beforeEach(() => {
  vi.clearAllMocks();
  mocks.permissions = ['*'];
  mocks.calls = [];
  mocks.targets.mockResolvedValue({ list: [{ type: 'core', pluginCode: null, scope: 'admin', name: '核心', available: true }, { type: 'plugin', pluginCode: 'cms', scope: 'admin', name: 'CMS', available: true }], defaultConnection: 'mysql', migrationPath: '' });
  mocks.menuTree.mockResolvedValue([]);
  mocks.refreshBusinessMenu.mockResolvedValue([]);
  mocks.confirm.mockResolvedValue('confirm');
  vi.stubGlobal('crypto', { randomUUID: vi.fn(() => `nonce-${mocks.previewFormalGeneration.mock.calls.length + 1}`) });
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('LoadTablesDialog', () => {
  const tables = [
    { name: 'fun_shop_order', comment: '订单' },
    { name: 'fun_shop_order_item', comment: '' },
    { name: 'fun_shop_goods', comment: '商品' },
    { name: 'fun_admin', comment: '管理员' }
  ];

  beforeEach(() => {
    mocks.databaseTables.mockResolvedValue(tables);
    mocks.modules.mockResolvedValue({ list: [baseModule, { ...baseModule, id: 8, table_name: 'fun_shop_order', connection_name: 'other' }], total: 2, page: 1, pageSize: 100 });
    mocks.inspectDatabase.mockImplementation(async (connection: string, table: string) => {
      mocks.calls.push(`inspect:${table}`);
      if (table === 'fun_admin') throw apiError('系统核心表，不可装载');
      return { connection, table, fields: [{ name: 'id' }], primaryKey: ['id'], snapshotHash: `hash-${table}`, indexes: [], observedAt: '' };
    });
    mocks.createFromDatabase.mockImplementation(async (payload: Record<string, unknown>) => {
      mocks.calls.push(`create:${payload.table}`);
      return { module: { ...baseModule, id: 100 + mocks.calls.length, code: payload.code, table_name: payload.table } };
    });
  });

  async function open() {
    const wrapper = mount(LoadTablesDialog, { props: { modelValue: true }, global: { stubs } });
    await flushPromises();
    return wrapper;
  }

  it('同一连接下已装载的表置灰并说明，其他连接的同名表不受影响', async () => {
    const wrapper = await open();
    expect(mocks.databaseTables).toHaveBeenCalledWith('mysql');
    expect(mocks.modules).toHaveBeenCalledWith(expect.objectContaining({ page: 1, pageSize: 100 }));
    const goods = wrapper.find('[data-select="fun_shop_goods"]');
    expect(goods.attributes('disabled')).toBeDefined();
    expect(wrapper.find('[data-table-note="fun_shop_goods"]').text()).toBe('已装载');
    expect(wrapper.find('[data-select="fun_shop_order"]').attributes('disabled')).toBeUndefined();
    expect(wrapper.find('[data-table-note="fun_shop_order"]').text()).toBe('');
  });

  it('按数据表顺序逐张执行 inspect → from-database，逐行显示结果，部分失败不影响其他表', async () => {
    const wrapper = await open();
    for (const name of ['fun_shop_order', 'fun_admin', 'fun_shop_order_item']) {
      await wrapper.find(`[data-select="${name}"]`).setValue(true);
    }
    // 已装载的表即使被尝试勾选也不会进入队列
    expect(action(wrapper, 'load-submit').text()).toContain('装载 3 张表');
    await action(wrapper, 'load-submit').trigger('click');
    await flushPromises();

    expect(mocks.calls).toEqual([
      'inspect:fun_shop_order', 'create:fun_shop_order',
      'inspect:fun_shop_order_item', 'create:fun_shop_order_item',
      'inspect:fun_admin'
    ]);
    expect(mocks.createFromDatabase).toHaveBeenNthCalledWith(1, expect.objectContaining({
      name: '订单', code: 'shop_order', connection: 'mysql', table: 'fun_shop_order',
      target: { type: 'core' }, expectedInspectionHash: 'hash-fun_shop_order'
    }));
    expect(mocks.createFromDatabase).toHaveBeenNthCalledWith(2, expect.objectContaining({ name: 'shop_order_item', code: 'shop_order_item' }));
    expect(wrapper.find('[data-table-note="fun_shop_order"]').text()).toBe('已装载为 shop_order');
    expect(wrapper.find('[data-table-note="fun_admin"]').text()).toBe('系统核心表，不可装载');
    expect(wrapper.find('[data-select="fun_shop_order"]').attributes('disabled')).toBeDefined();
    expect(wrapper.find('[data-select="fun_admin"]').attributes('disabled')).toBeUndefined();
    const loaded = wrapper.emitted('loaded');
    expect(loaded).toHaveLength(1);
    expect((loaded?.[0][0] as BusinessModule[]).map((item) => item.code)).toEqual(['shop_order', 'shop_order_item']);
  });

  it('选择插件目标时随装载提交该目标；搜索按表名或描述过滤', async () => {
    const wrapper = await open();
    await wrapper.find('[data-field="target"]').setValue('cms');
    await wrapper.find('[data-field="keyword"]').setValue('订单');
    expect(wrapper.findAll('[data-table-row]').map((row) => row.attributes('data-table-row'))).toEqual(['fun_shop_order']);
    await wrapper.find('[data-select="fun_shop_order"]').setValue(true);
    await action(wrapper, 'load-submit').trigger('click');
    await flushPromises();
    expect(mocks.createFromDatabase).toHaveBeenCalledWith(expect.objectContaining({ target: { type: 'plugin', pluginCode: 'cms' } }));
  });

  it('生成目标默认选中核心目标（真实 el-select 显示核心而非「请选择」）', async () => {
    const { ElSelect, ElOption, default: ElementPlus } = await vi.importActual<typeof import('element-plus')>('element-plus');
    const wrapper = mount(LoadTablesDialog, { props: { modelValue: true }, global: { plugins: [ElementPlus], stubs: { ...stubs, ElSelect, ElOption } } });
    await flushPromises();
    const select = wrapper.find('[data-field="target"]');
    const target = select.exists() ? select : wrapper.find('.codegen-load__target');
    expect(target.find('.el-select__placeholder').classes()).not.toContain('is-transparent');
    expect(target.find('.el-select__placeholder').text()).toBe('核心');
    await wrapper.find('[data-select="fun_shop_order"]').setValue(true);
    await action(wrapper, 'load-submit').trigger('click');
    await flushPromises();
    expect(mocks.createFromDatabase).toHaveBeenCalledWith(expect.objectContaining({ target: { type: 'core' } }));
  });

  it('缺少主键时该行失败，不调用 from-database', async () => {
    mocks.inspectDatabase.mockResolvedValue({ connection: 'mysql', table: 'fun_shop_order', fields: [{ name: 'a' }], primaryKey: [], snapshotHash: 'h', indexes: [], observedAt: '' });
    const wrapper = await open();
    await wrapper.find('[data-select="fun_shop_order"]').setValue(true);
    await action(wrapper, 'load-submit').trigger('click');
    await flushPromises();
    expect(mocks.createFromDatabase).not.toHaveBeenCalled();
    expect(wrapper.find('[data-table-note="fun_shop_order"]').text()).toBe('缺少主键，无法采纳');
    expect(wrapper.emitted('loaded')).toBeUndefined();
  });

  it('没有装载权限时提示且不能提交', async () => {
    mocks.permissions = ['development:business:database-tables', 'development:business:modules'];
    const wrapper = await open();
    expect(wrapper.text()).toContain('没有装载权限');
    expect(action(wrapper, 'load-submit').attributes('disabled')).toBeDefined();
  });
});

const node = (id: string, field: string, columnType: string, extra: Partial<FormSchemaNode> = {}): FormSchemaNode => ({
  id, kind: 'field', type: 'input', field, title: field, props: {}, children: [], validation: [], dataSource: null,
  conditions: [], events: {}, hidden: false,
  database: { columnType, nullable: true, comment: field, relation: { type: 'none' } },
  list: { show: false, filter: '', sort: false, width: 0 },
  ...extra
});

const syncDoc = (): FormSchemaDocument => ({
  schemaVersion: 2, key: 'shop_goods', title: '商品', list: {},
  nodes: [node('n_title', 'title', 'varchar(120)', { title: '标题' }), node('n_price', 'price', 'decimal(10,2)'), node('n_old', 'legacy', 'int')]
});

const syncDetail = (hash = 'hash-1', doc = syncDoc()): BusinessModuleDetail => ({
  module: { ...baseModule },
  form: {
    id: 3, form_key: 'shop_goods', name: '商品', table_name: 'fun_shop_goods', connection: 'mysql', source_type: 'adopted',
    status: 1, remark: '', sort_order: 0, schema_hash: hash, schema_document: doc as unknown as NonNullable<FormDefinition['schema_document']>, publish_config: {}
  }
});

const diff = (): DatabaseSyncDiff => ({
  inspectionHash: 'insp-1',
  added: [node('n_stock', 'stock', 'int'), node('n_sku', 'sku', 'varchar(32)')],
  removed: ['legacy'],
  changed: [{ field: 'title', from: { columnType: 'varchar(120)', nullable: true }, to: { columnType: 'varchar(200)', nullable: false } }]
});

describe('DatabaseSyncDialog', () => {
  beforeEach(() => {
    mocks.module.mockResolvedValue(syncDetail());
    mocks.databaseSync.mockResolvedValue(diff());
    mocks.saveSchema.mockImplementation(async (_id: number, schema: FormSchemaDocument) => ({ schemaHash: 'hash-2', document: schema }));
  });

  async function open(module: BusinessModule = baseModule) {
    const wrapper = mount(DatabaseSyncDialog, { props: { modelValue: true, module }, global: { stubs } });
    await flushPromises();
    return wrapper;
  }

  it('按新增 / 删除 / 变化三组展示差异，变化显示旧值与新值', async () => {
    const wrapper = await open();
    expect(mocks.databaseSync).toHaveBeenCalledWith(7);
    expect(wrapper.find('[data-sync-section="added"]').text()).toContain('stock');
    expect(wrapper.find('[data-sync-section="removed"]').text()).toContain('legacy');
    const changed = wrapper.find('[data-sync-change="title"]').text();
    expect(changed).toContain('varchar(120)');
    expect(changed).toContain('varchar(200)');
    expect(changed).toContain('是');
    expect(changed).toContain('否');
  });

  it('差异为空时提示表结构与配置一致，不能应用', async () => {
    mocks.databaseSync.mockResolvedValue({ inspectionHash: 'i', added: [], removed: [], changed: [] });
    const wrapper = await open();
    expect(wrapper.text()).toContain('表结构与配置一致');
    expect(action(wrapper, 'sync-apply').exists()).toBe(false);
  });

  it('按勾选调用 mergeDatabaseSync，再以加载时的 hash 保存 Schema 并发出 synced', async () => {
    const wrapper = await open();
    await wrapper.find('[data-sync-pick="added:sku"]').setValue(false);
    await wrapper.find('[data-sync-pick="removed:legacy"]').setValue(true);
    await action(wrapper, 'sync-apply').trigger('click');
    await flushPromises();
    expect(mocks.mergeDatabaseSync).toHaveBeenCalledTimes(1);
    const [baseDoc, passedDiff, pick] = mocks.mergeDatabaseSync.mock.calls[0];
    expect((baseDoc as FormSchemaDocument).nodes.map((item) => item.field)).toEqual(['title', 'price', 'legacy']);
    expect(passedDiff).toEqual(diff());
    expect(pick).toEqual({ added: ['stock'], removed: ['legacy'], changed: ['title'] });
    expect(mocks.saveSchema).toHaveBeenCalledTimes(1);
    const [id, saved, hash] = mocks.saveSchema.mock.calls[0];
    expect(id).toBe(7);
    expect(hash).toBe('hash-1');
    expect((saved as FormSchemaDocument).nodes.map((item) => item.field)).toEqual(['title', 'price', 'stock']);
    expect((saved as FormSchemaDocument).nodes[0].title).toBe('标题');
    expect((saved as FormSchemaDocument).nodes[0].database?.columnType).toBe('varchar(200)');
    expect(wrapper.emitted('synced')).toHaveLength(1);
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([false]);
  });

  it('保存冲突时不静默覆盖：提示核对版本，核对后以本地覆盖才发出 synced', async () => {
    mocks.saveSchema.mockRejectedValueOnce(apiError('冲突', 'FORM_SCHEMA_CONFLICT'));
    const wrapper = await open();
    await action(wrapper, 'sync-apply').trigger('click');
    await flushPromises();
    expect(wrapper.find('[data-testid="sync-conflict-alert"]').exists()).toBe(true);
    expect(wrapper.emitted('synced')).toBeUndefined();

    mocks.module.mockResolvedValue(syncDetail('hash-server'));
    await action(wrapper, 'sync-review-conflict').trigger('click');
    await flushPromises();
    await action(wrapper, 'conflict-use-local').trigger('click');
    await flushPromises();
    expect(mocks.saveSchema).toHaveBeenCalledTimes(2);
    expect(mocks.saveSchema.mock.calls[1][2]).toBe('hash-server');
    expect(wrapper.emitted('synced')).toHaveLength(1);
  });

  it('再次应用时始终基于加载的文档合并，不会重复追加字段', async () => {
    mocks.saveSchema.mockRejectedValueOnce(apiError('保存失败'));
    const wrapper = await open();
    await action(wrapper, 'sync-apply').trigger('click');
    await flushPromises();
    await action(wrapper, 'sync-apply').trigger('click');
    await flushPromises();
    const saved = mocks.saveSchema.mock.calls[1][1] as FormSchemaDocument;
    expect(saved.nodes.filter((item) => item.field === 'stock')).toHaveLength(1);
  });

  it('可视化新建的模块不能同步', async () => {
    const wrapper = await open({ ...baseModule, origin: 'visual' });
    expect(mocks.databaseSync).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('只有数据库装载的模块可以同步表结构');
  });
});

const previewOf = (overrides: Partial<BusinessFormalGenerationPreview> = {}): BusinessFormalGenerationPreview => ({
  generationId: 40,
  plan: { blocked: false, files: [{ path: 'a.php', status: 'create' }, { path: 'b.vue', status: 'create' }] },
  conflicts: [],
  sensitive: { confirmToken: 'token' },
  ...overrides
});

describe('BatchGenerateDialog', () => {
  const modules: BusinessModule[] = [
    { ...baseModule, id: 1, code: 'a', table_name: 'fun_a' },
    { ...baseModule, id: 2, code: 'b', table_name: 'fun_b' },
    { ...baseModule, id: 3, code: 'c', table_name: 'fun_c' },
    { ...baseModule, id: 4, code: 'd', table_name: 'fun_d' }
  ];
  const conflict = { path: 'x.vue', status: 'conflict' as const };

  beforeEach(() => {
    mocks.previewFormalGeneration.mockImplementation(async (id: number) => {
      mocks.calls.push(`preview:${id}`);
      if (id === 2) return previewOf({ generationId: 52, plan: { blocked: true, files: [conflict, { path: 'y.php', status: 'update' }] }, conflicts: [conflict], sensitive: undefined });
      if (id === 4) throw apiError('预览出错');
      return previewOf({ generationId: 50 + id, sensitive: { confirmToken: `token-${id}` } });
    });
    mocks.formalGeneration.mockImplementation(async (id: number, generationId: number) => {
      mocks.calls.push(`generate:${id}`);
      if (id === 3) throw apiError('令牌已失效');
      return { generationId, state: 'completed', resourceApplyStatus: 'applied' };
    });
  });

  async function open(list = modules) {
    const wrapper = mount(BatchGenerateDialog, { props: { modelValue: true, modules: list }, global: { stubs } });
    await flushPromises();
    return wrapper;
  }

  it('先逐个预览汇总：可否生成、冲突数、文件数；阻断的模块给出去预览入口', async () => {
    const wrapper = await open();
    expect(mocks.calls).toEqual(['preview:1', 'preview:2', 'preview:3', 'preview:4']);
    expect(mocks.formalGeneration).not.toHaveBeenCalled();
    expect(wrapper.find('[data-batch-status="1"]').text()).toBe('可生成');
    expect(wrapper.find('[data-batch-status="2"]').text()).toContain('1 个冲突文件');
    expect(wrapper.find('[data-batch-status="4"]').text()).toContain('预览出错');
    expect(wrapper.find('[data-batch-files="1"]').text()).toBe('2');
    expect(wrapper.find('[data-batch-files="2"]').text()).toBe('2');
    await wrapper.find('[data-open-preview="2"]').trigger('click');
    expect(wrapper.emitted('open-preview')?.[0]).toEqual([2]);
    expect(action(wrapper, 'batch-confirm').text()).toContain('确认生成 2 个模块');
  });

  it('确认后只对未阻断模块逐个生成，各用自己的 confirmToken；失败不影响其他模块', async () => {
    const wrapper = await open();
    mocks.calls = [];
    await action(wrapper, 'batch-confirm').trigger('click');
    await flushPromises();
    expect(mocks.calls).toEqual(['generate:1', 'generate:3']);
    expect(mocks.formalGeneration).toHaveBeenNthCalledWith(1, 1, 51, 'token-1', { deferReload: true });
    expect(mocks.formalGeneration).toHaveBeenNthCalledWith(2, 3, 53, 'token-3', { deferReload: false });
    expect(wrapper.find('[data-batch-result="1"]').text()).toBe('生成成功');
    expect(wrapper.find('[data-batch-result="3"]').text()).toContain('令牌已失效');
    expect(wrapper.find('[data-batch-result="2"]').text()).toBe('已跳过');
    expect(wrapper.find('[data-batch-result="4"]').text()).toBe('已跳过');
    expect(mocks.refreshBusinessMenu).toHaveBeenCalledTimes(1);
    expect(wrapper.emitted('generated')).toHaveLength(1);
    expect(action(wrapper, 'batch-confirm').exists()).toBe(false);
  });

  it('只有最后一个可生成模块请求后端重载，其余都 deferReload；重载后提示稍后可用并约 1.5 秒后刷新菜单', async () => {
    const ready = [modules[0], modules[2], { ...baseModule, id: 5, code: 'e', table_name: 'fun_e' }];
    mocks.formalGeneration.mockImplementation(async (id: number, generationId: number, _token: string, options?: { deferReload?: boolean }) => (
      options?.deferReload
        ? { generationId, state: 'completed', resourceApplyStatus: 'applied', reloadRequested: false, reloadReason: 'deferred' }
        : { generationId, state: 'completed', resourceApplyStatus: 'applied', reloadRequested: true, reloadReason: null }
    ));
    const wrapper = await open(ready);
    vi.useFakeTimers({ toFake: ['setTimeout'] });
    try {
      await action(wrapper, 'batch-confirm').trigger('click');
      await flushPromises();
      expect(mocks.formalGeneration.mock.calls.map(([id, , , options]) => [id, options])).toEqual([
        [1, { deferReload: true }],
        [3, { deferReload: true }],
        [5, { deferReload: false }]
      ]);
      expect(mocks.message.success).toHaveBeenCalledWith('后端已重新加载，新页面稍后可用');
      expect(mocks.message.warning).not.toHaveBeenCalled();
      expect(mocks.refreshBusinessMenu).not.toHaveBeenCalled();
      vi.advanceTimersByTime(1500);
      await flushPromises();
      expect(mocks.refreshBusinessMenu).toHaveBeenCalledTimes(1);
      expect(wrapper.find('[data-batch-summary]').text()).toContain('成功 3 个');
    } finally {
      vi.useRealTimers();
    }
  });

  it('最后一个可生成模块失败时（前面模块已延后重载）警告需要重启后端，不提示已重载', async () => {
    const wrapper = await open();
    await action(wrapper, 'batch-confirm').trigger('click');
    await flushPromises();
    expect(wrapper.find('[data-batch-result="1"]').text()).toBe('生成成功');
    expect(wrapper.find('[data-batch-result="3"]').text()).toContain('令牌已失效');
    expect(mocks.message.warning).toHaveBeenCalledWith('请重启后端服务使新生成的接口生效');
    expect(mocks.message.success).not.toHaveBeenCalledWith('后端已重新加载，新页面稍后可用');
    expect(mocks.refreshBusinessMenu).toHaveBeenCalledTimes(1);
  });

  it('全部为插件模块时不提示重启或重载', async () => {
    const plugin = (module: BusinessModule): BusinessModule => ({ ...module, metadata: { target: { type: 'plugin', pluginCode: 'cms', scope: 'admin' } } });
    mocks.formalGeneration.mockImplementation(async (_id: number, generationId: number) => ({ generationId, state: 'completed', resourceApplyStatus: 'applied', reloadRequested: false, reloadReason: 'plugin_target' }));
    const wrapper = await open([plugin(modules[0]), plugin(modules[2])]);
    await action(wrapper, 'batch-confirm').trigger('click');
    await flushPromises();
    expect(mocks.message.warning).not.toHaveBeenCalled();
    expect(mocks.message.success).not.toHaveBeenCalled();
    expect(mocks.refreshBusinessMenu).not.toHaveBeenCalled();
  });

  it('表单从未发布的模块显示简短原因（核心：需先动态发布；插件：需先在设计器发布），不显示原始错误', async () => {
    const notPublished = apiError('表单尚未发布', 'FORM_SCHEMA_NOT_PUBLISHED', 'req-np');
    mocks.previewFormalGeneration.mockImplementation(async (id: number) => {
      if (id === 1 || id === 2) throw notPublished;
      return previewOf({ generationId: 50 + id, sensitive: { confirmToken: `token-${id}` } });
    });
    const plugin: BusinessModule = { ...modules[1], metadata: { target: { type: 'plugin', pluginCode: 'cms', scope: 'admin' } } };
    const wrapper = await open([modules[0], plugin, modules[2]]);
    expect(wrapper.find('[data-batch-status="1"]').text()).toBe('未发布，需先动态发布');
    expect(wrapper.find('[data-batch-status="2"]').text()).toBe('未发布，需先在表单设计器发布');
    expect(wrapper.text()).not.toContain('表单尚未发布');
    expect(wrapper.find('[data-batch-status="3"]').text()).toBe('可生成');
    expect(action(wrapper, 'batch-confirm').text()).toContain('确认生成 1 个模块');
  });

  it('没有生成权限时只预览，不能确认生成', async () => {
    mocks.permissions = ['development:business:preview-formal-generation'];
    const wrapper = await open([modules[0]]);
    expect(mocks.previewFormalGeneration).toHaveBeenCalledTimes(1);
    expect(wrapper.find('[data-batch-status="1"]').text()).toBe('缺少生成权限');
    expect(action(wrapper, 'batch-confirm').attributes('disabled')).toBeDefined();
  });
});
