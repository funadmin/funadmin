import { defineComponent, inject, provide, reactive } from 'vue';
import Schema, { type Rules } from 'async-validator';
import { enableAutoUnmount, flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ElementPlus from 'element-plus';

const mocks = vi.hoisted(() => ({
  permissions: ['*'] as string[],
  query: {} as Record<string, string>,
  targets: vi.fn(),
  createVisual: vi.fn(),
  push: vi.fn()
}));

vi.mock('@/api/development/business', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/development/business')>();
  return { ...actual, businessDevelopmentApi: { ...actual.businessDevelopmentApi, targets: mocks.targets, createVisual: mocks.createVisual } };
});
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ get permissions() { return mocks.permissions; } }) }));
vi.mock('vue-router', () => ({ useRoute: () => ({ query: mocks.query }), useRouter: () => ({ push: mocks.push }) }));

import CreateTableDialog from './CreateTableDialog.vue';
import dialogSource from './CreateTableDialog.vue?raw';

// el-form 在 jsdom 下的异步校验不可靠（字段停在 validating），这里用 async-validator 按组件声明的 rules 校验，
// 并按 Element Plus FormItemContext 的形状暴露 fields，覆盖服务端字段错误的回写。
const formKey = Symbol('form');
type FieldStub = { prop: string; validateState: string; validateMessage: string; $el: { querySelector: () => null } };
const ElForm = defineComponent({
  props: ['model', 'rules'],
  setup(props, { expose, slots }) {
    const fields = reactive<FieldStub[]>([]);
    const clearValidate = () => fields.forEach((field) => Object.assign(field, { validateState: '', validateMessage: '' }));
    async function validate() {
      clearValidate();
      try {
        await new Schema(props.rules as Rules).validate({ ...props.model }, { firstFields: true });
        return true;
      } catch (reason) {
        const invalid = (reason as { fields?: Record<string, Array<{ message: string }>> }).fields ?? {};
        for (const [prop, errors] of Object.entries(invalid)) {
          const field = fields.find((item) => item.prop === prop);
          if (field) Object.assign(field, { validateState: 'error', validateMessage: errors[0]?.message ?? '' });
        }
        throw invalid;
      }
    }
    provide(formKey, fields);
    expose({ validate, clearValidate, fields, scrollToField: vi.fn() });
    return () => slots.default?.();
  }
});
const ElFormItem = defineComponent({
  props: ['label', 'prop'],
  setup(props) {
    const fields = inject<FieldStub[]>(formKey, []);
    const field = reactive<FieldStub>({ prop: props.prop, validateState: '', validateMessage: '', $el: { querySelector: () => null } });
    if (props.prop) fields.push(field);
    return { field };
  },
  template: '<div :data-form-item="prop"><span>{{ label }}</span><slot /><em v-if="field.validateState === \'error\'" data-error>{{ field.validateMessage }}</em></div>'
});

const stubs = {
  ElForm,
  ElFormItem,
  ElDialog: defineComponent({
    props: ['modelValue', 'title'],
    emits: ['update:modelValue'],
    template: '<aside v-if="modelValue" role="dialog"><header>{{ title }}</header><slot /><footer><slot name="footer" /></footer></aside>'
  }),
  ElButton: defineComponent({
    inheritAttrs: false,
    props: ['disabled', 'loading', 'type', 'link'],
    emits: ['click'],
    template: '<button v-bind="$attrs" type="button" :disabled="disabled || loading" @click="$emit(\'click\')"><slot /></button>'
  }),
  ElInput: defineComponent({
    inheritAttrs: false,
    props: ['modelValue', 'disabled'],
    emits: ['update:modelValue', 'blur'],
    template: '<input v-bind="$attrs" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" @blur="$emit(\'blur\')" />'
  }),
  ElSelect: defineComponent({
    inheritAttrs: false,
    props: ['modelValue', 'disabled'],
    emits: ['update:modelValue', 'change'],
    template: '<select v-bind="$attrs" :value="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.value)"><slot /></select>'
  }),
  ElOption: defineComponent({ props: ['value', 'label', 'disabled'], template: '<option :value="value" :disabled="disabled">{{ label }}</option>' })
};

const targets = {
  list: [
    { type: 'core', pluginCode: null, scope: 'admin', name: '核心后台', available: true },
    { type: 'plugin', pluginCode: 'cms', scope: 'admin', name: 'CMS', available: true },
    { type: 'plugin', pluginCode: 'shop', scope: 'admin', name: '商城', available: false, reason: { code: 'X', message: '未安装' } }
  ],
  defaultConnection: 'main',
  migrationPath: 'database/migrations'
};

enableAutoUnmount(afterEach);

beforeEach(() => {
  vi.clearAllMocks();
  mocks.permissions = ['*'];
  mocks.query = {};
  mocks.targets.mockResolvedValue(targets);
  mocks.createVisual.mockResolvedValue({ module: { id: 7, form_id: 11 }, fields: [] });
});

async function open(props: Record<string, unknown> = {}) {
  const wrapper = mount(CreateTableDialog, { props: { modelValue: true, ...props }, global: { plugins: [ElementPlus], stubs } });
  await flushPromises();
  return wrapper;
}

const field = (wrapper: VueWrapper, name: string) => wrapper.get(`[data-field="${name}"]`);
const action = (wrapper: VueWrapper, name: string) => wrapper.get(`[data-action="${name}"]`);

async function fill(wrapper: VueWrapper, name: string, code?: string) {
  await field(wrapper, 'name').setValue(name);
  if (code !== undefined) {
    await field(wrapper, 'code').setValue(code);
    await field(wrapper, 'code').trigger('blur');
  }
}

async function submit(wrapper: VueWrapper) {
  await action(wrapper, 'create-submit').trigger('click');
  await flushPromises();
}

describe('CreateTableDialog 新建表', () => {
  it('只保留新建表需要的字段；去掉创建方式和备注', async () => {
    const wrapper = await open();
    expect(wrapper.get('[role="dialog"] header').text()).toBe('新建表');
    for (const name of ['name', 'code', 'connection']) expect(wrapper.find(`[data-field="${name}"]`).exists()).toBe(true);
    expect(wrapper.find('[data-target-card="core"]').exists()).toBe(true);
    expect(wrapper.find('[data-target-card="plugin"]').exists()).toBe(true);
    expect(wrapper.find('[data-table-display]').exists()).toBe(true);
    expect(wrapper.find('[data-table-type="normal"]').exists()).toBe(true);
    expect(wrapper.find('[data-table-type="tree"]').exists()).toBe(true);
    expect(wrapper.text()).not.toContain('创建方式');
    expect(wrapper.text()).not.toContain('备注');
    expect(wrapper.find('textarea').exists()).toBe(false);
  });

  it('业务标识按英文名称建议，手动修改后不再跟随名称；中文名称不建议', async () => {
    const wrapper = await open();
    await field(wrapper, 'name').setValue('Customer Order');
    expect((field(wrapper, 'code').element as HTMLInputElement).value).toBe('customer_order');
    await field(wrapper, 'code').setValue('My-Order');
    await field(wrapper, 'code').trigger('blur');
    expect((field(wrapper, 'code').element as HTMLInputElement).value).toBe('my_order');
    await field(wrapper, 'name').setValue('Another Name');
    expect((field(wrapper, 'code').element as HTMLInputElement).value).toBe('my_order');

    const fresh = await open();
    await field(fresh, 'name').setValue('客户订单');
    expect((field(fresh, 'code').element as HTMLInputElement).value).toBe('');
  });

  it('生成目标默认核心；数据库连接预填服务端默认连接且可输入其他连接', async () => {
    const wrapper = await open();
    expect(wrapper.get('[data-target-card="core"]').attributes('aria-checked')).toBe('true');
    expect(wrapper.get('[data-target-card="plugin"]').attributes('aria-checked')).toBe('false');
    expect(wrapper.find('[data-field="plugin"]').exists()).toBe(false);
    const connection = field(wrapper, 'connection');
    expect((connection.element as HTMLSelectElement).value).toBe('main');
    expect(connection.attributes('allow-create')).toBeDefined();
    expect(connection.attributes('filterable')).toBeDefined();
  });

  it('数据表名默认只读显示（插件目标自动补插件前缀），点击后可改，切换目标恢复自动表名', async () => {
    const wrapper = await open();
    await fill(wrapper, '客户订单', 'customer_order');
    expect(wrapper.get('[data-table-display]').text()).toContain('customer_order');
    expect(wrapper.find('[data-field="table"]').exists()).toBe(false);

    await wrapper.get('[data-target-card="plugin"]').trigger('click');
    await field(wrapper, 'plugin').setValue('cms');
    expect(wrapper.get('[data-table-display]').text()).toContain('cms_customer_order');
    expect(field(wrapper, 'connection').attributes('disabled')).toBeDefined();

    await wrapper.get('[data-table-display]').trigger('click');
    const table = field(wrapper, 'table');
    expect((table.element as HTMLInputElement).value).toBe('cms_customer_order');
    await table.setValue('cms_orders');

    await wrapper.get('[data-target-card="core"]').trigger('click');
    expect(wrapper.find('[data-field="table"]').exists()).toBe(false);
    expect(wrapper.get('[data-table-display]').text()).toContain('customer_order');
    expect(wrapper.get('[data-table-display]').text()).not.toContain('cms_');

    await wrapper.get('[data-target-card="plugin"]').trigger('click');
    await field(wrapper, 'plugin').setValue('cms');
    await wrapper.get('[data-table-display]').trigger('click');
    await field(wrapper, 'table').setValue('cms_orders');
    await submit(wrapper);
    expect(mocks.createVisual).toHaveBeenCalledWith(expect.objectContaining({ table: 'cms_orders', target: { type: 'plugin', pluginCode: 'cms' }, connection: 'main' }));
  });

  it('提交 payload 与原创建向导 createVisual 一致，成功后关闭弹窗并进入表单设计器', async () => {
    const wrapper = await open();
    await fill(wrapper, ' 客户订单 ', 'Customer Order');
    await submit(wrapper);
    expect(mocks.createVisual).toHaveBeenCalledOnce();
    expect(mocks.createVisual).toHaveBeenCalledWith({
      name: '客户订单', code: 'customer_order', table: 'customer_order', connection: 'main', remark: '',
      treeEnabled: false, treeParentField: '', treeSelectionMode: 'single', listConfig: {}, target: { type: 'core' }
    });
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([false]);
    expect(wrapper.emitted('created')?.[0]?.[0]).toEqual({ module: { id: 7, form_id: 11 }, fields: [] });
    expect(mocks.push).toHaveBeenCalledWith({ path: '/admin/development/business/designer', query: { id: '11', moduleId: '7' } });
  });

  it('选择树形表格时随 treeEnabled 提交，并提示创建后在设计器里选择父级字段', async () => {
    const wrapper = await open();
    await fill(wrapper, '分类', 'category');
    await wrapper.get('[data-table-type="tree"] input').setValue(true);
    await flushPromises();
    expect(wrapper.text()).toContain('父级字段');
    await submit(wrapper);
    expect(mocks.createVisual).toHaveBeenCalledWith(expect.objectContaining({ treeEnabled: true, listConfig: {} }));
  });

  it('必填缺失时不提交；插件目标未选择插件时不能提交', async () => {
    const wrapper = await open();
    await submit(wrapper);
    expect(mocks.createVisual).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('请输入业务名称');

    await fill(wrapper, '客户订单', 'customer_order');
    await wrapper.get('[data-target-card="plugin"]').trigger('click');
    expect((field(wrapper, 'plugin').element as HTMLSelectElement).value).toBe('');
    expect(field(wrapper, 'plugin').findAll('option').map((item) => item.attributes('value'))).toEqual(['cms', 'shop']);
    expect(action(wrapper, 'create-submit').attributes('disabled')).toBeDefined();
    await submit(wrapper);
    expect(mocks.createVisual).not.toHaveBeenCalled();
  });

  it('query 预选插件时打开即为插件目标', async () => {
    mocks.query = { plugin: 'cms' };
    const wrapper = await open();
    expect(wrapper.get('[data-target-card="plugin"]').attributes('aria-checked')).toBe('true');
    expect((field(wrapper, 'plugin').element as HTMLSelectElement).value).toBe('cms');
  });

  it('409 冲突落到业务标识字段，不进入设计器且弹窗保持打开', async () => {
    mocks.createVisual.mockRejectedValueOnce({ code: 409, msg: '业务标识已存在' });
    const wrapper = await open();
    await fill(wrapper, '客户订单', 'customer_order');
    await submit(wrapper);
    expect(mocks.push).not.toHaveBeenCalled();
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('业务标识已存在');
  });

  it('提交中防重复点击，取消与关闭被锁定', async () => {
    let resolve!: (value: unknown) => void;
    mocks.createVisual.mockReturnValueOnce(new Promise((done) => { resolve = done; }));
    const wrapper = await open();
    await fill(wrapper, '客户订单', 'customer_order');
    await action(wrapper, 'create-submit').trigger('click');
    await flushPromises();
    await action(wrapper, 'create-submit').trigger('click');
    expect(action(wrapper, 'create-cancel').attributes('disabled')).toBeDefined();
    expect(mocks.createVisual).toHaveBeenCalledOnce();
    resolve({ module: { id: 7, form_id: 11 }, fields: [] });
    await flushPromises();
    expect(mocks.push).toHaveBeenCalledOnce();
  });

  it('没有创建新表权限时提示且不能提交', async () => {
    mocks.permissions = ['development:business:modules'];
    const wrapper = await open();
    expect(wrapper.text()).toContain('没有创建新表权限');
    expect(action(wrapper, 'create-submit').attributes('disabled')).toBeDefined();
  });

  it('重新打开时清空上一次的输入', async () => {
    const wrapper = await open();
    await fill(wrapper, '客户订单', 'customer_order');
    await wrapper.setProps({ modelValue: false });
    await wrapper.setProps({ modelValue: true });
    await flushPromises();
    expect((field(wrapper, 'name').element as HTMLInputElement).value).toBe('');
    expect((field(wrapper, 'code').element as HTMLInputElement).value).toBe('');
  });

  it('使用主题变量并适配暗色与窄屏', () => {
    const styles = dialogSource.split('<style scoped>')[1] ?? '';
    expect(styles).toContain('var(--el-color-primary)');
    expect(styles).toContain('html.dark');
    expect(styles).toMatch(/@media \(max-width: \d+px\)/);
    expect(styles).not.toMatch(/#[0-9a-f]{3,6}\b/i);
    // 主题色 light-* 变量在夜间仍是浅色（见 designer darkModeTokens.spec），改用与 --el-bg-color 混合的主色。
    expect(styles).not.toMatch(/--el-color-primary-light-\d/);
  });
});
