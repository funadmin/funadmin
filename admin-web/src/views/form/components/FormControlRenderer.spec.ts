import { defineComponent, h, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
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
      ElPagination: Pagination
    }
  }
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
});
