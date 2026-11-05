import { defineComponent, h, nextTick } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import ElementPlus from 'element-plus';
import SchemaRenderer from './SchemaRenderer.vue';
import { formatFieldValue } from '../runtime/fieldPresentation';
import { describe, expect, it, vi } from 'vitest';
import FormControlRenderer from './FormControlRenderer.vue';
import type { FormFieldDef } from '@/api/form';
import type { FormDataSourceControlState } from '../dataSource/useFormDataSource';

const selectionTypes = ['select', 'selectV2', 'treeSelect', 'cascader', 'relation', 'dictionary', 'user', 'department'] as const;

const field = (type: string): FormFieldDef => ({
  field_name: 'owner_id', label: '负责人', type, column_type: '', nullable: 1, default_value: '', comment: '', unsigned: 0,
  index_type: 'none', placeholder: '', options_source: {}, control_props: {}, validate_rules: null, link_rules: null,
  relation_type: 'none', relation_table: '', relation_label_field: '', relation_value_field: '', relation_multiple: 0,
  relation_on_delete: 'restrict', list_show: 0, list_sort: 0, list_filter: '', list_formatter: '', list_width: 0,
  form_show: 1, form_required: 0, form_group: '', form_span: 24, form_readonly: 0, sort_order: 0
});

const Selection = defineComponent({
  inheritAttrs: false,
  props: { loading: Boolean, remote: Boolean, filterable: Boolean, remoteMethod: Function },
  setup(props, { attrs, slots }) {
    return () => h('div', {
      ...attrs,
      class: 'selection',
      'data-loading': String(props.loading),
      'data-remote': String(props.remote),
      'data-filterable': String(props.filterable)
    }, slots.default?.());
  }
});
const Alert = defineComponent({
  props: { title: String },
  setup(props, { slots }) { return () => h('div', { role: 'alert' }, [props.title, slots.default?.()]); }
});
const Button = defineComponent({
  emits: ['click'],
  setup(_, { emit, slots }) { return () => h('button', { onClick: () => emit('click') }, slots.default?.()); }
});
const Pagination = defineComponent({
  props: { currentPage: Number, pageSize: Number, total: Number },
  emits: ['current-change'],
  setup(props) {
    return () => h('button', {
      class: 'pagination',
      'data-page': props.currentPage,
      'data-page-size': props.pageSize,
      'data-total': props.total
    }, '下一页');
  }
});

const state = (overrides: Partial<FormDataSourceControlState> = {}): FormDataSourceControlState => ({
  loading: true,
  error: new Error('加载失败'),
  page: 1,
  pageSize: 20,
  total: 41,
  searchable: true,
  paginated: true,
  search: vi.fn(),
  setPage: vi.fn(),
  retry: vi.fn(async () => undefined),
  ...overrides
});

const mountControl = (type: string, dataSourceState?: FormDataSourceControlState) => mount(FormControlRenderer, {
  props: { field: field(type), dataSourceState, options: [{ label: '用户', value: 1 }] },
  global: {
    config: { warnHandler: () => undefined },
    stubs: {
      ElSelect: Selection, ElSelectV2: Selection, ElTreeSelect: Selection, ElCascader: Selection,
      ElOption: defineComponent({ setup() { return () => h('span'); } }), ElAlert: Alert, ElButton: Button,
      ElPagination: Pagination, Upload: true,
      ElText: defineComponent({ template: '<span class="readonly-value"><slot /></span>' })
    }
  }
});

describe('真实选择控件标签与值语义', () => {
  const options = [{ label: '启用', value: 1 }, { label: '停用', value: '0' },
    { label: '父', value: 'p', children: [{ label: '子', value: 'c' },
      { label: '禁用组', value: 'g', disabled: true, children: [{ label: '不可选', value: 'x' }] }] }];
  it.each([
    { value: '1', label: '启用' }, { value: 0, label: '停用' },
    { value: ['c', '1', 0], label: '子, 启用, 停用' }
  ])('编辑 $value 展示标签，打开与保存不转换原始类型', async ({ value, label }) => {
    const values = { status: value };
    const wrapper = mount(SchemaRenderer, {
      props: { schema: { schemaVersion: 2, key: 'labels', title: '标签', nodes: [{
        id: 'status', kind: 'field', field: 'status', title: '状态', type: 'select', children: [],
        props: { multiple: Array.isArray(value) }, dataSource: { kind: 'static', options }
      }] }, values }, global: { plugins: [ElementPlus] }
    });
    try {
      await flushPromises();
      await vi.waitFor(() => expect(wrapper.find('.el-select').exists()).toBe(true));
      await flushPromises();
      const labels = wrapper.findAll('.el-select__selected-item')
        .filter(node => !node.classes().includes('el-select__input-wrapper') && !node.classes().includes('is-transparent'))
        .map(node => node.text()).filter(Boolean).join(', ');
      expect.soft(labels).toBe(label);
      const save = vi.fn(() => ({ ...values }));
      await wrapper.vm.submit(save);
      expect(save).toHaveReturnedWith({ status: value });
      expect(wrapper.emitted('change')).toBeUndefined();
      expect(values.status).toEqual(value);
    } finally { wrapper.unmount(); }
  });

  it('嵌套分组保留 disabled，主动点击提交选项的原始值', async () => {
    const wrapper = mount(FormControlRenderer, {
      props: { field: field('select'), modelValue: '1', options },
      global: { plugins: [ElementPlus] }, attachTo: document.body
    });
    try {
      await flushPromises();
      await wrapper.get('.el-select__wrapper').trigger('click');
      await flushPromises();
      const items = () => Array.from(document.querySelectorAll<HTMLElement>('.el-select-dropdown__item'));
      const disabled = items().find(node => node.textContent === '不可选');
      expect.soft(disabled?.classList.contains('is-disabled')).toBe(true);
      disabled?.click();
      expect(wrapper.emitted('update:modelValue')).toBeUndefined();
      items().find(node => node.textContent === '停用')!.click();
      await flushPromises();
      expect(wrapper.emitted('update:modelValue')).toEqual([['0']]);
      await wrapper.setProps({ modelValue: '0' });
      await wrapper.get('.el-select__wrapper').trigger('click');
      await flushPromises();
      items().find(node => node.textContent === '启用')!.click();
      await flushPromises();
      expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([1]);
    } finally { wrapper.unmount(); }
  });

  it('迟到的嵌套选项更新标签但不回写模型，父选项和分组均保留', async () => {
    const wrapper = mount(FormControlRenderer, {
      props: { field: field('select'), modelValue: '2', options: [] }, global: { plugins: [ElementPlus] }
    });
    try {
      await flushPromises();
      expect(wrapper.get('.el-select__selected-item:not(.el-select__input-wrapper)').text()).toBe('2');
      const nested = [{ label: '父', value: 'p', children: [{ label: '子', value: 2, disabled: true }] }];
      await wrapper.setProps({ options: nested });
      await flushPromises();
      expect(wrapper.get('.el-select__selected-item:not(.el-select__input-wrapper)').text()).toBe('子');
      expect(wrapper.findAllComponents({ name: 'ElOptionGroup' }).map(group => group.props('label'))).toContain('父');
      expect(wrapper.findAllComponents({ name: 'ElOption' }).map(option => option.props('value'))).toEqual(['p', 2]);
      expect(wrapper.findAllComponents({ name: 'ElOption' })[1]!.props('disabled')).toBe(true);
      expect(nested[0]!.children[0]!.value).toBe(2);
      expect(wrapper.props('modelValue')).toBe('2');
      expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    } finally { wrapper.unmount(); }
  });

  it('精确类型优先，兼容匹配不吞并 boolean 或未知值', async () => {
    const mixed = [{ label: '数字', value: 1 }, { label: '字符串', value: '1' }];
    expect(formatFieldValue('1', mixed)).toBe('字符串');
    expect(formatFieldValue(true, [{ label: '字符串真', value: 'true' }])).toBe('true');
    const wrapper = mount(FormControlRenderer, { props: { field: field('select'), modelValue: '1', options: mixed }, global: { plugins: [ElementPlus] } });
    try {
      await flushPromises();
      expect(wrapper.get('.el-select__selected-item:not(.el-select__input-wrapper)').text()).toBe('字符串');
      await wrapper.setProps({ modelValue: 'missing' });
      await flushPromises();
      expect(wrapper.get('.el-select__selected-item:not(.el-select__input-wrapper)').text()).toBe('missing');
      expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    } finally { wrapper.unmount(); }
  });
});

describe('数据源选择控件 UI', () => {
  it.each(selectionTypes)('%s 接入 loading、远程搜索、错误重试和分页', async (type) => {
    const dataSourceState = state();
    const wrapper = mountControl(type, dataSourceState);
    const selection = wrapper.get('.selection');

    expect(selection.attributes('data-loading')).toBe('true');
    expect(selection.attributes('data-remote')).toBe('true');
    expect(selection.attributes('data-filterable')).toBe('true');
    const remoteMethod = wrapper.findComponent(Selection).props('remoteMethod') as (keyword: string) => void;
    remoteMethod('张');
    expect(dataSourceState.search).toHaveBeenCalledWith('张');
    expect(wrapper.get('[role="alert"]').text()).toContain('加载失败');
    await wrapper.get('[role="alert"] button').trigger('click');
    expect(dataSourceState.retry).toHaveBeenCalledTimes(1);
    expect(wrapper.get('.pagination').attributes()).toMatchObject({ 'data-page': '1', 'data-page-size': '20', 'data-total': '41' });
    wrapper.findComponent(Pagination).vm.$emit('current-change', 2);
    await nextTick();
    expect(dataSourceState.setPage).toHaveBeenCalledWith(2);
  });

  it('未提供数据源状态时兼容旧 SchemaForm 且不启用远程能力', () => {
    const wrapper = mountControl('select');
    expect(wrapper.get('.selection').attributes('data-loading')).toBe('false');
    expect(wrapper.get('.selection').attributes('data-remote')).toBe('false');
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    expect(wrapper.find('.pagination').exists()).toBe(false);
  });

  it('错误对象和字符串都转换为可读提示', () => {
    const wrapper = mountControl('select', state({ error: '服务不可用' }));
    expect(wrapper.get('[role="alert"]').text()).toContain('服务不可用');
  });

  it.each([...selectionTypes, 'readonly'])('%s 只读时展示选项标签且响应选项更新', async (type) => {
    const wrapper = mountControl(type);
    await wrapper.setProps({ readonly: type !== 'readonly', modelValue: ['1', 0, 9], options: [{ label: '用户', value: 1 }, { label: '停用', value: 0 }] });
    expect(wrapper.get('.readonly-value').text()).toBe('用户, 停用, 9');
    expect(wrapper.find('.selection').exists()).toBe(false);
    await wrapper.setProps({ options: [{ label: '新用户', value: 1 }] });
    expect(wrapper.get('.readonly-value').text()).toBe('新用户, 0, 9');
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();
  });

  it('只读展示支持 formatter、树形选项和空字符串', async () => {
    const wrapper = mountControl('readonly');
    await wrapper.setProps({ field: { ...field('readonly'), list_formatter: 'money' }, modelValue: 12.5 });
    expect(wrapper.text()).toBe('￥12.50');
    await wrapper.setProps({ field: { ...field('readonly'), default_value: '2' }, modelValue: '2', options: [{ label: '父', value: 1, children: [{ label: '子', value: 2 }] }] });
    expect(wrapper.text()).toBe('子');
    await wrapper.setProps({ modelValue: '' });
    expect(wrapper.text()).toBe('');
  });

  it.each(['image', 'images', 'file', 'files'])('%s 只读时保留上传专用分支', async (type) => {
    const wrapper = mountControl(type);
    await wrapper.setProps({ readonly: true, modelValue: '/test.png' });
    const upload = wrapper.getComponent({ name: 'Upload' });
    expect(upload.props('modelValue')).toBe('/test.png');
    expect(upload.props('disabled')).toBe(true);
    expect(wrapper.find('.readonly-value').exists()).toBe(false);
  });

  it('显式空值不被默认值替换，避免详情与列表展示不同记录值', async () => {
    const wrapper = mountControl('readonly');
    await wrapper.setProps({ field: { ...field('readonly'), default_value: '99', list_formatter: 'money' }, modelValue: null });
    expect(wrapper.text()).toBe('');
    wrapper.unmount();
  });

  it('可编辑选择控件保留值更新与选择事件', async () => {
    const wrapper = mountControl('select');
    wrapper.getComponent(Selection).vm.$emit('update:modelValue', 1);
    wrapper.getComponent(Selection).vm.$emit('change', 1);
    await nextTick();
    expect(wrapper.emitted('update:modelValue')).toEqual([[1]]);
    expect(wrapper.emitted('event')).toEqual([['select', 1]]);
  });
});
