import { defineComponent, nextTick } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
  permissions: ['*'] as string[],
  createVisual: vi.fn(),
  createFromDatabase: vi.fn(),
  inspectDatabase: vi.fn(),
  databaseTables: vi.fn(),
  confirm: vi.fn(),
  targets: vi.fn(),
  query: {} as Record<string, string>,
  push: vi.fn(),
  onBeforeRouteLeave: vi.fn()
}));

vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ get permissions() { return mocks.permissions; } }) }));

vi.mock('@/api/development/business', async (importOriginal) => ({
  ...await importOriginal<typeof import('@/api/development/business')>(),
  businessDevelopmentApi: { createVisual: mocks.createVisual, targets: mocks.targets, createFromDatabase: mocks.createFromDatabase, inspectDatabase: mocks.inspectDatabase, databaseTables: mocks.databaseTables }
}));
vi.mock('element-plus', async (importOriginal) => ({
  ...await importOriginal<typeof import('element-plus')>(),
  ElMessageBox: { confirm: mocks.confirm }
}));
vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal<typeof import('vue-router')>();
  return {
    ...actual,
    useRouter: () => ({ push: mocks.push }),
    useRoute: () => ({ query: mocks.query }),
    onBeforeRouteLeave: mocks.onBeforeRouteLeave
  };
});

import BusinessVisual from './visual.vue';
import visualSource from './visual.vue?raw';

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
  return { promise, resolve, reject };
}

const ElInput = defineComponent({
  props: ['modelValue', 'ariaDescribedby'],
  emits: ['update:modelValue', 'blur'],
  template: '<input :value="modelValue" :aria-describedby="ariaDescribedby" @input="$emit(\'update:modelValue\', $event.target.value)" @blur="$emit(\'blur\')" />'
});
const ElButton = defineComponent({
  props: ['loading', 'disabled', 'type'],
  emits: ['click'],
  template: '<button type="button" :disabled="disabled || loading" :data-loading="String(Boolean(loading))" @click="$emit(\'click\')"><slot /></button>'
});
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
const PageWrapper = defineComponent({ template: '<main><slot /></main>' });
const ElCard = defineComponent({ template: '<section><slot /></section>' });
const ElFormItem = defineComponent({ props: ['label', 'prop'], template: '<label :data-prop="prop"><slot /></label>' });

function render(initialMode?: 'created' | 'adopted') {
  const focus = vi.fn();
  const formApi = {
    validate: vi.fn().mockResolvedValue(true),
    validateField: vi.fn().mockResolvedValue(true),
    clearValidate: vi.fn(),
    scrollToField: vi.fn(),
    fields: ['name', 'code', 'table', 'connection', 'remark'].map((prop) => ({
      prop,
      validateState: '',
      validateMessage: '',
      $el: { querySelector: () => ({ focus }) }
    }))
  };
  const ElForm = defineComponent({
    props: ['model', 'rules', 'labelPosition'],
    setup(_props, { expose }) { expose(formApi); },
    template: '<form :data-label-position="labelPosition"><slot /></form>'
  });
  const wrapper = mount(BusinessVisual, {
    props: { initialMode },
    global: { stubs: { ElRadioGroup: { props: ['modelValue', 'disabled'], emits: ['update:modelValue'], template: `<div role="radiogroup"><input v-for="mode in ['created', 'adopted']" :key="mode" type="radio" :data-mode="mode" :checked="modelValue === mode" :disabled="disabled" @change="$emit('update:modelValue', mode)" /></div>` }, ElRadioButton: true, ElSelect: { template: '<div><slot /></div>' }, ElOption: { props: ['label', 'disabled', 'value'], template: '<option :value="value" :disabled="disabled">{{ label }}</option>' }, ElResult: true, ElAlert: true, ElTable: true, ElTableColumn: true, ElEmpty: true, ElSkeleton: true, PageWrapper, ElCard, ElForm, ElFormItem, ElInput, ElButton } }
  });
  return { wrapper, formApi, focus };
}

function inputAt(wrapper: ReturnType<typeof render>['wrapper'], index: number) {
  return wrapper.findAll('input:not([type="radio"])')[index]!;
}

async function fillRequired(wrapper: ReturnType<typeof render>['wrapper']) {
  await inputAt(wrapper, 0).setValue('客户订单');
  await inputAt(wrapper, 1).setValue('customer_order');
}

describe('BusinessVisual', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocks.query = {};
    mocks.permissions = ['*'];
    mocks.databaseTables.mockResolvedValue([{ name: 'fun_existing', comment: '已有业务' }]);
    mocks.confirm.mockResolvedValue('confirm');
    mocks.inspectDatabase.mockResolvedValue({ connection: 'mysql', table: 'fun_existing', fields: [{ name: 'id' }], primaryKey: ['id'], snapshotHash: 'snapshot-1', indexes: [], observedAt: '' });
    mocks.targets.mockResolvedValue({ list: [{ type: 'core', pluginCode: null, name: '核心后台', scope: 'console' }, { type: 'plugin', pluginCode: 'demo', name: '演示', scope: 'console' }], defaultConnection: 'mysql', migrationPath: 'database/migrations' });
    vi.stubGlobal('confirm', vi.fn().mockReturnValue(false));
  });

  it('统一命名并支持旧入口的已有表初始模式', async () => {
    const { wrapper } = render('adopted');
    await flushPromises();
    expect(wrapper.find('[data-prop="existingTable"]').exists()).toBe(true);
    expect(wrapper.attributes('title')).toBe('创建业务');
    wrapper.unmount();
  });

  it.each([
    ['仅检查', ['console/development.business:inspectdatabase'], true, false, false],
    ['仅创建', ['console/development.business:createvisual'], false, true, false],
    ['仅采纳', ['console/development.business:createfromdatabase'], false, false, true],
    ['只有合并别名', ['development:business:save', 'development:business:inspect'], false, false, false],
    ['无权限', [], false, false, false]
  ] as const)('%s 不从合并别名扩大独立动作权限', async (_label, permissions, inspect, create, adopt) => {
    mocks.permissions = [...permissions];
    const { wrapper } = render('adopted');
    await flushPromises();
    const state = (wrapper.vm as any).$.setupState;
    expect(mocks.targets).not.toHaveBeenCalled();
    expect(wrapper.find('[data-action="inspect"]').exists()).toBe(inspect);
    expect(wrapper.find('[data-action="submit"]').exists()).toBe(adopt);
    state.form.existingTable = 'fun_existing';
    await nextTick();
    await state.loadTables();
    await state.inspect();
    await state.submit();
    expect(mocks.databaseTables).not.toHaveBeenCalled();
    expect(mocks.inspectDatabase).toHaveBeenCalledTimes(inspect ? 1 : 0);
    expect(mocks.createFromDatabase).not.toHaveBeenCalled();
    state.mode = 'created';
    await nextTick();
    expect(wrapper.find('[data-action="submit"]').exists()).toBe(create);
    expect(wrapper.find('[role="alert"]').exists()).toBe(true);
    wrapper.unmount();
  });

  it('过期快照保留输入并要求重新检查，不重复提交', async () => {
    const { wrapper } = render('adopted');
    await flushPromises();
    const state = (wrapper.vm as any).$.setupState;
    state.form.existingTable = 'fun_existing';
    await nextTick();
    await state.inspect();
    mocks.createFromDatabase.mockRejectedValueOnce({ code: 409, msg: '结构已变化', data: { error: { code: 'DATABASE_INSPECTION_STALE', requestId: 'test', retryable: true, details: {} } } });
    await state.submit();
    expect(state.inspection).toBeUndefined();
    expect(state.form.code).toBe('existing');
    expect(wrapper.text()).toContain('数据库结构已变化，请重新检查');
    await state.submit();
    expect(mocks.createFromDatabase).toHaveBeenCalledTimes(1);
    wrapper.unmount();
  });

  it('检查校验期间防双击，卸载后不补写名称标识', async () => {
    const { wrapper, formApi } = render('adopted');
    await flushPromises();
    const state = (wrapper.vm as any).$.setupState;
    state.form.existingTable = 'fun_existing';
    await nextTick();
    const validation = deferred<boolean>();
    const response = deferred<any>();
    formApi.validateField.mockReturnValueOnce(validation.promise);
    mocks.inspectDatabase.mockReturnValueOnce(response.promise);
    const first = state.inspect();
    await state.inspect();
    expect(formApi.validateField).toHaveBeenCalledTimes(1);
    validation.resolve(true);
    await flushPromises();
    expect(mocks.inspectDatabase).toHaveBeenCalledTimes(1);
    wrapper.unmount();
    response.resolve({ connection: 'mysql', table: 'fun_existing', fields: [{ name: 'id' }], primaryKey: ['id'], snapshotHash: 'late' });
    await first;
    expect(state.form.code).toBe('');
    expect(state.inspection).toBeUndefined();
  });

  it('query 指定已有表模式，插件与其他参数不被重写', async () => {
    mocks.query = { plugin: 'demo', mode: 'adopted', source: 'legacy' };
    const { wrapper } = render();
    await flushPromises();
    const state = (wrapper.vm as any).$.setupState;
    expect(state.mode).toBe('adopted');
    expect(state.selected).toBe('demo');
    expect(mocks.query).toEqual({ plugin: 'demo', mode: 'adopted', source: 'legacy' });
    expect(mocks.push).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('检查补空名称标识、展示快照和移动端字段，保留用户输入', async () => {
    const { wrapper } = render('adopted');
    await flushPromises();
    const state = (wrapper.vm as any).$.setupState;
    state.form.existingTable = 'fun_existing';
    await nextTick();
    await state.inspect();
    await flushPromises();
    expect(state.form).toMatchObject({ code: 'existing', name: 'existing' });
    expect(wrapper.get('.snapshot-hash').text()).toBe('snapshot-1');
    expect(wrapper.get('.field-cards').text()).toContain('id');
    expect(wrapper.get('.field-table-scroll').attributes('aria-label')).toBe('检查字段列表');
    state.form.code = 'custom'; state.form.name = '自定义';
    await state.inspect();
    expect(state.form).toMatchObject({ code: 'custom', name: '自定义' });
    wrapper.unmount();
  });

  it('目标请求失败后点击重试恢复创建，保留预选插件和已填内容', async () => {
    mocks.query = { plugin: 'demo' };
    mocks.targets.mockRejectedValueOnce(new Error('目标服务暂不可用'));
    mocks.createVisual.mockResolvedValueOnce({ module: { id: 7, form_id: 11 }, fields: [] });
    const { wrapper } = render();
    await flushPromises();
    await fillRequired(wrapper);
    expect(wrapper.text()).toContain('目标服务暂不可用');
    expect(mocks.createVisual).not.toHaveBeenCalled();
    await wrapper.get('a[href="#"]').trigger('click');
    await flushPromises();
    expect(mocks.targets).toHaveBeenCalledTimes(2);
    expect(wrapper.text()).not.toContain('目标服务暂不可用');
    await wrapper.findAll('button')[0]!.trigger('click');
    expect(mocks.createVisual).toHaveBeenCalledWith(expect.objectContaining({ name: '客户订单', target: { type: 'plugin', pluginCode: 'demo' } }));
    wrapper.unmount();
  });

  it.each([
    ['BUSINESS_TARGET_READ_ONLY', '插件目录或必要文件不可写'],
    ['BUSINESS_TARGET_RECOVERY_LOCKED', '插件存在待恢复操作，请先完成恢复'],
    ['BUSINESS_TARGET_CONSOLE_MISSING', '插件缺少 console 后台目录'],
    ['BUSINESS_TARGET_ADMIN_WEB_MISSING', '插件缺少标准 admin-web 前端目录']
  ])('不可用候选 %s 禁用并展示原因，预选阻止创建', async (code, message) => {
    mocks.query = { plugin: 'demo' };
    mocks.targets.mockResolvedValueOnce({ list: [{ type: 'plugin', pluginCode: 'demo', name: '演示', scope: 'console', available: false, reason: { code, message } }], defaultConnection: 'mysql' });
    const { wrapper } = render();
    await flushPromises();
    await fillRequired(wrapper);
    expect(wrapper.get('option[value="demo"]').attributes('disabled')).toBeDefined();
    expect(wrapper.get('option[value="demo"]').text()).toContain(message);
    expect(wrapper.get('[role="alert"]').text()).toContain(message);
    expect(wrapper.findAll('button')[0]!.attributes('disabled')).toBeDefined();
    expect(mocks.createVisual).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it.each([
    ['BUSINESS_CODE_ALREADY_EXISTS', 'code', '业务标识已存在，请更换业务标识'],
    ['BUSINESS_TABLE_ALREADY_EXISTS', 'table', '新建业务表已存在']
  ])('截图同数据保留受控 payload，并将 %s 定位到正确字段', async (code, field, message) => {
    mocks.query = { plugin: 'example' };
    mocks.targets.mockResolvedValueOnce({ list: [{ type: 'plugin', pluginCode: 'example', name: '示例插件', scope: 'console' }], defaultConnection: 'mysql' });
    mocks.createVisual.mockRejectedValueOnce({ code: 409, msg: message, data: { error: { code, requestId: 'visual-isolated', retryable: false, details: { fieldErrors: { [field]: message } } } } });
    const { wrapper, formApi } = render();
    await flushPromises();
    await inputAt(wrapper, 0).setValue('test');
    await inputAt(wrapper, 1).setValue('test');
    await inputAt(wrapper, 2).setValue('example_test');
    await inputAt(wrapper, 4).setValue('asdf');
    await wrapper.get('[data-action="submit"]').trigger('click');
    await flushPromises();
    expect(mocks.createVisual).toHaveBeenCalledWith({ name: 'test', code: 'test', table: 'example_test', connection: 'mysql', remark: 'asdf', target: { type: 'plugin', pluginCode: 'example' } });
    expect(formApi.fields.find(item => item.prop === field)?.validateMessage).toBe(message);
    expect(formApi.scrollToField).toHaveBeenCalledWith(field);
    expect(mocks.push).not.toHaveBeenCalled();
    expect((inputAt(wrapper, 1).element as HTMLInputElement).value).toBe('test');
    wrapper.unmount();
  });

  it('query 预选插件，使用默认连接与插件表前缀且只发送受控目标', async () => {
    mocks.query = { plugin: 'demo' };
    mocks.createVisual.mockResolvedValue({ module: { id: 7, form_id: 11 }, fields: [] });
    const { wrapper } = render();
    await flushPromises();
    await fillRequired(wrapper);
    await wrapper.findAll('button')[0]!.trigger('click');
    expect(mocks.createVisual).toHaveBeenCalledWith(expect.objectContaining({ target: { type: 'plugin', pluginCode: 'demo' }, table: 'demo_customer_order', connection: 'mysql' }));
    wrapper.unmount();
  });

  it('不可用的 query 插件不能静默回退核心，加载失败可见且阻止创建', async () => {
    mocks.query = { plugin: 'missing' };
    const { wrapper } = render();
    await flushPromises();
    await fillRequired(wrapper);
    expect(wrapper.text()).toContain('不可用或无权限');
    expect(wrapper.findAll('button')[0]!.attributes('disabled')).toBeDefined();
    expect(mocks.createVisual).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('初始空表单不 dirty，首次变化后由同一 guard 保护路由离开和 beforeunload', async () => {
    const { wrapper } = render();
    const guard = mocks.onBeforeRouteLeave.mock.calls[0][0];
    expect(guard()).toBe(true);
    expect(window.confirm).not.toHaveBeenCalled();

    await inputAt(wrapper, 0).setValue('新业务');
    expect(guard()).toBe(false);
    expect(window.confirm).toHaveBeenCalledOnce();
    const event = new Event('beforeunload', { cancelable: true }) as BeforeUnloadEvent;
    window.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
    wrapper.unmount();
  });

  it('取消使用路由导航并受已注册的 dirty guard 统一确认', async () => {
    const { wrapper } = render();
    await inputAt(wrapper, 0).setValue('新业务');
    await wrapper.findAll('button')[1]!.trigger('click');
    expect(mocks.push).toHaveBeenCalledWith('/development/business/mine');
    const guard = mocks.onBeforeRouteLeave.mock.calls[0][0];
    expect(guard()).toBe(false);
  });

  it('submitting 同步防双击并设置创建和取消按钮 loading/disabled', async () => {
    const pending = deferred<{ module: { id: number; form_id: number }; fields: [] }>();
    mocks.createVisual.mockReturnValue(pending.promise);
    const { wrapper } = render();
    await fillRequired(wrapper);
    const create = wrapper.findAll('button')[0]!;
    await create.trigger('click');
    await create.trigger('click');
    expect(mocks.createVisual).toHaveBeenCalledOnce();
    expect(create.attributes('data-loading')).toBe('true');
    expect(create.attributes('disabled')).toBeDefined();
    expect(wrapper.findAll('button')[1]!.attributes('disabled')).toBeDefined();
    pending.resolve({ module: { id: 7, form_id: 11 }, fields: [] });
    await pending.promise;
    await nextTick();
  });

  it('成功时先清除 dirty 再导航到设计器', async () => {
    mocks.createVisual.mockResolvedValue({ module: { id: 7, form_id: 11 }, fields: [] });
    const { wrapper } = render();
    await fillRequired(wrapper);
    mocks.push.mockImplementationOnce(() => {
      const guard = mocks.onBeforeRouteLeave.mock.calls[0][0];
      expect(guard()).toBe(true);
      expect(window.confirm).not.toHaveBeenCalled();
      return Promise.resolve();
    });
    await wrapper.findAll('button')[0]!.trigger('click');
    expect(mocks.push).toHaveBeenCalledWith({
      path: '/development/business/designer',
      query: { id: '11', moduleId: '7' }
    });
  });

  it('失败后保留 dirty，并将 409 冲突聚焦到 code', async () => {
    mocks.createVisual.mockRejectedValue({ code: 409, msg: '业务标识已存在' });
    const { wrapper, formApi, focus } = render();
    await fillRequired(wrapper);
    await wrapper.findAll('button')[0]!.trigger('click');
    await nextTick();
    const codeField = formApi.fields[1]!;
    expect(codeField.validateState).toBe('error');
    expect(codeField.validateMessage).toBe('业务标识已存在');
    expect(formApi.scrollToField).toHaveBeenCalledWith('code');
    expect(focus).toHaveBeenCalled();
    expect(mocks.onBeforeRouteLeave.mock.calls[0][0]()).toBe(false);
  });

  it('消费 HTTP Error details 中的 422 fieldErrors 并映射 Element Plus 字段错误', async () => {
    const error = Object.assign(new Error('参数验证失败'), {
      code: 422,
      details: { fieldErrors: [{ path: 'name', message: '名称已占用' }, { field: 'connection', message: '连接不存在' }] }
    });
    mocks.createVisual.mockRejectedValue(error);
    const { wrapper, formApi } = render();
    await fillRequired(wrapper);
    await wrapper.findAll('button')[0]!.trigger('click');
    await nextTick();
    expect(formApi.fields[0]!.validateMessage).toBe('名称已占用');
    expect(formApi.fields[3]!.validateMessage).toBe('连接不存在');
    expect(formApi.scrollToField).toHaveBeenCalledWith('name');
  });

  async function switchMode(wrapper: ReturnType<typeof render>['wrapper'], mode: string) {
    await wrapper.get(`[data-mode="${mode}"]`).setValue();
    await flushPromises();
  }

  it('分段原地切换保留公共输入，隔离表名并清除旧校验', async () => {
    const { wrapper, formApi } = render();
    await flushPromises();
    const state = (wrapper.vm as any).$.setupState;
    Object.assign(state.form, { name: '订单', code: 'orders', table: 'new_orders', connection: 'archive', remark: '保留备注' });
    await switchMode(wrapper, 'adopted');
    expect(state.form).toMatchObject({ name: '订单', code: 'orders', table: 'new_orders', connection: 'archive', remark: '保留备注' });
    expect(state.form.existingTable).toBe('');
    expect(wrapper.find('[data-prop="table"]').exists()).toBe(false);
    expect(wrapper.find('[data-prop="existingTable"]').exists()).toBe(true);
    expect(formApi.clearValidate).toHaveBeenCalled();
    state.form.existingTable = 'fun_existing';
    await switchMode(wrapper, 'created');
    expect(state.form.table).toBe('new_orders');
    expect(mocks.push).not.toHaveBeenCalled();
    expect(window.confirm).not.toHaveBeenCalled();
    expect(visualSource).toContain('el-radio-button');
    expect(wrapper.text()).not.toContain('改为采纳已有表');
    wrapper.unmount();
  });

  it('采纳使用原检查和创建 API，保留插件边界且不发送新表名', async () => {
    mocks.query = { plugin: 'demo' };
    const { wrapper } = render();
    await flushPromises();
    await fillRequired(wrapper);
    const state = (wrapper.vm as any).$.setupState;
    state.form.table = 'demo_new';
    state.form.remark = '备注';
    await switchMode(wrapper, 'adopted');
    expect(wrapper.text()).toContain('外部依赖');
    state.form.existingTable = 'fun_existing';
    await nextTick();
    await wrapper.get('[data-action="inspect"]').trigger('click');
    await flushPromises();
    mocks.createFromDatabase.mockResolvedValueOnce({ module: { id: 8, form_id: 9 } });
    await wrapper.get('[data-action="submit"]').trigger('click');
    await flushPromises();
    expect(mocks.inspectDatabase).toHaveBeenCalledWith('mysql', 'fun_existing');
    expect(mocks.createFromDatabase).toHaveBeenCalledWith({ name: '客户订单', code: 'customer_order', table: 'fun_existing', connection: 'mysql', remark: '备注', target: { type: 'plugin', pluginCode: 'demo' }, expectedInspectionHash: 'snapshot-1' });
    expect(mocks.createVisual).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('切换丢弃检查结果和迟到响应，重新进入采纳必须重新检查', async () => {
    const pending = deferred<any>();
    mocks.inspectDatabase.mockReturnValueOnce(pending.promise);
    const { wrapper } = render();
    await flushPromises();
    await switchMode(wrapper, 'adopted');
    const state = (wrapper.vm as any).$.setupState;
    state.form.existingTable = 'fun_existing';
    await nextTick();
    await wrapper.get('[data-action="inspect"]').trigger('click');
    await switchMode(wrapper, 'created');
    pending.resolve({ connection: 'mysql', table: 'fun_existing', fields: [{ name: 'id' }], primaryKey: ['id'], snapshotHash: 'old' });
    await flushPromises();
    await switchMode(wrapper, 'adopted');
    expect(wrapper.get('[data-action="submit"]').attributes('disabled')).toBeDefined();
    expect(state.inspection).toBeUndefined();
    expect(mocks.createFromDatabase).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it.each(['connection', 'selected'])('%s 变化使已有表检查失效', async (field) => {
    const { wrapper } = render();
    await flushPromises();
    await switchMode(wrapper, 'adopted');
    const state = (wrapper.vm as any).$.setupState;
    state.form.existingTable = 'fun_existing';
    await nextTick();
    await wrapper.get('[data-action="inspect"]').trigger('click');
    await flushPromises();
    expect(state.canAdopt).toBe(true);
    if (field === 'selected') state.selected = 'missing';
    else state.form.connection = 'archive';
    await nextTick();
    expect(state.inspection).toBeUndefined();
    expect(state.form.existingTable).toBe('');
    expect(wrapper.get('[data-action="submit"]').attributes('disabled')).toBeDefined();
    wrapper.unmount();
  });

  it('已有表列表读取当前连接，切换后丢弃迟到列表和错误', async () => {
    const pending = deferred<any>();
    mocks.databaseTables.mockReturnValueOnce(pending.promise);
    const { wrapper } = render();
    await flushPromises();
    await switchMode(wrapper, 'adopted');
    const state = (wrapper.vm as any).$.setupState;
    state.loadTables();
    expect(mocks.databaseTables).toHaveBeenCalledWith('mysql');
    await switchMode(wrapper, 'created');
    pending.resolve([{ name: 'old_table', comment: '' }]);
    await flushPromises();
    expect(state.tables).toEqual([]);
    expect(state.tableError).toBe('');
    wrapper.unmount();
  });

  it.each([
    { fields: [], primaryKey: ['id'] },
    { fields: [{ name: 'id' }], primaryKey: [] }
  ])('无字段或无主键时禁止采纳', async (schema) => {
    mocks.inspectDatabase.mockResolvedValueOnce({ ...schema, connection: 'mysql', table: 'fun_existing', snapshotHash: 'invalid' });
    const { wrapper } = render();
    await flushPromises();
    await switchMode(wrapper, 'adopted');
    (wrapper.vm as any).$.setupState.form.existingTable = 'fun_existing';
    await nextTick();
    await wrapper.get('[data-action="inspect"]').trigger('click');
    await flushPromises();
    expect(wrapper.get('[data-action="submit"]').attributes('disabled')).toBeDefined();
    expect(mocks.createFromDatabase).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('确认期间锁定模式，连接变化后不得提交旧检查', async () => {
    const pending = deferred<string>();
    mocks.confirm.mockReturnValueOnce(pending.promise);
    const { wrapper } = render();
    await flushPromises();
    await switchMode(wrapper, 'adopted');
    const state = (wrapper.vm as any).$.setupState;
    state.form.existingTable = 'fun_existing';
    await nextTick();
    await wrapper.get('[data-action="inspect"]').trigger('click');
    await flushPromises();
    await wrapper.get('[data-action="submit"]').trigger('click');
    expect(wrapper.get('[data-mode="created"]').attributes('disabled')).toBeDefined();
    state.form.connection = 'archive';
    pending.resolve('confirm');
    await flushPromises();
    expect(mocks.createFromDatabase).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it.each(['.creation-mode', '.form-actions'])('%s 沿用普通按钮尺寸，不覆盖高度和内边距', (selector) => {
    const styles = visualSource.split('<style scoped>')[1]!;
    const blocks = [...styles.matchAll(/([^{}]+)\{([^{}]*)\}/g)]
      .filter(([, selectors]) => selectors!.includes(selector));
    for (const [, , declarations] of blocks) {
      expect(declarations).not.toMatch(/(?:min-height|height|padding(?:-\w+)?)\s*:/);
    }
    expect(visualSource).not.toMatch(/\bsize=["']large["']/);
  });

  it('提供字段说明关联和小屏响应式表单样式', () => {
    const { wrapper } = render();
    expect(inputAt(wrapper, 1).attributes('aria-describedby')).toBe('business-code-help');
    expect(wrapper.get('#business-code-help').text()).toContain('小写字母');
    expect(wrapper.get('form').attributes('data-label-position')).toBe('top');
    expect(wrapper.find('.form-actions').exists()).toBe(true);
    expect(visualSource).toContain('flex-wrap: wrap');
    wrapper.unmount();
  });
});
