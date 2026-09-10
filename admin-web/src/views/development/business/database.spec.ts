import { defineComponent, nextTick } from 'vue';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DatabasePage from './database.vue';

const mocks = vi.hoisted(() => ({
  inspectDatabase: vi.fn(),
  createFromDatabase: vi.fn(),
  push: vi.fn(),
  confirm: vi.fn(),
  warning: vi.fn(),
  success: vi.fn(),
  routeGuard: undefined as undefined | (() => boolean)
}));

vi.mock('@/api/development/business', () => ({
  businessDevelopmentApi: {
    inspectDatabase: mocks.inspectDatabase,
    createFromDatabase: mocks.createFromDatabase
  },
  isBusinessApiError: (value: unknown) => {
    const candidate = value as { data?: { error?: { code?: unknown } } };
    return typeof candidate?.data?.error?.code === 'string';
  }
}));

vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal<typeof import('vue-router')>();
  return {
    ...actual,
    useRouter: () => ({ push: mocks.push }),
    onBeforeRouteLeave: (guard: () => boolean) => { mocks.routeGuard = guard; }
  };
});

vi.mock('element-plus', () => ({
  ElMessage: { warning: mocks.warning, success: mocks.success },
  ElMessageBox: { confirm: mocks.confirm }
}));

const inspection = {
  connection: 'mysql',
  table: 'fun_orders',
  snapshotHash: 'a'.repeat(64),
  observedAt: '2026-09-10T10:00:00Z',
  primaryKey: ['id'],
  indexes: [{ name: 'PRIMARY', columns: ['id'], unique: true }],
  fields: [
    { name: 'id', label: 'ID', columnType: 'bigint', type: 'number' },
    { name: 'title', label: '标题', columnType: 'varchar(100)', type: 'input' }
  ]
};

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
  return { promise, resolve, reject };
}

const PageWrapper = defineComponent({ template: '<main><slot /></main>' });
const ElCard = defineComponent({ template: '<section><slot /></section>' });
const ElForm = defineComponent({
  props: ['model', 'rules'],
  setup(_, { expose }) {
    expose({ validate: vi.fn().mockResolvedValue(true), validateField: vi.fn().mockResolvedValue(true) });
    return {};
  },
  template: '<form><slot /></form>'
});
const ElFormItem = defineComponent({ props: ['label', 'prop'], template: '<label><slot /></label>' });
const ElInput = defineComponent({
  inheritAttrs: false,
  props: ['modelValue', 'type'],
  emits: ['update:modelValue'],
  template: '<textarea v-if="type === \'textarea\'" v-bind="$attrs" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /><div v-else><input v-bind="$attrs" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /><slot name="append" /></div>'
});
const ElButton = defineComponent({
  props: ['disabled', 'loading', 'type'],
  emits: ['click'],
  template: '<button type="button" :disabled="disabled || loading" @click="$emit(\'click\')"><slot /></button>'
});
const ElAlert = defineComponent({ props: ['title'], template: '<div role="status">{{ title }}</div>' });
const ElTable = defineComponent({ props: ['data'], template: '<div class="field-table" tabindex="0" role="region" aria-label="检查字段列表"><slot /></div>' });
const ElTableColumn = defineComponent({ template: '<span />' });
const BusinessPageState = defineComponent({
  props: ['loading', 'error', 'empty', 'emptyText'],
  template: '<section class="page-state" :data-loading="String(loading)" :data-error="error || \'\'" :data-empty="String(empty)"><slot v-if="!loading && !error && !empty" /></section>'
});

function render(): VueWrapper {
  return mount(DatabasePage, {
    global: {
      stubs: { PageWrapper, ElCard, ElForm, ElFormItem, ElInput, ElButton, ElAlert, ElTable, ElTableColumn, BusinessPageState }
    }
  });
}

async function fillRequired(wrapper: VueWrapper) {
  await wrapper.get('input[name="connection"]').setValue('mysql');
  await wrapper.get('input[name="table"]').setValue('fun_orders');
  await wrapper.get('input[name="name"]').setValue('订单');
  await wrapper.get('input[name="code"]').setValue('orders');
}

async function inspectSuccessfully(wrapper: VueWrapper) {
  mocks.inspectDatabase.mockResolvedValueOnce(inspection);
  await wrapper.get('[data-action="inspect"]').trigger('click');
  await flushPromises();
}

function staleError() {
  return {
    code: 409,
    msg: '数据库结构已变化',
    data: { error: { code: 'DATABASE_INSPECTION_STALE', requestId: 'req-1', retryable: true, details: {} } }
  };
}

describe('数据库采纳页', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocks.routeGuard = undefined;
    mocks.confirm.mockResolvedValue('confirm');
    mocks.push.mockResolvedValue(undefined);
  });

  it('为 name、code、connection、table 配置 Element Plus rules', () => {
    const wrapper = render();
    const rules = wrapper.getComponent(ElForm).props('rules') as Record<string, unknown[]>;
    expect(Object.keys(rules)).toEqual(expect.arrayContaining(['name', 'code', 'connection', 'table']));
    for (const field of ['name', 'code', 'connection', 'table']) expect(rules[field]?.length).toBeGreaterThan(0);
  });

  it('检查与提交状态独立，并同步阻止双击重复请求', async () => {
    const pendingInspection = deferred<typeof inspection>();
    mocks.inspectDatabase.mockReturnValue(pendingInspection.promise);
    const wrapper = render();
    await fillRequired(wrapper);
    const inspectButton = wrapper.get('[data-action="inspect"]');
    await inspectButton.trigger('click');
    await inspectButton.trigger('click');
    expect(mocks.inspectDatabase).toHaveBeenCalledOnce();
    expect(inspectButton.attributes('disabled')).toBeDefined();
    expect(wrapper.get('[data-action="create"]').attributes('disabled')).toBeDefined();
    pendingInspection.resolve(inspection);
    await flushPromises();

    const pendingCreate = deferred<{ module: { id: number; form_id: number } }>();
    mocks.createFromDatabase.mockReturnValue(pendingCreate.promise);
    const createButton = wrapper.get('[data-action="create"]');
    await createButton.trigger('click');
    await flushPromises();
    await createButton.trigger('click');
    expect(mocks.createFromDatabase).toHaveBeenCalledOnce();
    expect(createButton.attributes('disabled')).toBeDefined();
    expect(wrapper.get('[data-action="inspect"]').attributes('disabled')).toBeUndefined();
    pendingCreate.resolve({ module: { id: 8, form_id: 9 } });
    await flushPromises();
  });

  it('检查结果绑定 connection、table、snapshotHash，来源输入变化立即失效', async () => {
    const wrapper = render();
    await fillRequired(wrapper);
    await inspectSuccessfully(wrapper);
    expect(wrapper.text()).toContain(inspection.snapshotHash);
    expect(wrapper.find('.field-table').exists()).toBe(true);

    await wrapper.get('input[name="table"]').setValue('fun_changed');
    expect(wrapper.find('.field-table').exists()).toBe(false);
    expect(wrapper.text()).toContain('请重新检查');
    expect(wrapper.get('[data-action="create"]').attributes('disabled')).toBeDefined();
  });

  it('忽略输入变化前返回的过期检查响应', async () => {
    const pending = deferred<typeof inspection>();
    mocks.inspectDatabase.mockReturnValueOnce(pending.promise);
    const wrapper = render();
    await fillRequired(wrapper);
    await wrapper.get('[data-action="inspect"]').trigger('click');
    await wrapper.get('input[name="connection"]').setValue('reporting');
    pending.resolve(inspection);
    await flushPromises();
    expect(wrapper.find('.field-table').exists()).toBe(false);
    expect(wrapper.text()).toContain('请重新检查');
  });

  it.each([
    [{ ...inspection, fields: [] }, '未识别到字段'],
    [{ ...inspection, primaryKey: [] }, '缺少主键']
  ])('阻断不可采纳结构 %#', async (unsafeInspection, message) => {
    mocks.inspectDatabase.mockResolvedValueOnce(unsafeInspection);
    const wrapper = render();
    await fillRequired(wrapper);
    await wrapper.get('[data-action="inspect"]').trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain(message);
    expect(wrapper.get('[data-action="create"]').attributes('disabled')).toBeDefined();
  });

  it('提交前确认连接、表、主键、字段数及不可变基线影响，并仅发送 expectedInspectionHash', async () => {
    const wrapper = render();
    await fillRequired(wrapper);
    await inspectSuccessfully(wrapper);
    mocks.createFromDatabase.mockResolvedValueOnce({ module: { id: 8, form_id: 9 } });
    await wrapper.get('[data-action="create"]').trigger('click');
    await flushPromises();

    expect(mocks.confirm).toHaveBeenCalledOnce();
    const confirmation = String(mocks.confirm.mock.calls[0][0]);
    for (const text of ['mysql', 'fun_orders', 'id', '2', '不可变']) expect(confirmation).toContain(text);
    expect(mocks.createFromDatabase).toHaveBeenCalledWith({
      connection: 'mysql', table: 'fun_orders', name: '订单', code: 'orders', remark: '', expectedInspectionHash: inspection.snapshotHash
    });
    expect(mocks.createFromDatabase.mock.calls[0][0]).not.toHaveProperty('fields');
  });

  it('DATABASE_INSPECTION_STALE 保留业务输入并要求重新检查', async () => {
    const wrapper = render();
    await fillRequired(wrapper);
    await inspectSuccessfully(wrapper);
    mocks.createFromDatabase.mockRejectedValueOnce(staleError());
    await wrapper.get('[data-action="create"]').trigger('click');
    await flushPromises();

    expect((wrapper.get('input[name="name"]').element as HTMLInputElement).value).toBe('订单');
    expect((wrapper.get('input[name="code"]').element as HTMLInputElement).value).toBe('orders');
    expect(wrapper.find('.field-table').exists()).toBe(false);
    expect(wrapper.text()).toContain('结构已变化，请重新检查');
  });

  it('通过 BusinessPageState 呈现 loading、error、empty、content 并提供 aria-live', async () => {
    const wrapper = render();
    expect(wrapper.get('.page-state').attributes('data-empty')).toBe('true');
    expect(wrapper.find('[aria-live="polite"]').exists()).toBe(true);

    const pending = deferred<typeof inspection>();
    mocks.inspectDatabase.mockReturnValueOnce(pending.promise);
    await fillRequired(wrapper);
    await wrapper.get('[data-action="inspect"]').trigger('click');
    expect(wrapper.get('.page-state').attributes('data-loading')).toBe('true');
    pending.reject(new Error('连接失败'));
    await flushPromises();
    expect(wrapper.get('.page-state').attributes('data-error')).toContain('连接失败');

    await inspectSuccessfully(wrapper);
    expect(wrapper.find('.field-table').exists()).toBe(true);
  });

  it('取消及路由离开均确认 dirty，卸载后不接收未完成请求', async () => {
    const originalConfirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
    const pending = deferred<typeof inspection>();
    mocks.inspectDatabase.mockReturnValueOnce(pending.promise);
    const wrapper = render();
    await fillRequired(wrapper);
    expect(mocks.routeGuard?.()).toBe(false);
    expect(originalConfirm).toHaveBeenCalled();

    await wrapper.get('[data-action="cancel"]').trigger('click');
    expect(mocks.push).not.toHaveBeenCalled();
    originalConfirm.mockClear();
    originalConfirm.mockReturnValue(true);
    mocks.push.mockImplementationOnce(async () => {
      expect(mocks.routeGuard?.()).toBe(true);
    });
    await wrapper.get('[data-action="cancel"]').trigger('click');
    await flushPromises();
    expect(originalConfirm).toHaveBeenCalledOnce();

    mocks.inspectDatabase.mockReturnValueOnce(pending.promise);
    await wrapper.get('[data-action="inspect"]').trigger('click');
    wrapper.unmount();
    pending.resolve(inspection);
    await flushPromises();
    originalConfirm.mockRestore();
  });

  it('字段区域在窄屏切换为卡片，并为横向表格提供可访问名称', async () => {
    const wrapper = render();
    await fillRequired(wrapper);
    await inspectSuccessfully(wrapper);
    expect(wrapper.find('.field-cards').exists()).toBe(true);
    expect(wrapper.get('.field-table-scroll').attributes()).toMatchObject({ tabindex: '0', role: 'region', 'aria-label': '检查字段列表' });
    await nextTick();
  });
});
