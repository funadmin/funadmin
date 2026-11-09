import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, nextTick, reactive } from 'vue';
import ElementPlus, { ElDatePicker, ElDialog, ElDrawer, ElMessage, ElMessageBox } from 'element-plus';
import { createI18n } from 'vue-i18n';
import DataPage from '@/views/form/data.vue';
import PublishedPage from '@/views/form/published.vue';
import SearchForm from '@/components/SearchForm/index.vue';
import SchemaTablePage from '@/components/DataTable/SchemaTablePage.vue';
import ListButtonBar from '@/views/form/components/ListButtonBar.vue';
import ListCategoryPanel from '@/views/form/components/ListCategoryPanel.vue';
import ListSourceTree from '@/views/form/components/ListSourceTree.vue';
import SchemaRenderer from '@/views/form/components/SchemaRenderer.vue';
import FormDataImportDialog from '@/views/form/components/FormDataImportDialog.vue';
import { formDataApi, type FormDataMeta } from '@/api/formData';
import type { FormSchemaNode } from '@/views/form/schema/types';

const mocks = vi.hoisted(() => ({ route: {} as Record<string, unknown>, submit: vi.fn() }));
vi.mock('vue-router', () => ({ useRoute: () => mocks.route, useRouter: () => ({ push: vi.fn() }) }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ permissions: ['*'] }) }));
vi.mock('@/api/formData', () => ({ formDataApi: { meta: vi.fn(), index: vi.fn(), detail: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn(), leftTree: vi.fn() } }));
vi.mock('@/views/form/components/SchemaRenderer.vue', () => ({ default: defineComponent({
  props: ['values', 'schema', 'formKey'],
  setup(props, { expose }) { expose({ submit: mocks.submit, setFieldErrors: vi.fn() }); return () => h('pre', JSON.stringify(props.values)); }
}) }));

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
  return { promise, resolve, reject };
}
const node = (name: string, filter = ''): FormSchemaNode => ({ id: `node_${name}`, kind: 'field', type: 'input', field: name, title: name, children: [], database: { columnType: 'varchar' }, list: { show: true, ...(filter ? { filter } : {}) } });
// 运行态页面只读取 schema.nodes；后端在 Task 11 前仍可能附带 fields，这里刻意不提供。
function metadata(key = 'alpha'): FormDataMeta {
  return {
    form: { form_key: key, name: key } as FormDataMeta['form'],
    primaryKey: { name: 'code', type: 'string' },
    schema: { schemaVersion: 2, key, title: key, nodes: [node('name'), node('date', 'date')], list: {} }, schemaHash: `${key}-hash`, etag: `${key}-etag`
  } as FormDataMeta;
}
const reply = (code: string) => ({ row: { code, name: code }, children: {} });
type Reply = ReturnType<typeof reply>;
const wrappers: VueWrapper[] = [];
// 只替换表格渲染外壳，宿主 SFC、搜索表单、日期控件及弹窗均使用真实组件。
const TableShell = defineComponent({
  props: ['rows', 'lock', 'schema'],
  setup(_, { slots, expose }) {
    expose({ clearSelection: vi.fn() });
    return () => h('section', [slots.search?.(), slots.toolbar?.(), slots.actions?.({ row: { code: 'A', name: 'A' } })]);
  }
});
async function mountPage(page: typeof DataPage, realCategory = false) {
  const wrapper = mount(page, { global: {
    plugins: [ElementPlus, createI18n({ legacy: false, locale: 'zh', messages: { zh: { table: { search: '查询', reset: '重置' } } } })],
    components: { SearchForm, PageWrapper: defineComponent({ setup: (_, { slots }) => () => h('main', slots.default?.()) }) },
    stubs: { SchemaTablePage: TableShell, ListButtonBar: true, ListSourceTree: !realCategory, ListCategoryPanel: !realCategory }
  } });
  wrappers.push(wrapper);
  await flushPromises();
  return wrapper;
}
const handlers = (wrapper: VueWrapper) => wrapper.findComponent(ListButtonBar).props('handlers');
const rendererValues = (wrapper: VueWrapper) => wrapper.findComponent(SchemaRenderer).props('values');

for (const source of ['leftTree'] as const) {
  it(`published 真实 ${source} 渲染分类动作并传递筛选、节点、来源版本及行上下文`, async () => {
    const meta = metadata();
    meta.schema.list = {
      category: { enabled: true, field: 'category' },
      buttons: {
        categoryToolbar: [{ id: 'category_refresh', label: '刷新分类', action: { type: 'refresh' } }],
        categoryNode: [{ id: 'category_copy', label: '复制分类', action: { type: 'copy', resource: 'category_copy' } }]
      },
      ...(source === 'leftTree' ? { leftTree: { enabled: true, source: { type: 'module' as const, module: 'categories' }, mapping: { valueField: 'code', labelField: 'name', targetField: 'category' } } } : {})
    };
    meta.categoryOptions = [{ label: '分类 A', value: 'a' }];
    vi.mocked(formDataApi.meta).mockResolvedValue(meta);
    vi.mocked(formDataApi.leftTree).mockResolvedValue({ nodes: [{ id: 'node-a', value: 'a', label: '分类 A', parent: null }], sourceKey: 'categories', schemaHash: 'source-hash', actions: {} });
    const wrapper = await mountPage(PublishedPage, true);
    expect(wrapper.findComponent(source === 'category' ? ListCategoryPanel : ListSourceTree).exists()).toBe(true);
    if (source === 'leftTree') expect(wrapper.findComponent(ListCategoryPanel).exists()).toBe(false);
    const bar = (location: string) => wrapper.findAllComponents(ListButtonBar).find(item => item.props('context')?.location === location)!;
    expect(bar('categoryToolbar')).toBeDefined();
    expect(bar('categoryNode')).toBeDefined();
    expect(bar('categoryToolbar').props('buttons')[0].id).toBe('category_refresh');
    expect(bar('categoryNode').props('buttons')[0].id).toBe('category_copy');
    state(wrapper).filters.name = '筛选';
    await nextTick();
    const filter = { name: '筛选', __leftTree: [] };
    expect(bar('categoryToolbar').props('context')).toMatchObject({ formKey: 'alpha', schemaHash: 'alpha-hash', ids: [], filter });
    expect(bar('categoryNode').props('context')).toMatchObject({ category: { id: source === 'category' ? 'a' : 'node-a' }, filter });
    expect(bar('categoryNode').props('row')).toMatchObject({ id: source === 'category' ? 'a' : 'node-a', label: '分类 A' });
    expect(bar('row').props('row')).toEqual({ code: 'A', name: 'A' });
    expect(bar('row').props('context')).toMatchObject({ ids: ['A'], filter });
    expect(bar('categoryNode').props('lock')).toBe(bar('row').props('lock'));
    if (source === 'leftTree') {
      expect(bar('categoryNode').props('context')).toMatchObject({ sourceSchemaHash: 'source-hash', sourceKey: 'categories' });
      expect(bar('categoryToolbar').props('context')).toMatchObject({ sourceSchemaHash: 'source-hash' });
    } else {
      // 枚举分类没有来源表，不能伪造来源 schemaHash 或调用主表编辑。
      expect(bar('categoryNode').props('context').sourceSchemaHash).toBeUndefined();
      expect(bar('categoryNode').props('handlers').edit).toBeUndefined();
      await wrapper.findComponent(ListCategoryPanel).findAll('button')[1]!.trigger('click');
      expect(bar('categoryToolbar').props('context').filter.__category).toBe('a');
    }
    expect(wrapper.find('button button').exists()).toBe(false);
  });
}
it('published 静态分类仅筛选，不冒充业务记录或渲染分类写动作', async () => {
  const meta = metadata();
  meta.schema.list = { category: { enabled: true, field: 'category' }, buttons: { categoryToolbar: [{ id: 'create', label: '新增分类', action: { type: 'builtin', key: 'create' } }], categoryNode: [{ id: 'edit', label: '编辑分类', action: { type: 'builtin', key: 'edit' } }] } };
  meta.categoryOptions = [{ label: '分类 A', value: 'a' }];
  vi.mocked(formDataApi.meta).mockResolvedValue(meta);
  const wrapper = await mountPage(PublishedPage, true);
  expect(wrapper.findAllComponents(ListButtonBar).filter(bar => String(bar.props('context')?.location ?? '').startsWith('category'))).toHaveLength(0);
  await wrapper.findComponent(ListCategoryPanel).findAll('button')[1]!.trigger('click');
  expect(state(wrapper).filters.__category).toBe('a');
});

it('published 复制新增只使用安全详情预填，保存调用 create 而非 update', async () => {
  const wrapper = await mountPage(PublishedPage);
  expect(wrapper.findAllComponents(ListButtonBar).find(bar => bar.props('context')?.location === 'row')!.props('handlers').copyCreate).toBeTypeOf('function');
  await wrapper.findAllComponents(ListButtonBar).find(bar => bar.props('context')?.location === 'row')!.props('handlers').copyCreate({ code: 'A', name: '不可信列表值' });
  expect(formDataApi.detail).toHaveBeenLastCalledWith('alpha', 'A', true, 'alpha-hash');
  expect(state(wrapper).editingId).toBeNull();
  expect(state(wrapper).dialogValues.name).toBe('A');
  await nextTick();
  await state(wrapper).saveRow();
  expect(formDataApi.create).toHaveBeenCalled();
  expect(formDataApi.update).not.toHaveBeenCalled();
});
// 仅观察/切换真实 setup 状态，不替换或复制宿主函数。
const state = (wrapper: VueWrapper) => (wrapper.vm.$ as unknown as { setupState: Record<string, any> }).setupState;

beforeEach(() => {
  vi.resetAllMocks();
  mocks.route = reactive({ params: { key: 'alpha', formKey: 'alpha' }, meta: {}, query: {} });
  vi.mocked(formDataApi.meta).mockImplementation(async key => metadata(key));
  vi.mocked(formDataApi.index).mockResolvedValue({ list: [{ code: 'A' }, { code: 'B' }], total: 2 });
  vi.mocked(formDataApi.detail).mockImplementation(async (_, id) => reply(String(id)));
  vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm');
  mocks.submit.mockResolvedValue(undefined);
});
afterEach(() => {
  wrappers.splice(0).forEach(wrapper => wrapper.unmount());
  vi.restoreAllMocks();
});

for (const [name, page] of [['data', DataPage], ['published', PublishedPage]] as const) {
  describe(`${name} 真实页面宿主生命周期`, () => {
    it('重置同时清空日期控件与实际请求筛选，页码回到 1', async () => {
      const wrapper = await mountPage(page);
      const picker = wrapper.findComponent(ElDatePicker);
      picker.vm.$emit('update:modelValue', ['2026-09-01', '2026-09-14']);
      (picker.vm.$attrs.onChange as () => void)();
      state(wrapper).query.page = 3;
      await nextTick();
      expect(picker.props('modelValue')).toEqual(['2026-09-01', '2026-09-14']);
      await wrapper.findComponent(SearchForm).findAll('button').find(button => button.text() === '查询')!.trigger('click');
      expect(formDataApi.index).toHaveBeenLastCalledWith('alpha', expect.objectContaining({ filters: { date_from: '2026-09-01', date_to: '2026-09-14', __leftTree: [] } }));
      await wrapper.findComponent(SearchForm).findAll('button').find(button => button.text() === '重置')!.trigger('click');
      await flushPromises();
      expect(picker.props('modelValue')).toBe('');
      expect(state(wrapper).dateFilters).toEqual({});
      expect(picker.findAll('input').map(input => input.element.value)).toEqual(['', '']);
      expect(formDataApi.index).toHaveBeenLastCalledWith('alpha', expect.objectContaining({ page: 1, filters: { __leftTree: [] } }));
    });

    it('只读 schema 节点：分组内字段渲染列表列与筛选输入', async () => {
      const meta = metadata();
      const nested: FormSchemaNode = { id: 'node_title', kind: 'field', type: 'input', field: 'title', title: '标题', children: [], database: { columnType: 'varchar' }, list: { show: true, filter: 'like' } };
      meta.schema.nodes = [{ id: 'group_main', kind: 'layout', type: 'group', title: '基础信息', children: [nested] }];
      vi.mocked(formDataApi.meta).mockResolvedValue(meta);
      const wrapper = await mountPage(page);
      expect(wrapper.findComponent(TableShell).props('schema').columns).toEqual(expect.arrayContaining([expect.objectContaining({ key: 'title', prop: 'title', label: '标题', slot: 'title' })]));
      const item = wrapper.findComponent(SearchForm).findAll('.el-form-item').find(entry => entry.find('label').text() === '标题');
      expect(item).toBeDefined();
      await item!.find('input').setValue('关键字');
      await wrapper.findComponent(SearchForm).findAll('button').find(button => button.text() === '查询')!.trigger('click');
      expect(formDataApi.index).toHaveBeenLastCalledWith('alpha', expect.objectContaining({ filters: { title: '关键字', __leftTree: [] } }));
      expect(wrapper.findComponent(FormDataImportDialog).props('fields')).toEqual([nested]);
    });

    for (const mode of ['edit', 'detail'] as const) {
      const visible = mode === 'edit' ? 'dialogVisible' : 'detailVisible';
      const content = (wrapper: VueWrapper) => mode === 'edit' ? state(wrapper).dialogValues : state(wrapper).detail?.row;
      it(`${mode} A 慢 B 快，迟到 A 不覆盖 B，编辑主键也保持 B`, async () => {
        const wrapper = await mountPage(page);
        const slow = deferred<Reply>();
        vi.mocked(formDataApi.detail).mockImplementation((_, id) => id === 'A' ? slow.promise : Promise.resolve(reply('B')));
        const pending = handlers(wrapper)[mode]({ code: 'A' });
        await handlers(wrapper)[mode]({ code: 'B' });
        slow.resolve(reply('A'));
        await pending;
        await nextTick();
        expect(content(wrapper)?.name).toBe('B');
        expect(state(wrapper)[visible]).toBe(true);
        if (mode === 'edit') { expect(state(wrapper).editingId).toBe('B'); expect(rendererValues(wrapper).name).toBe('B'); }
      });
      for (const interruption of ['关闭', '关闭再打开', '切换表单并返回', 'schemaHash 改变并恢复', '卸载'] as const) {
        it(`${mode} 请求期间${interruption}，旧响应不覆盖或重新打开`, async () => {
          const wrapper = await mountPage(page);
          await handlers(wrapper)[mode]({ code: 'B' });
          const slow = deferred<Reply>();
          vi.mocked(formDataApi.detail).mockReturnValue(slow.promise);
          const pending = handlers(wrapper)[mode]({ code: 'A' });
          const before = JSON.stringify(content(wrapper));
          if (interruption.startsWith('关闭')) {
            const host = mode === 'edit'
              ? wrapper.findAllComponents(ElDialog).find(dialog => dialog.props('modelValue') === true)
              : wrapper.findComponent(ElDrawer);
            host!.vm.$emit('update:modelValue', false);
            if (interruption === '关闭再打开') state(wrapper)[visible] = true;
          } else if (interruption === '切换表单并返回') {
            const params = mocks.route.params as Record<string, string>;
            params.key = params.formKey = 'beta';
            await flushPromises();
            params.key = params.formKey = 'alpha';
            await flushPromises();
          } else if (interruption === 'schemaHash 改变并恢复') {
            state(wrapper).meta.schemaHash = 'new-hash';
            state(wrapper).meta.schemaHash = 'alpha-hash';
          } else wrapper.unmount();
          const expectedVisible = state(wrapper)[visible];
          slow.resolve(reply('A'));
          await pending;
          await nextTick();
          expect(JSON.stringify(content(wrapper))).toBe(before);
          expect(state(wrapper)[visible]).toBe(expectedVisible);
        });
      }
    }

    for (const phase of ['校验', '请求'] as const) {
      for (const interruption of ['关闭重开', '版本往返', '卸载'] as const) {
        it(`保存${phase}期间${interruption}，旧任务不得提交或关闭新宿主`, async () => {
          const wrapper = await mountPage(page);
          await handlers(wrapper).edit({ code: 'B' });
          await nextTick();
          const slow = deferred<void>();
          if (phase === '校验') mocks.submit.mockReturnValueOnce(slow.promise);
          else vi.mocked(formDataApi.update).mockReturnValueOnce(slow.promise as never);
          const pending = (name === 'data' ? state(wrapper).onSave : state(wrapper).saveRow)();
          await flushPromises();
          if (interruption === '关闭重开') {
            state(wrapper).dialogVisible = false;
            state(wrapper).dialogVisible = true;
          } else if (interruption === '版本往返') {
            state(wrapper).meta.schemaHash = 'new-hash';
            state(wrapper).meta.schemaHash = 'alpha-hash';
          } else wrapper.unmount();
          const calls = vi.mocked(formDataApi.index).mock.calls.length;
          slow.resolve();
          await pending;
          if (phase === '校验') expect(formDataApi.update).not.toHaveBeenCalled();
          expect(state(wrapper).dialogVisible).toBe(true);
          expect(formDataApi.index).toHaveBeenCalledTimes(calls);
          expect(state(wrapper).buttonLock.busy).toBe(false);
        });
      }
    }

    it('普通元数据加载切走再返回，旧响应不得覆盖或触发列表请求', async () => {
      const wrapper = await mountPage(page);
      const slow = deferred<FormDataMeta>();
      vi.mocked(formDataApi.meta).mockReturnValueOnce(slow.promise);
      const pending = state(wrapper).loadMeta();
      const params = mocks.route.params as Record<string, string>;
      params.key = params.formKey = 'beta';
      await flushPromises();
      params.key = params.formKey = 'alpha';
      await flushPromises();
      slow.resolve({ ...metadata(), schemaHash: 'stale-hash' });
      await pending;
      expect(state(wrapper).meta.schemaHash).toBe('alpha-hash');
    });

    it('写入成功但刷新失败时 create/update/remove 只执行一次、关闭宿主、释放共享锁且不设置保存字段错误', async () => {
      const warning = vi.spyOn(ElMessage, 'warning');
      const wrapper = await mountPage(page);
      const setFieldErrors = () => wrapper.findComponent(SchemaRenderer).vm.$.exposed?.setFieldErrors as ReturnType<typeof vi.fn>;
      const runSave = async (kind: 'create' | 'update') => {
        if (kind === 'create') await handlers(wrapper).create();
        else await handlers(wrapper).edit({ code: 'B' });
        await nextTick();
        const refreshCalls = vi.mocked(formDataApi.index).mock.calls.length;
        warning.mockClear();
        vi.mocked(formDataApi.index).mockRejectedValueOnce(new Error('refresh failed'));
        await (name === 'data' ? state(wrapper).onSave : state(wrapper).saveRow)();
        await flushPromises();
        expect(formDataApi[kind]).toHaveBeenCalledTimes(1);
        expect(formDataApi.index).toHaveBeenCalledTimes(refreshCalls + 1);
        expect(warning).toHaveBeenCalledExactlyOnceWith('操作已成功，但列表刷新失败，请手动刷新，不要重复提交');
        expect(state(wrapper).dialogVisible).toBe(false);
        expect(wrapper.findComponent(SchemaTablePage).props('lock').busy).toBe(false);
        expect(setFieldErrors()).not.toHaveBeenCalled();
        await (name === 'data' ? state(wrapper).onSave : state(wrapper).saveRow)();
        expect(formDataApi[kind]).toHaveBeenCalledTimes(1);
      };
      await runSave('create');
      await runSave('update');
      const refreshCalls = vi.mocked(formDataApi.index).mock.calls.length;
      warning.mockClear();
      vi.mocked(formDataApi.index).mockRejectedValueOnce(new Error('refresh failed'));
      await handlers(wrapper).delete({ code: 'A' });
      await flushPromises();
      expect(ElMessageBox.confirm).toHaveBeenCalledExactlyOnceWith('确认删除该条数据？', '删除确认', { type: 'warning' });
      expect(formDataApi.remove).toHaveBeenCalledExactlyOnceWith('alpha', 'A', 'alpha-hash');
      expect(formDataApi.index).toHaveBeenCalledTimes(refreshCalls + 1);
      expect(wrapper.findComponent(SchemaTablePage).props('lock').busy).toBe(false);
      expect(warning).toHaveBeenCalledExactlyOnceWith('操作已成功，但列表刷新失败，请手动刷新，不要重复提交');
    });

    it('写成功刷新 pending 后切换 formKey，迟到刷新失败不提示', async () => {
      const warning = vi.spyOn(ElMessage, 'warning');
      const wrapper = await mountPage(page);
      const refresh = deferred<{ list: Record<string, unknown>[]; total: number }>();
      vi.mocked(formDataApi.index).mockReturnValueOnce(refresh.promise);
      await handlers(wrapper).create();
      await nextTick();
      const save = name === 'data' ? state(wrapper).onSave : state(wrapper).saveRow;
      const pending = save();
      await flushPromises();
      const params = mocks.route.params as Record<string, string>;
      params.key = params.formKey = 'beta';
      await nextTick();
      refresh.reject(new Error('refresh failed'));
      await pending;
      await flushPromises();
      expect(warning).not.toHaveBeenCalled();
    });

    it('写成功刷新 pending 后卸载，迟到刷新失败不提示', async () => {
      const warning = vi.spyOn(ElMessage, 'warning');
      const wrapper = await mountPage(page);
      const refresh = deferred<{ list: Record<string, unknown>[]; total: number }>();
      vi.mocked(formDataApi.index).mockReturnValueOnce(refresh.promise);
      await handlers(wrapper).create();
      await nextTick();
      const save = name === 'data' ? state(wrapper).onSave : state(wrapper).saveRow;
      const pending = save();
      await flushPromises();
      wrapper.unmount();
      refresh.reject(new Error('refresh failed'));
      await pending;
      await flushPromises();
      expect(warning).not.toHaveBeenCalled();
    });

    it('已有共享锁、异步校验身份检查与保存 hash 保留', async () => {
      const wrapper = await mountPage(page);
      await handlers(wrapper).edit({ code: 'B' });
      await nextTick();
      const validation = deferred<void>();
      mocks.submit.mockReturnValue(validation.promise);
      const save = name === 'data' ? state(wrapper).onSave : state(wrapper).saveRow;
      const pending = save();
      await save();
      expect(mocks.submit).toHaveBeenCalledTimes(1);
      expect(wrapper.findComponent(SchemaTablePage).props('lock').busy).toBe(true);
      state(wrapper).meta.schemaHash = 'new-hash';
      validation.resolve();
      await pending;
      expect(formDataApi.update).not.toHaveBeenCalled();
      expect(wrapper.findComponent(SchemaTablePage).props('lock').busy).toBe(false);
      mocks.submit.mockResolvedValue(undefined);
      await save();
      expect(formDataApi.update).toHaveBeenCalledWith('alpha', 'B', expect.objectContaining({ name: 'B' }), [], 'new-hash');
    });
  });
}

it('published 首次等待 meta 后仍可按真实主键正常编辑', async () => {
  const wrapper = await mountPage(PublishedPage);
  state(wrapper).meta = null;
  await handlers(wrapper).edit({ code: 'B' });
  expect(formDataApi.detail).toHaveBeenLastCalledWith('alpha', 'B');
  expect(state(wrapper).dialogValues.name).toBe('B');
  expect(state(wrapper).editingId).toBe('B');
});

it('published 等待 meta 时切走再返回，旧 meta 不覆盖当前版本', async () => {
  const wrapper = await mountPage(PublishedPage);
  state(wrapper).meta = null;
  const slow = deferred<FormDataMeta>();
  vi.mocked(formDataApi.meta).mockReturnValueOnce(slow.promise);
  const pending = handlers(wrapper).edit({ code: 'A' });
  const params = mocks.route.params as Record<string, string>;
  params.formKey = 'beta';
  await flushPromises();
  params.formKey = 'alpha';
  await flushPromises();
  slow.resolve({ ...metadata(), schemaHash: 'stale-hash' });
  await pending;
  expect(state(wrapper).meta.schemaHash).toBe('alpha-hash');
  expect(formDataApi.detail).not.toHaveBeenCalled();
});

it('published 等待 meta 时切 formKey，旧编辑不得向新表单请求详情', async () => {
  const wrapper = await mountPage(PublishedPage);
  state(wrapper).meta = null;
  const slow = deferred<FormDataMeta>();
  vi.mocked(formDataApi.meta).mockImplementation(key => key === 'alpha' ? slow.promise : Promise.resolve(metadata(key)));
  const pending = handlers(wrapper).edit({ code: 'A' });
  const params = mocks.route.params as Record<string, string>;
  params.formKey = 'beta';
  await flushPromises();
  slow.resolve(metadata());
  await pending;
  expect(formDataApi.detail).not.toHaveBeenCalled();
  expect(state(wrapper).dialogVisible).toBe(false);
  expect(state(wrapper).meta.form.form_key).toBe('beta');
});
