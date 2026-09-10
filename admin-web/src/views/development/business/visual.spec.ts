import { defineComponent, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
  createVisual: vi.fn(),
  push: vi.fn(),
  onBeforeRouteLeave: vi.fn()
}));

vi.mock('@/api/development/business', () => ({
  businessDevelopmentApi: { createVisual: mocks.createVisual }
}));
vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal<typeof import('vue-router')>();
  return {
    ...actual,
    useRouter: () => ({ push: mocks.push }),
    onBeforeRouteLeave: mocks.onBeforeRouteLeave
  };
});

import BusinessVisual from './visual.vue';

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
const PageWrapper = defineComponent({ template: '<main><slot /></main>' });
const ElCard = defineComponent({ template: '<section><slot /></section>' });
const ElFormItem = defineComponent({ props: ['label', 'prop'], template: '<label :data-prop="prop"><slot /></label>' });

function render() {
  const focus = vi.fn();
  const formApi = {
    validate: vi.fn().mockResolvedValue(true),
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
    global: { stubs: { PageWrapper, ElCard, ElForm, ElFormItem, ElInput, ElButton } }
  });
  return { wrapper, formApi, focus };
}

function inputAt(wrapper: ReturnType<typeof render>['wrapper'], index: number) {
  return wrapper.findAll('input')[index]!;
}

async function fillRequired(wrapper: ReturnType<typeof render>['wrapper']) {
  await inputAt(wrapper, 0).setValue('客户订单');
  await inputAt(wrapper, 1).setValue('customer_order');
}

describe('BusinessVisual', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('confirm', vi.fn().mockReturnValue(false));
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
      details: { fieldErrors: { name: '名称已占用', connection: ['连接不存在'] } }
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

  it('提供字段说明关联和小屏响应式表单/触控按钮样式', () => {
    const { wrapper } = render();
    expect(inputAt(wrapper, 1).attributes('aria-describedby')).toBe('business-code-help');
    expect(wrapper.get('#business-code-help').text()).toContain('小写字母');
    expect(wrapper.get('form').attributes('data-label-position')).toBe('top');
    expect(wrapper.find('.form-actions').exists()).toBe(true);
    expect(wrapper.find('style').text()).toContain('flex-wrap: wrap');
    expect(wrapper.find('style').text()).toContain('min-height: 44px');
  });
});
