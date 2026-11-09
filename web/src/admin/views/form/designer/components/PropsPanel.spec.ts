import { defineComponent } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ElementPlus from 'element-plus';
import { createI18n } from 'vue-i18n';
import PropsPanel from './PropsPanel.vue';
import propsPanelSource from './PropsPanel.vue?raw';
import propsFoldsSource from './PropsFieldFolds.vue?raw';
import propsToggleSource from './PropsToggle.vue?raw';
import { businessDevelopmentApi } from '@/api/development/business';
import { CONTROL_REGISTRY, createNode } from '../../registry';
import type { FormSchemaNode } from '../../schema/types';
import { patchDatabase, patchLayout, patchList, patchRelation, withRequired } from '../../schema/nodeEdits';
import zhCN from '@/locales/zh-CN';

const dictTypeApi = vi.hoisted(() => ({ list: vi.fn() }));
vi.mock('@/api/system/dict', () => ({ dictTypeApi, dictApi: { options: vi.fn(), batch: vi.fn() }, dictItemApi: {} }));
vi.mock('@/api/development/business', () => ({ businessDevelopmentApi: { databaseTables: vi.fn(), databaseTableSchema: vi.fn() } }));

const selectStub = defineComponent({
  props: ['modelValue'],
  emits: ['update:modelValue', 'visible-change'],
  template: '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value); $emit(\'visible-change\', true)"><slot /></select>'
});
const optionStub = defineComponent({ props: ['label', 'value'], template: '<option :value="value">{{ label }}</option>' });

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
/** 选项不在 jsdom <select> 的直接子级（el-option-group）或尚未加载时，直接让下拉组件发出选择。 */
const choose = async (wrapper: ReturnType<typeof render>, prop: string, value: string) => {
  wrapper.get(`[data-prop="${prop}"]`).findComponent(selectStub).vm.$emit('update:modelValue', value);
  await flushPromises();
};
const make = (type: string, extra: Partial<FormSchemaNode> = {}): FormSchemaNode => ({ ...createNode(type, 1, `node_${type}_1`), ...extra });
const spanned = (span: number) => make('input', { layout: { span, group: '' } });
const rules = (validation: FormSchemaNode['validation'], extra: Partial<FormSchemaNode> = {}) => make('input', { validation, ...extra });
const render = (node: FormSchemaNode, extra: Record<string, unknown> = {}) => mount(PropsPanel, {
  props: { moduleId: 1, node, sourceType: 'created', controls: CONTROL_REGISTRY, ...extra },
  global: { plugins: [ElementPlus, i18n], stubs: { ElSelect: selectStub, ElOption: optionStub } }
});

describe('字典控件数据源配置', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({
      list: [
        { id: 1, code: 'order_status', name: '订单状态', status: 1 },
        { id: 2, code: 'pay_channel', name: '支付渠道', status: 1 }
      ],
      total: 2
    });
  });

  it('字典控件显示字典选择器并加载字典分类，选择后写入 kind 与 dictionary', async () => {
    const node = make('dictionary');
    expect(node.dataSource).toMatchObject({ kind: 'dictionary', dictionary: '' });
    const wrapper = render(node);
    await flushPromises();
    expect(dictTypeApi.list).toHaveBeenCalled();
    const select = wrapper.get('[data-testid="dictionary-code-select"]');
    await select.setValue('order_status');
    const patch = wrapper.emitted('update')?.at(-1)?.[0] as Record<string, unknown>;
    expect(Object.keys(patch)).toEqual(['dataSource']);
    const source = patch.dataSource as Record<string, unknown>;
    expect(source).toMatchObject({ kind: 'dictionary', dictionary: 'order_status' });
    expect(source).not.toHaveProperty('mode');
    wrapper.unmount();
  });

  it('回显已保存的字典编码（kind 键）', async () => {
    const byKind = render(make('dictionary', { dataSource: { kind: 'dictionary', dictionary: 'pay_channel', options: [] } }));
    await flushPromises();
    expect(byKind.get('[data-testid="dictionary-code-select"]').element).toHaveProperty('value', 'pay_channel');
    byKind.unmount();
  });

  it('非字典控件不渲染字典选择器', async () => {
    const wrapper = render(make('input'));
    await flushPromises();
    expect(wrapper.find('[data-testid="dictionary-code-select"]').exists()).toBe(false);
    expect(dictTypeApi.list).not.toHaveBeenCalled();
    wrapper.unmount();
  });
});

describe('属性面板布局', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({ list: [], total: 0 });
  });

  it('标题区：字段标题 + 控件类型标签 + 「字段名 · 列类型 · 位置」', async () => {
    const wrapper = render(make('input', { field: 'order_no', title: '订单号', database: { columnType: 'varchar(32)' } }), { groupTitle: '基本信息' });
    await flushPromises();
    expect(wrapper.get('[data-props-title]').text()).toBe('订单号');
    expect(wrapper.get('[data-props-chip]').text()).toBe('单行输入');
    expect(wrapper.get('[data-props-meta]').text()).toBe('order_no · varchar(32) · 位于「基本信息」');
    wrapper.unmount();
    const root = render(make('input', { field: 'remark', title: '备注', database: { columnType: 'varchar(255)' } }));
    await flushPromises();
    expect(root.get('[data-props-meta]').text()).toBe('remark · varchar(255) · 位于根节点');
    root.unmount();
  });

  it('页签依次为 表单 / 列表 / 数据库列 / 属性，默认表单', async () => {
    const wrapper = render(make('input'));
    await flushPromises();
    expect(wrapper.findAll('.el-tabs__item').map((item) => item.text())).toEqual(['表单', '列表', '数据库列', '属性']);
    expect(wrapper.get('.el-tabs__item.is-active').text()).toBe('表单');
    wrapper.unmount();
  });

  it('四个页签的表单都是标签在上、输入在下', async () => {
    const wrapper = render(make('input'));
    await flushPromises();
    for (const tab of ['form', 'list', 'column', 'properties']) {
      const forms = wrapper.get(`[data-props-tab="${tab}"]`).findAll('form.el-form');
      expect(forms.length, tab).toBeGreaterThan(0);
      forms.forEach((form) => expect(form.classes(), tab).toContain('el-form--label-top'));
    }
    wrapper.unmount();
  });

  it('表单页签按 基础 / 布局 / 状态 分组，基础含 显示名称、控件类型、分组、占位提示', async () => {
    const wrapper = render(make('input'));
    await flushPromises();
    const form = wrapper.get('[data-props-tab="form"]');
    expect(form.findAll('[data-props-section]').map((item) => item.attributes('data-props-section'))).toEqual(['basic', 'layout', 'state']);
    expect(form.findAll('[data-props-section-title]').map((item) => item.text())).toEqual(['基础', '布局', '状态']);
    const basic = form.get('[data-props-section="basic"]');
    expect(basic.findAll('.el-form-item__label').map((item) => item.text())).toEqual(['显示名称', '控件类型', '分组', '占位提示']);
    expect(form.get('[data-props-section="layout"]').text()).toContain('宽度');
    expect(form.get('[data-props-section="state"]').findAll('[data-toggle]').map((item) => item.text())).toEqual(['必填', '显示', '只读']);
    expect(form.text()).not.toContain('编辑禁改');
    wrapper.unmount();
  });

  it('布局节点只保留 表单 / 属性 页签与原始 JSON 折叠项，状态区只有「显示」', async () => {
    const wrapper = render(make('group'));
    await flushPromises();
    expect(wrapper.findAll('.el-tabs__item').map((item) => item.text())).toEqual(['表单', '属性']);
    expect(wrapper.get('[data-props-chip]').text()).toBe('分组');
    expect(wrapper.get('[data-props-meta]').text()).toBe('node_group_1 · 位于根节点');
    expect(wrapper.findAll('[data-fold]').map((item) => item.attributes('data-fold'))).toEqual(['raw']);
    expect(wrapper.findAll('[data-toggle]').map((item) => item.attributes('data-toggle'))).toEqual(['form_show']);
    expect(wrapper.find('[data-prop="placeholder"]').exists()).toBe(false);
    expect(wrapper.find('[data-span-option="24"]').exists()).toBe(true);
    wrapper.unmount();
  });
});

describe('宽度分段 ↔ layout.span', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({ list: [], total: 0 });
  });

  const activeOption = (wrapper: ReturnType<typeof render>) => wrapper.findAll('[data-span-option]').filter((item) => item.attributes('aria-checked') === 'true').map((item) => item.attributes('data-span-option'));

  it('分段依次为 1/4 · 1/3 · 1/2 · 整行 · 自定义，对应 6/8/12/24', async () => {
    const wrapper = render(spanned(24));
    await flushPromises();
    expect(wrapper.findAll('[data-span-option]').map((item) => [item.attributes('data-span-option'), item.text()])).toEqual([
      ['6', '1/4'], ['8', '1/3'], ['12', '1/2'], ['24', '整行'], ['custom', '自定义']
    ]);
    expect(activeOption(wrapper)).toEqual(['24']);
    expect(wrapper.get('[data-span-hint]').text()).toBe('对应栅格 24 列中的 6 / 8 / 12 / 24');
    wrapper.unmount();
  });

  it.each([[6, '6'], [8, '8'], [12, '12'], [24, '24']])('layout.span=%s 时高亮对应分段', async (span, option) => {
    const wrapper = render(spanned(span));
    await flushPromises();
    expect(activeOption(wrapper)).toEqual([option]);
    wrapper.unmount();
  });

  it('点击分段写回 layout.span 6/8/12/24', async () => {
    const wrapper = render(spanned(10));
    await flushPromises();
    for (const span of [6, 8, 12, 24]) {
      await wrapper.get(`[data-span-option="${span}"]`).trigger('click');
      expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ layout: { span, group: '' } });
    }
    wrapper.unmount();
  });

  it('非 6/8/12/24 的值显示「自定义 N 列」，不被改写', async () => {
    const wrapper = render(spanned(10));
    await flushPromises();
    expect(activeOption(wrapper)).toEqual(['custom']);
    expect(wrapper.get('[data-span-hint]').text()).toBe('自定义 10 列');
    expect(wrapper.emitted('update')).toBeUndefined();
    await wrapper.setProps({ node: spanned(18) });
    expect(wrapper.get('[data-span-hint]').text()).toBe('自定义 18 列');
    expect(wrapper.emitted('update')).toBeUndefined();
    wrapper.unmount();
  });

  it('「自定义」展开精确输入，不立即改写；输入后写回 layout.span', async () => {
    const wrapper = render(spanned(24));
    await flushPromises();
    expect(wrapper.find('[data-span-custom-input]').exists()).toBe(false);
    await wrapper.get('[data-span-option="custom"]').trigger('click');
    expect(wrapper.emitted('update')).toBeUndefined();
    const input = wrapper.get('[data-span-custom-input] input');
    await input.setValue('10');
    await input.trigger('change');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ layout: { span: 10, group: '' } });
    wrapper.unmount();
  });

  it('属性页签可精确输入栅格列数', async () => {
    const wrapper = render(spanned(10));
    await flushPromises();
    const input = wrapper.get('[data-props-tab="properties"] [data-prop="form_span"] input');
    expect((input.element as HTMLInputElement).value).toBe('10');
    await input.setValue('20');
    await input.trigger('change');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ layout: { span: 20, group: '' } });
    wrapper.unmount();
  });
});

describe('状态切换块', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({ list: [], total: 0 });
  });

  it('必填 / 显示 / 只读 三块同一行，开关写回 validation / hidden / disabled', async () => {
    const wrapper = render(make('input', { validation: [], hidden: false, disabled: false }));
    await flushPromises();
    const row = wrapper.get('[data-props-toggles="form"]');
    expect(row.findAll('[data-toggle]').map((item) => item.attributes('data-toggle'))).toEqual(['form_required', 'form_show', 'form_readonly']);
    expect(row.get('[data-toggle="form_show"]').classes()).toContain('is-on');
    expect(row.get('[data-toggle="form_required"]').classes()).not.toContain('is-on');
    await row.get('[data-toggle="form_required"] .el-switch').trigger('click');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ validation: [{ type: 'required', value: true }] });
    await row.get('[data-toggle="form_show"] .el-switch').trigger('click');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ hidden: true });
    await row.get('[data-toggle="form_readonly"] .el-switch').trigger('click');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ disabled: true });
    expect(wrapper.emitted('update')).toHaveLength(3);
    wrapper.unmount();
  });

  it('点击切换块的文字区域同样切换，且只写一次', async () => {
    const wrapper = render(make('input', { disabled: true }));
    await flushPromises();
    await wrapper.get('[data-toggle="form_readonly"] [data-toggle-label]').trigger('click');
    expect(wrapper.emitted('update')).toEqual([[{ disabled: false }]]);
    wrapper.unmount();
  });

  it('「只读」对应节点 disabled', async () => {
    const wrapper = render(make('input', { disabled: true }));
    await flushPromises();
    const block = wrapper.get('[data-toggle="form_readonly"]');
    expect(block.text()).toBe('只读');
    expect(block.classes()).toContain('is-on');
    wrapper.unmount();
  });
});

describe('折叠区摘要', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({ list: [], total: 0 });
  });

  const summary = (wrapper: ReturnType<typeof render>, fold: string) => wrapper.get(`[data-fold="${fold}"] [data-fold-summary]`).text();

  it('校验规则、联动条件、数据源、原始 JSON 为表单页签下的折叠项', async () => {
    const wrapper = render(rules([{ type: 'required', value: true }, { type: 'max', value: 20 }], { conditions: [{ when: { field: 'status', op: 'eq', value: 1 }, then: { action: 'show' } }] }));
    await flushPromises();
    const form = wrapper.get('[data-props-tab="form"]');
    expect(form.findAll('[data-fold]').map((item) => item.attributes('data-fold'))).toEqual(['validation', 'conditions', 'dataSource', 'raw']);
    expect(form.findAll('[data-fold-label]').map((item) => item.text())).toEqual(['校验规则', '联动条件', '数据源', '原始 JSON（高级）']);
    expect(form.get('[data-fold="validation"]').text()).toContain('required');
    expect(form.get('[data-fold="validation"]').text()).toContain('max');
    wrapper.unmount();
  });

  it('校验规则摘要直接写出规则：必填 · 最大 32 字符', async () => {
    const wrapper = render(rules([{ type: 'maxLength', value: 32 }, { type: 'required', value: true }]));
    await flushPromises();
    expect(summary(wrapper, 'validation')).toBe('必填 · 最大 32 字符');
    expect(wrapper.get('[data-fold="validation"]').findAll('[data-rule-summary]').map((item) => item.text())).toEqual(['必填', '最大 32 字符']);
    wrapper.unmount();
  });

  it.each([
    [{ min: 1, max: 99 }, '最小 1 · 最大 99'],
    [{ minlen: 2, maxlen: 10 }, '最少 2 字符 · 最大 10 字符'],
    [{ type: 'array', min: 1, max: 3 }, '最少 1 项 · 最多 3 项'],
    [{ pattern: '^\\d+$' }, '正则 ^\\d+$'],
    [{ email: true }, '邮箱格式'],
    [{ format: 'url' }, '网址格式'],
    [{ enum: ['a', 'b'] }, '可选值 a / b'],
    [{ precision: 2 }, '精度 2 位'],
    [{ custom_rule: { x: 1 } }, 'custom_rule {"x":1}']
  ])('校验规则 %j 的摘要为 %s', async (entries, text) => {
    const wrapper = render(rules(Object.entries(entries).map(([type, value]) => ({ type, value }))));
    await flushPromises();
    expect(summary(wrapper, 'validation')).toBe(text);
    wrapper.unmount();
  });

  it('没有规则或条件时摘要为「无」', async () => {
    const wrapper = render(make('input', { validation: [], conditions: [] }));
    await flushPromises();
    expect(summary(wrapper, 'validation')).toBe('无');
    expect(summary(wrapper, 'conditions')).toBe('无');
    wrapper.unmount();
  });

  it('联动条件摘要写出条件与动作', async () => {
    const node = make('input', {
      conditions: [
        { when: { field: 'status', op: 'eq', value: 1 }, then: { action: 'show' } },
        { when: { op: 'and', conditions: [{ field: 'type', op: 'neq', value: 'a' }, { field: 'remark', op: 'notEmpty' }] }, then: { action: 'disable' } },
        { when: {}, then: {} }
      ]
    });
    const wrapper = render(node);
    await flushPromises();
    expect(wrapper.get('[data-fold="conditions"]').findAll('[data-condition-summary]').map((item) => item.text())).toEqual([
      'status = 1 时显示',
      'type ≠ a 且 remark 不为空 时禁用',
      '未设置动作'
    ]);
    expect(summary(wrapper, 'conditions')).toBe('status = 1 时显示；type ≠ a 且 remark 不为空 时禁用；未设置动作');
    wrapper.unmount();
  });

  it('数据源折叠项承载选项来源 JSON，原始 JSON 折叠项承载 props JSON，修改仍写回字段', async () => {
    const wrapper = render(make('select'));
    await flushPromises();
    const options = wrapper.get('[data-fold="dataSource"] textarea');
    (options.element as HTMLTextAreaElement).value = '{"kind":"static","options":[]}';
    await options.trigger('change');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ dataSource: { kind: 'static', options: [] } });
    const raw = wrapper.get('[data-fold="raw"] textarea');
    (raw.element as HTMLTextAreaElement).value = '{"size":"large"}';
    await raw.trigger('change');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ props: { size: 'large' } });
    expect(wrapper.find('[data-props-tab="properties"] textarea').exists()).toBe(false);
    wrapper.unmount();
  });

  it('字典控件的字典选择位于数据源折叠项并默认展开', async () => {
    const wrapper = render(make('dictionary'));
    await flushPromises();
    expect(wrapper.get('[data-fold="dataSource"]').find('[data-testid="dictionary-code-select"]').exists()).toBe(true);
    expect(wrapper.get('[data-fold="dataSource"]').classes()).toContain('is-active');
    wrapper.unmount();
  });
});

describe('每个控件写回对应的节点切片', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({ list: [], total: 0 });
    vi.mocked(businessDevelopmentApi.databaseTableSchema).mockResolvedValue({ columns: [{ name: 'id', type: 'int', primary: true }, { name: 'name', type: 'varchar' }] } as never);
  });

  it('data-prop / data-toggle 不变，各自产出节点补丁', async () => {
    const base = make('input');
    const node = patchList(patchRelation(base, { type: 'belongs_to', table: 'fa_user' }), { show: true, sort: false });
    const wrapper = render(node);
    await flushPromises();
    const last = () => wrapper.emitted('update')?.at(-1)?.[0];
    const input = async (prop: string, value: string) => { await wrapper.get(`[data-prop="${prop}"] input`).setValue(value); };
    const number = async (selector: string, value: string) => { const el = wrapper.get(`${selector} input`); await el.setValue(value); await el.trigger('change'); };
    const select = async (prop: string, value: string) => { await wrapper.get(`[data-prop="${prop}"] select`).setValue(value); };
    const toggle = async (key: string) => { await wrapper.get(`[data-toggle="${key}"] .el-switch`).trigger('click'); };

    await input('label', '名称'); expect(last()).toEqual({ title: '名称' });
    await input('placeholder', '请输入'); expect(last()).toEqual({ props: { ...node.props, placeholder: '请输入' } });
    await input('form_group', '基础'); expect(last()).toEqual({ layout: patchLayout(node, { group: '基础' }).layout });
    await wrapper.get('[data-span-option="12"]').trigger('click'); expect(last()).toEqual({ layout: patchLayout(node, { span: 12 }).layout });
    await toggle('form_required'); expect(last()).toEqual({ validation: withRequired(node, true).validation });
    await toggle('form_show'); expect(last()).toEqual({ hidden: true });
    await toggle('form_readonly'); expect(last()).toEqual({ disabled: true });
    await toggle('list_show'); expect(last()).toEqual({ list: patchList(node, { show: false }).list });
    await toggle('list_sort'); expect(last()).toEqual({ list: patchList(node, { sort: true }).list });
    await select('list_filter', 'eq'); expect(last()).toEqual({ list: patchList(node, { filter: 'eq' }).list });
    await select('list_formatter', 'tag'); expect(last()).toEqual({ list: patchList(node, { formatter: 'tag' }).list });
    await number('[data-prop="list_width"]', '120'); expect(wrapper.emitted('update')).toContainEqual([{ list: patchList(node, { width: 120 }).list }]);
    await input('field_name', 'title'); expect(last()).toEqual({ field: 'title' });
    await select('column_type', 'int'); expect(last()).toEqual({ database: patchDatabase(node, { columnType: 'int' }).database });
    await toggle('nullable'); expect(last()).toEqual({ database: patchDatabase(node, { nullable: false }).database });
    await input('default_value', '0'); expect(last()).toEqual({ defaultValue: '0' });
    await toggle('unsigned'); expect(last()).toEqual({ database: patchDatabase(node, { unsigned: true }).database });
    await select('index_type', 'unique'); expect(last()).toEqual({ database: patchDatabase(node, { index: 'unique' }).database });
    await input('comment', '备注'); expect(last()).toEqual({ database: patchDatabase(node, { comment: '备注' }).database });
    await select('relation_type', 'has_many'); expect(last()).toEqual({ database: patchRelation(node, { type: 'has_many' }).database });
    await select('relation_on_delete', 'cascade'); expect(last()).toEqual({ database: patchRelation(node, { onDelete: 'cascade' }).database });
    await wrapper.get('[data-prop="relation_label_field"] select').trigger('change');
    await flushPromises();
    await select('relation_label_field', 'name'); expect(last()).toEqual({ database: patchRelation(node, { labelField: 'name' }).database });
    await select('relation_value_field', 'name'); expect(last()).toEqual({ database: patchRelation(node, { valueField: 'name' }).database });
    await wrapper.get('[data-props-tab="properties"] [data-property="placeholder"] input').setValue('x');
    expect(last()).toEqual({ props: { ...(node.props ?? {}), placeholder: 'x' } });
    wrapper.unmount();
  });

  it('编辑只产出改动的切片，不携带 events / access / attrs / info / valueType', async () => {
    const node = make('input', { events: { change: [{ type: 'x' }] }, access: { write: ['a'] }, attrs: { id: 'k' }, info: 'i', valueType: 'string' });
    const wrapper = render(node);
    await flushPromises();
    await wrapper.get('[data-toggle="list_show"] .el-switch').trigger('click');
    await wrapper.get('[data-prop="label"] input').setValue('新');
    for (const [patch] of wrapper.emitted('update') ?? []) {
      for (const key of ['events', 'access', 'attrs', 'info', 'valueType', 'children', 'id']) expect(patch as object).not.toHaveProperty(key);
    }
    wrapper.unmount();
  });

  it('切换控件用 changeControl：重置 props / dataSource / valueType，新建表同时改列类型', async () => {
    const wrapper = render(make('select', { valueType: 'string' }));
    await flushPromises();
    await choose(wrapper, 'type', 'textarea');
    const patch = wrapper.emitted('update')?.at(-1)?.[0] as Partial<FormSchemaNode>;
    expect(Object.keys(patch).sort()).toEqual(['dataSource', 'database', 'props', 'type', 'valueType']);
    expect(patch).toMatchObject({ type: 'textarea', dataSource: null, props: { rows: 4 }, database: { columnType: 'text' } });
    expect(patch.valueType).toBeUndefined();
    wrapper.unmount();
  });

  it('采纳表切换控件保留列类型', async () => {
    const wrapper = render(make('select'), { sourceType: 'adopted' });
    await flushPromises();
    await choose(wrapper, 'type', 'textarea');
    const patch = wrapper.emitted('update')?.at(-1)?.[0] as Partial<FormSchemaNode>;
    expect(patch).toMatchObject({ type: 'textarea' });
    expect(patch).not.toHaveProperty('database');
    wrapper.unmount();
  });

  it('控件类型只列出字段控件；布局与子表容器不可切换', async () => {
    const wrapper = render(make('input'));
    await flushPromises();
    const values = wrapper.get('[data-prop="type"] select').findAll('option').map((option) => option.attributes('value'));
    expect(values).toContain('textarea');
    for (const type of ['group', 'tabs', 'grid', 'repeatable', 'subform']) expect(values).not.toContain(type);
    wrapper.unmount();
    for (const type of ['group', 'repeatable']) {
      const container = render(make(type));
      await flushPromises();
      expect(container.get('[data-prop="type"] select').findAll('option').map((option) => option.attributes('value'))).toEqual([type]);
      container.unmount();
    }
  });

  it('关联表选择写回 database.relation 的 table / labelField / valueField', async () => {
    const wrapper = render(patchRelation(make('input'), { type: 'belongs_to', table: '' }));
    await flushPromises();
    await choose(wrapper, 'relation_table', 'fa_user');
    const patches = (wrapper.emitted('update') ?? []).map(([patch]) => patch as Partial<FormSchemaNode>);
    expect(patches.every((patch) => Object.keys(patch).join() === 'database')).toBe(true);
    expect(patches.at(-1)?.database?.relation).toMatchObject({ type: 'belongs_to', table: 'fa_user', valueField: 'id', labelField: 'name' });
    wrapper.unmount();
  });

  it('源码不再出现 v1 字段键的写回', () => {
    for (const source of [propsPanelSource, propsFoldsSource]) {
      const script = source.slice(source.indexOf('<script'), source.indexOf('</script>'));
      expect(script).not.toMatch(/FormFieldDef|NODE_FIELD_PREFIX|control_props|options_source|validate_rules|link_rules/);
      expect(source).not.toMatch(/patch\(\{\s*(?:form_|list_|relation_|column_type|field_name|default_value|index_type)/);
    }
  });

  it('样式只用主题变量，不写死颜色、不用夜间仍为浅色的 primary-light 变量，控件不溢出', () => {
    for (const source of [propsPanelSource, propsFoldsSource, propsToggleSource]) {
      const style = source.slice(source.indexOf('<style'));
      expect(style).not.toMatch(/--el-color-primary-light-\d/);
      expect(style.split('\n').filter((line) => /#[0-9a-f]{3,8}\b|rgba?\(|(?<![-\w])(?:white|black)(?![-\w])/i.test(line))).toEqual([]);
    }
    expect(propsPanelSource).toMatch(/min-width:\s*0/);
  });
});
